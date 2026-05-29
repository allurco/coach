<?php

namespace App\Agent\Services;

use App\Agent\Agents\CoachAgent;
use App\Domains\Finance\Tools\BudgetSnapshot;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;

/**
 * Orchestrates one turn of conversation with the Coach agent — used by
 * both the Filament chat page and the email webhook. Centralises the
 * behaviours that used to live in Coach.php::runAi (streaming, retry,
 * decoration, conversation-goal stamping) so callers don't each
 * reimplement them with their own minor variations.
 *
 * Callers pass:
 *   - `onChunk`: a closure that receives each text/UI chunk for live
 *     streaming. When null, the call is effectively blocking — the
 *     internal stream still runs (so tool activity is captured), but
 *     no chunk callback fires.
 *   - `modelKey`: 'interactive' or 'background', mapped via
 *     `config('coach.models.<key>')`. The web chat uses 'interactive'
 *     (Pro); the email path uses 'background' (Flash).
 *
 * Callers do NOT authenticate. The service does it once at the start of
 * the turn (see authenticateTurn) so global scopes on Action/Memory/Budget
 * work for any tool the agent fires — without disturbing an already-active
 * web session.
 *
 * See ADR 0003 (Agent is its own layer) and the Phase 7 commit body.
 */
class CoachInteraction
{
    /**
     * Tools whose output the chat treats as verbatim — the raw markdown
     * placeholder is preserved in the persisted assistant message so the
     * structured display (e.g. the BudgetSnapshot table) survives a
     * page reload. This list is a known leak from the Finance pack into
     * the Agent layer; will move to a pack contribution mechanism when
     * a second pack pressures the same pattern.
     */
    protected const VERBATIM_TOOLS = ['BudgetSnapshot'];

    /**
     * @param  array<Document|Image>  $documents
     */
    public function run(
        User $user,
        string $promptText,
        array $documents = [],
        ?string $conversationId = null,
        ?int $activeGoalId = null,
        ?Closure $onChunk = null,
        string $promptPrefix = '',
        string $modelKey = 'background',
    ): InteractionResult {
        // Authenticate once so Action/Memory/Budget global scopes apply
        // for the duration of any tools the agent fires.
        $this->authenticateTurn($user);

        $coach = $this->buildAgent($user, $conversationId, $activeGoalId);

        $fullPrompt = $promptPrefix === '' ? $promptText : $promptPrefix.$promptText;
        $model = (string) config("coach.models.{$modelKey}", $modelKey);

        ['text' => $accumulated, 'tools' => $toolActivity, 'streamText' => $streamText, 'conversationId' => $convId]
            = $this->streamOnePass($coach, $fullPrompt, $documents, $onChunk, $model, $conversationId);

        $rawText = trim($accumulated !== '' ? $accumulated : (string) ($streamText ?? ''));
        $retried = false;
        $shouldRetry = $rawText !== ''
            && $this->decorate($rawText, $toolActivity) !== $rawText;

        if ($shouldRetry) {
            Log::warning('CoachInteraction auto-retrying after broken first pass', [
                'goal_id' => $activeGoalId,
                'conversation_id' => $convId,
                'tools_called' => array_column($toolActivity, 'name'),
                'accumulated_tail' => mb_substr($rawText, -120),
            ]);

            if ($onChunk !== null) {
                $onChunk("\n\n— _retrying…_ —\n\n");
            }

            $retryPrompt = '[System]: Your previous response narrated actions ("created", "added", '
                .'"updated", "marked") but did NOT call the corresponding tools (CreateAction / '
                .'UpdateAction / RememberFact), OR ended mid-sentence. Execute the necessary tools '
                .'NOW and finish with a short text. DO NOT narrate — execute. If a list is needed, '
                .'call ListActions.';

            ['text' => $retryAccumulated, 'tools' => $retryTools, 'streamText' => $retryStreamText, 'conversationId' => $convId]
                = $this->streamOnePass($coach, $retryPrompt, [], $onChunk, $model, $convId);

            $accumulated = $retryAccumulated;
            $toolActivity = array_merge($toolActivity, $retryTools);
            $streamText = $retryStreamText;
            $retried = true;
        }

        // SwitchToGoal may have re-pointed the conversation at a different
        // goal during this turn. Re-read the persisted goal_id so callers
        // can sync their own view of the active goal.
        $effectiveGoalId = $activeGoalId;
        if ($convId !== null) {
            $convGoalId = DB::table('agent_conversations')->where('id', $convId)->value('goal_id');
            if ($convGoalId !== null) {
                $effectiveGoalId = (int) $convGoalId;
            }
        }

        $rawText = trim($accumulated !== '' ? $accumulated : (string) ($streamText ?? ''));
        if ($rawText === '') {
            $rawText = $this->summarizeToolActivity($toolActivity);
        } else {
            $rawText = $this->decorate($rawText, $toolActivity);
        }

        // Overwrite the SDK's bare assistant content with the decorated +
        // verbatim-augmented text so it survives a reload (otherwise tool
        // tables and decoration warnings only exist in the live stream).
        if ($convId !== null && $rawText !== '' && $rawText !== ($streamText ?? '')) {
            $latestAssistantId = DB::table('agent_conversation_messages')
                ->where('conversation_id', $convId)
                ->where('role', 'assistant')
                ->orderByDesc('created_at')
                ->value('id');

            if ($latestAssistantId !== null) {
                DB::table('agent_conversation_messages')
                    ->where('id', $latestAssistantId)
                    ->update(['content' => $rawText, 'updated_at' => now()]);
            }
        }

        return new InteractionResult(
            text: $rawText,
            conversationId: $convId,
            effectiveGoalId: $effectiveGoalId,
            toolActivity: $toolActivity,
            retried: $retried,
        );
    }

    /**
     * Make this turn run as $user so the owner global scopes on
     * Action/Memory/Budget resolve. In the stateless contexts (email webhook,
     * scheduled command) no one is authenticated, so we log in. In the
     * interactive web chat the user is ALREADY the authenticated session user;
     * calling auth()->login() again would migrate the session id (a fresh id
     * every turn — see SessionGuard::updateSession → session()->regenerate).
     * The interactive turn runs inside the streamed runAi() response, where the
     * new session cookie isn't reliably applied in a standalone PWA, so the next
     * message arrives on the destroyed session id and bounces to login. Skipping
     * the redundant re-login keeps the session stable across messages.
     */
    protected function authenticateTurn(User $user): void
    {
        if (auth()->id() === $user->getAuthIdentifier()) {
            return;
        }

        auth()->login($user);
    }

    protected function buildAgent(User $user, ?string $conversationId, ?int $activeGoalId): CoachAgent
    {
        $coach = new CoachAgent;
        $coach = $conversationId
            ? $coach->continue($conversationId, as: $user)
            : $coach->forUser($user);

        return $coach->forGoal($activeGoalId);
    }

    /**
     * Runs one streaming pass through the agent, accumulating text and
     * tool activity. When `$onChunk` is provided, emits each piece of
     * output to the caller for live display. Always streams internally
     * — the callback just controls whether chunks are surfaced.
     *
     * @param  array<Document|Image>  $documents
     * @return array{text: string, tools: list<array{name: string, count: int, ok: int}>, streamText: ?string, conversationId: ?string}
     */
    protected function streamOnePass(
        CoachAgent $coach,
        string $promptText,
        array $documents,
        ?Closure $onChunk,
        string $model,
        ?string $conversationId,
    ): array {
        $accumulated = '';
        $toolLabels = (array) __('coach.tool_labels');
        $batch = ['name' => null, 'calls' => 0, 'ok' => 0, 'verbatim' => []];
        $toolActivity = [];
        $passVerbatims = [];

        $flushBatch = function () use (&$batch, &$toolActivity, &$accumulated, &$passVerbatims, $onChunk) {
            if ($batch['name'] === null) {
                return;
            }
            $count = $batch['calls'];
            $allOk = $batch['ok'] === $count;
            $icon = $allOk ? '✓' : '⚠';
            $suffix = $count > 1 ? " ({$count}x)" : '';
            if ($onChunk !== null) {
                $onChunk(" {$icon}{$suffix}\n\n");
            }
            foreach ($batch['verbatim'] as $payload) {
                if ($onChunk !== null) {
                    $expanded = BudgetSnapshot::expandPlaceholders($payload);
                    $onChunk($expanded."\n\n");
                }
                $accumulated .= $payload."\n\n";
                $passVerbatims[] = $payload;
            }
            $toolActivity[] = ['name' => $batch['name'], 'count' => $count, 'ok' => $batch['ok']];
            $batch = ['name' => null, 'calls' => 0, 'ok' => 0, 'verbatim' => []];
        };

        $stream = $coach->stream(
            $promptText,
            attachments: $documents,
            provider: Lab::Gemini,
            model: $model,
        );

        foreach ($stream as $event) {
            if ($event instanceof TextDelta) {
                $flushBatch();
                $accumulated .= $event->delta;
                if ($onChunk !== null) {
                    $onChunk($event->delta);
                }
            } elseif ($event instanceof ToolCall) {
                if ($batch['name'] !== null && $batch['name'] !== $event->toolCall->name) {
                    $flushBatch();
                }
                if ($batch['name'] === null) {
                    $label = $toolLabels[$event->toolCall->name] ?? $event->toolCall->name;
                    if ($onChunk !== null) {
                        $onChunk("\n⏳ {$label}…");
                    }
                    $batch['name'] = $event->toolCall->name;
                }
                $batch['calls']++;
            } elseif ($event instanceof ToolResult) {
                if ($event->successful) {
                    $batch['ok']++;
                    $name = $event->toolResult->name;
                    if (in_array($name, self::VERBATIM_TOOLS, true) && $batch['name'] === $name) {
                        $batch['verbatim'][] = (string) $event->toolResult->result;
                    }
                }
            }
        }

        $flushBatch();

        $resolvedConvId = $coach->currentConversation() ?? $conversationId;

        // Anchor verbatim placeholders into this pass's persisted assistant
        // message so an auto-retry can't strip them.
        if (! empty($passVerbatims) && $resolvedConvId !== null) {
            $latest = DB::table('agent_conversation_messages')
                ->where('conversation_id', $resolvedConvId)
                ->where('role', 'assistant')
                ->orderByDesc('created_at')
                ->first(['id', 'content']);
            if ($latest !== null) {
                $merged = implode("\n\n", $passVerbatims)."\n\n".(string) $latest->content;
                DB::table('agent_conversation_messages')
                    ->where('id', $latest->id)
                    ->update(['content' => $merged, 'updated_at' => now()]);
            }
        }

        return [
            'text' => $accumulated,
            'tools' => $toolActivity,
            'streamText' => $stream->text ?? null,
            'conversationId' => $resolvedConvId,
        ];
    }

    /**
     * Append a discreet warning when the assistant response looks
     * truncated or narrates an action it didn't actually execute as a
     * tool call. Both patterns happen when Gemini stops mid-stream —
     * the verb is in the text but no tool fired.
     *
     * Public so callers (and tests) can ask "would this response be
     * decorated?" without forcing a full interaction; CoachInteraction
     * itself uses it as the broken-response detector for the retry.
     *
     * @param  list<array{name: string, count: int, ok: int}>  $toolActivity
     */
    public function decorate(string $text, array $toolActivity = []): string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return $text;
        }

        $toolsCalled = collect($toolActivity)->pluck('name')->all();

        $endsOpenEnded = preg_match('/[:\-—]\s*$/u', $trimmed) === 1;
        if ($endsOpenEnded && empty($toolsCalled)) {
            return $trimmed."\n\n".__('coach.errors.truncated_warning');
        }

        $createPattern = '/\b(criei|criou|adicionei|adicionou|cadastrei|cadastrou)\b/iu';
        $updatePattern = '/\b(atualizei|atualizou|marquei|conclu[íi]|adiei|adiou)\b/iu';
        $rememberPattern = '/\b(salvei|guardei|anotei|memorizei)\b/iu';

        $missingCreate = preg_match($createPattern, $trimmed) === 1
            && ! in_array('CreateAction', $toolsCalled, true);
        $missingUpdate = preg_match($updatePattern, $trimmed) === 1
            && ! in_array('UpdateAction', $toolsCalled, true);
        $missingRemember = preg_match($rememberPattern, $trimmed) === 1
            && ! in_array('RememberFact', $toolsCalled, true);

        if ($missingCreate || $missingUpdate || $missingRemember) {
            return $trimmed."\n\n".__('coach.errors.narrated_no_tool');
        }

        return $text;
    }

    /**
     * Fallback summary when the agent ran tools but returned no text.
     *
     * @param  list<array{name: string, count: int, ok: int}>  $activity
     */
    protected function summarizeToolActivity(array $activity): string
    {
        if (empty($activity)) {
            return (string) __('coach.errors.no_text_returned');
        }

        $created = 0;
        $updated = 0;
        $remembered = 0;

        foreach ($activity as $entry) {
            match ($entry['name']) {
                'CreateAction' => $created += $entry['ok'],
                'UpdateAction' => $updated += $entry['ok'],
                'RememberFact' => $remembered += $entry['ok'],
                default => null,
            };
        }

        $parts = [];
        if ($created > 0) {
            $parts[] = $created === 1
                ? __('coach.recap.created_one')
                : __('coach.recap.created_many', ['count' => $created]);
        }
        if ($updated > 0) {
            $parts[] = $updated === 1
                ? __('coach.recap.updated_one')
                : __('coach.recap.updated_many', ['count' => $updated]);
        }
        if ($remembered > 0) {
            $parts[] = $remembered === 1
                ? __('coach.recap.remembered_one')
                : __('coach.recap.remembered_many', ['count' => $remembered]);
        }

        if (empty($parts)) {
            return (string) __('coach.recap.done');
        }

        return (string) __('coach.recap.with_results', ['parts' => implode(', ', $parts)]);
    }
}
