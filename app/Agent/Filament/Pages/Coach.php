<?php

namespace App\Agent\Filament\Pages;

use App\Agent\Filament\Concerns\HasShareMessageModal;
use App\Agent\Models\CoachMemory;
use App\Agent\Services\CoachInteraction;
use App\Domains\Finance\Tools\BudgetSnapshot;
use App\Models\Action;
use App\Models\Goal;
use App\Services\TipResolver;
use App\Tips\Tip;
use App\Tools\Tool;
use App\Tools\ToolRegistry;
use BackedEnum;
use Carbon\Carbon;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\Image;
use Livewire\Attributes\Url;
use Throwable;

class Coach extends Page implements HasForms
{
    use HasShareMessageModal;
    use InteractsWithForms;

    protected string $view = 'agent::coach';

    protected static ?string $slug = '/';

    protected static ?string $navigationLabel = 'Coach';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 2;

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public ?string $conversationId = null;

    public array $messages = [];

    public bool $thinking = false;

    /** Each entry: [id, name, label, last_activity_label]. */
    public array $goals = [];

    /**
     * The open Workspace's Goal, or null for the Goals start screen.
     * Synced to ?goal= so a Workspace deep-links and the browser back
     * button returns to the Goals screen (ADR 0007).
     */
    #[Url(as: 'goal')]
    public ?int $activeGoalId = null;

    /**
     * The open Tool within the Workspace (Plan, Budget, Contacts…), or
     * null for the chat. Synced to ?tool= so a Tool deep-links and the
     * back button returns to the chat. The set of Tools comes from the
     * ToolRegistry, keyed by the active Goal's pack (ADR 0007).
     */
    #[Url(as: 'tool')]
    public ?string $activeTool = null;

    public array $goalHistory = [];

    public bool $historyOpen = false;

    /**
     * Toggled true while the workspace shell is on screen but the chat
     * history hasn't been fetched yet (the deferred-load window). Drives
     * the chat skeleton. See loadLatestConversation.
     */
    public bool $conversationLoading = false;

    public bool $newGoalOpen = false;

    public string $newGoalName = '';

    public string $newGoalLabel = 'general';

    public ?string $streamingText = null;

    public ?string $pendingPrompt = null;

    public array $pendingAttachments = [];

    // Memoization for view helpers called multiple times per render.
    // Private — Livewire doesn't dehydrate these, so they reset
    // naturally on each request. Mutators run before render, so the
    // cache is populated lazily during rendering only.
    private ?bool $memoIsFirstTimer = null;

    private ?int $memoPendingPlanCount = null;

    private ?string $memoUserFirstName = null;

    private ?Tip $memoTip = null;

    private bool $memoTipResolved = false;

    private ?array $memoActiveGoal = null;

    private bool $memoActiveGoalResolved = false;

    public function getHeading(): string
    {
        return '';
    }

    public function getMaxContentWidth(): string
    {
        return 'full';
    }

    /**
     * True the very first time someone lands on Coach: zero plan
     * actions and zero consolidated memories. The empty-state UI
     * uses this to swap the abstract greeting for a "what is this
     * thing" welcome card + use-case-oriented suggestions.
     */
    public function isFirstTimer(): bool
    {
        return $this->memoIsFirstTimer ??= Action::count() === 0 && CoachMemory::count() === 0;
    }

    public function mount(): void
    {
        // mount() is hot — it runs on every full page load (login redirect,
        // refresh, deep link). Order matters:
        //   1. form->fill()        cheap, in-memory
        //   2. loadGoals()         single query (sidebar)
        //   3. activateDefaultGoal cascades into setActiveGoal which already
        //      calls loadPlan(), so we don't call loadPlan() again here.
        $this->form->fill();
        $this->loadGoals();

        // Deep link / refresh with ?goal=N opens that Workspace (Goal::find
        // is owner-scoped, so a stale or foreign id falls back to the Goals
        // start screen). No ?goal → land on the Goals start screen with no
        // active Goal (ADR 0007). The Workspace shell renders synchronously
        // for an instant feel; the chat history loads via the deferred
        // loadLatestConversation call queued below.
        if ($this->activeGoalId !== null) {
            $goal = Goal::find($this->activeGoalId);

            if ($goal) {
                // activateGoal() resets activeTool — capture the URL's ?tool
                // first and re-open it (validated against this goal's Tools)
                // so a Tool deep-link / browser back survives the mount.
                $deepTool = $this->activeTool;
                $this->activateGoal($goal);

                if ($deepTool !== null) {
                    $this->openTool($deepTool);
                }
            } else {
                $this->activeGoalId = null;
                $this->activeTool = null;
            }
        }
    }

    /**
     * Return to the Goals start screen — clears the active Goal and its
     * conversation thread. Synced to the URL (?goal drops off).
     */
    public function backToGoals(): void
    {
        $this->activeGoalId = null;
        $this->activeTool = null;
        $this->messages = [];
        $this->conversationId = null;
        $this->conversationLoading = false;
        $this->historyOpen = false;
        $this->goalHistory = [];
        $this->memoActiveGoal = null;
        $this->memoActiveGoalResolved = false;
        $this->memoPendingPlanCount = null;
    }

    /**
     * Inner setActiveGoal that takes an already-loaded Goal model — skips
     * the extra Goal::find($id) query that setActiveGoal does. Used by
     * activateDefaultGoal on mount and by setActiveGoal after a sidebar
     * click. The chat history is NOT loaded here — the workspace shell
     * renders first (instant feel) and loadLatestConversation runs after,
     * triggered via $wire so the heavy markdown + DB work happens off the
     * goal-click critical path.
     */
    protected function activateGoal(Goal $goal): void
    {
        $this->activeGoalId = $goal->id;
        $this->messages = [];
        $this->conversationId = null;
        $this->conversationLoading = true;

        $this->historyOpen = false;
        $this->goalHistory = [];
        $this->activeTool = null;
        $this->memoPendingPlanCount = null;
        $this->memoActiveGoal = null;
        $this->memoActiveGoalResolved = false;
        // Tell the embedded PlanFlyout component to switch goals and reload —
        // Livewire children persist their own state across requests, so prop
        // changes alone don't propagate. See ADR 0005.
        $this->dispatch('plan-refreshed', activeGoalId: $goal->id);
        // Defer the chat history load to a follow-up Livewire request so the
        // workspace shell appears immediately; the skeleton shows in the chat
        // area until loadLatestConversation lands.
        $this->js('$wire.loadLatestConversation()');
    }

    /**
     * Deferred chat history load. Runs as a follow-up Livewire request right
     * after the workspace shell renders (queued via $this->js in activateGoal
     * / mount). Looks up the goal's latest conversation and hydrates $messages
     * via the write-through-cached loadConversation.
     */
    public function loadLatestConversation(): void
    {
        if ($this->activeGoalId === null) {
            $this->conversationLoading = false;

            return;
        }

        $goal = Goal::find($this->activeGoalId);
        if (! $goal) {
            $this->conversationLoading = false;

            return;
        }

        $latest = $goal->latestConversation();
        if ($latest) {
            $this->loadConversation($latest->id);
        }

        $this->conversationLoading = false;
    }

    /**
     * Open + in-progress action count for the active goal — drives the
     * "Plan" header badge in the chat. Independent query from the
     * embedded PlanFlyout component so the badge works without
     * round-tripping component state. Memoised per request.
     */
    public function pendingPlanCount(): int
    {
        if ($this->memoPendingPlanCount !== null) {
            return $this->memoPendingPlanCount;
        }

        $query = Action::query()->whereIn('status', Action::OPEN_STATUSES);
        if ($this->activeGoalId !== null) {
            $query->where('goal_id', $this->activeGoalId);
        }

        return $this->memoPendingPlanCount = $query->count();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('message')
                    ->hiddenLabel()
                    ->placeholder(__('coach.composer.placeholder'))
                    ->rows(1)
                    ->autosize()
                    ->autofocus()
                    ->extraInputAttributes([
                        // Enter sends, Shift+Enter inserts a newline (default
                        // textarea behaviour). requestSubmit triggers the
                        // form's wire:submit="send".
                        'x-on:keydown.enter' => 'if (!$event.shiftKey) { $event.preventDefault(); $el.closest(\'form\').requestSubmit(); }',
                    ]),
                FileUpload::make('attachments')
                    ->hiddenLabel()
                    ->multiple()
                    ->acceptedFileTypes(['application/pdf', 'image/png', 'image/jpeg', 'image/webp'])
                    ->maxFiles(5)
                    ->maxSize(10240)
                    ->disk('local')
                    ->directory('coach-uploads')
                    ->preserveFilenames()
                    ->visibility('private')
                    ->openable()
                    ->downloadable()
                    ->previewable()
                    ->imagePreviewHeight('100'),
            ])
            ->statePath('data');
    }

    /**
     * The active goal's sidebar entry — the array shape produced by
     * loadGoals(). Returns null when no goal is active, or when the
     * active id no longer matches any loaded goal.
     *
     * @return array{id:int,name:string,label:string,is_archived:bool,last_activity_label:?string}|null
     */
    public function activeGoal(): ?array
    {
        if ($this->memoActiveGoalResolved) {
            return $this->memoActiveGoal;
        }
        $this->memoActiveGoalResolved = true;

        if ($this->activeGoalId === null) {
            return $this->memoActiveGoal = null;
        }

        return $this->memoActiveGoal = collect($this->goals)->firstWhere('id', $this->activeGoalId);
    }

    /** First word of the auth user's name, used in the greeting line. */
    public function userFirstName(): string
    {
        return $this->memoUserFirstName ??= trim(explode(' ', auth()->user()?->name ?? '')[0] ?? '');
    }

    /**
     * Which suggestion bundle to surface in the empty-thread state:
     *   - first-timer (no plan + no memories) → onboarding-flavored
     *   - active plan                         → action-flavored
     *   - default                             → generic
     */
    public function suggestionsKey(): string
    {
        if ($this->isFirstTimer()) {
            return 'coach.suggestions_first';
        }

        // Was: `! empty($this->planActions)`. Phase 8 moved planActions
        // into the PlanFlyout Livewire component, so the chat page no
        // longer holds that state. pendingPlanCount() runs a focused
        // count query against the active goal — same semantics: "user
        // has open work" → active suggestions.
        if ($this->pendingPlanCount() > 0) {
            return 'coach.suggestions_active';
        }

        return 'coach.suggestions';
    }

    public function loadGoals(): void
    {
        $user = auth()->user();
        if (! $user) {
            $this->goals = [];

            return;
        }

        $this->goals = $user->goalsForSidebar()->map(fn ($g) => [
            'id' => $g->id,
            'name' => $g->name,
            'label' => $g->label,
            'is_archived' => (bool) $g->is_archived,
            'last_activity_label' => $g->last_activity_at
                ? $this->humanTime($g->last_activity_at)
                : null,
        ])->toArray();
    }

    /**
     * Switch the active workspace. Loads the goal's most recent conversation
     * (if any) into $messages, clears the message thread otherwise, and
     * refreshes the plan to show only this goal's actions.
     */
    public function setActiveGoal(int $goalId): void
    {
        $goal = Goal::find($goalId);
        if (! $goal) {
            return;
        }

        $this->activateGoal($goal);
    }

    /**
     * The Tools available in the active Goal's Workspace — core Tools plus
     * the active pack's Tools, from the ToolRegistry (ADR 0007). Shaped for
     * the tab bar / rail; labels resolved through __(). Empty on the Goals
     * start screen.
     *
     * @return list<array{key:string,label:string,icon:string,component:string,is_primary:bool}>
     */
    public function workspaceTools(): array
    {
        $goal = $this->activeGoal();
        if ($goal === null) {
            return [];
        }

        return collect(app(ToolRegistry::class)->forGoalLabel($goal['label']))
            ->map(fn (Tool $t) => [
                'key' => $t->key,
                'label' => (string) __($t->label),
                'icon' => $t->icon,
                'component' => $t->component,
                'is_primary' => $t->isPrimary,
            ])
            ->all();
    }

    /**
     * The Tool key for the slot next to Chat in the tab bar. Prefers the
     * pack's designated primary (Finance → 'budget'); when the pack has none
     * (e.g. a 'general' Goal with only core Tools), promotes the first
     * available Tool so the slot is never a dead placeholder. Null only on
     * the Goals start screen (no Tools at all).
     */
    public function primaryToolKey(): ?string
    {
        $goal = $this->activeGoal();
        if ($goal === null) {
            return null;
        }

        $primary = app(ToolRegistry::class)->primaryFor($goal['label']);

        return $primary?->key ?? ($this->workspaceTools()[0]['key'] ?? null);
    }

    /**
     * Open a Tool in the Workspace. Ignores keys that aren't available for
     * the active Goal (stale client, foreign pack), falling back to chat.
     */
    public function openTool(string $key): void
    {
        // Toggle: tapping the open Tool's button closes it (back to chat).
        if ($this->activeTool === $key) {
            $this->activeTool = null;

            return;
        }

        $available = collect($this->workspaceTools())->pluck('key')->all();
        $this->activeTool = in_array($key, $available, true) ? $key : null;
    }

    public function closeTool(): void
    {
        $this->activeTool = null;
    }

    public function startNewConversationInActiveGoal(): void
    {
        $this->messages = [];
        $this->conversationId = null;
        $this->form->fill(['message' => '', 'attachments' => []]);
    }

    public function toggleHistory(): void
    {
        $this->historyOpen = ! $this->historyOpen;

        if (! $this->historyOpen) {
            $this->goalHistory = [];

            return;
        }

        if ($this->activeGoalId === null) {
            return;
        }

        $goal = Goal::find($this->activeGoalId);
        if (! $goal) {
            return;
        }

        $this->goalHistory = $goal->conversationHistory()->map(fn ($c) => [
            'id' => $c->id,
            'title' => $c->title ?: __('coach.conversations.untitled'),
            'updated_label' => $this->humanTime($c->updated_at),
        ])->toArray();
    }

    public function openNewGoal(): void
    {
        $this->newGoalOpen = true;
        $this->newGoalName = '';
        $this->newGoalLabel = 'general';
    }

    public function cancelNewGoal(): void
    {
        $this->newGoalOpen = false;
        $this->newGoalName = '';
    }

    public function createGoal(): void
    {
        $name = trim($this->newGoalName);
        if ($name === '') {
            return;
        }

        $label = in_array($this->newGoalLabel, array_keys(Goal::LABELS), true)
            ? $this->newGoalLabel
            : 'general';

        $goal = Goal::create([
            'name' => $name,
            'label' => $label,
        ]);

        // Reset cached defaultGoal so subsequent calls see the new goal as
        // a candidate (the cache was filled at request start).
        auth()->user()?->refreshDefaultGoal();

        $this->cancelNewGoal();
        $this->loadGoals();
        $this->setActiveGoal($goal->id);
    }

    public function loadConversation(string $id): void
    {
        $userId = auth()->id();

        $exists = DB::table('agent_conversations')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->exists();

        if (! $exists) {
            return;
        }

        $rows = DB::table('agent_conversation_messages')
            ->where('conversation_id', $id)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at', 'asc')
            ->get();

        $this->messages = $rows->map(function ($m) {
            $isAssistant = $m->role === 'assistant';
            $content = (string) $m->content;
            $attachments = [];

            if (! empty($m->attachments) && $m->attachments !== '[]') {
                $decoded = json_decode($m->attachments, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $att) {
                        $attachments[] = $att['name'] ?? $att['path'] ?? '?';
                    }
                }
            }

            // Write-through cache: prefer the persisted content_html when the
            // assistant row already has it (~50-100ms saved on a goal switch).
            // When it's null — a row freshly inserted by the laravel/ai SDK
            // or a legacy row from before the column existed — render once
            // and persist so the next load is fast.
            $contentHtml = null;
            if ($isAssistant) {
                $contentHtml = $m->content_html ?: null;
                if ($contentHtml === null) {
                    $renderable = BudgetSnapshot::expandPlaceholders($content);
                    $contentHtml = Str::markdown($renderable, [
                        'html_input' => 'escape',
                        'allow_unsafe_links' => false,
                    ]);
                    DB::table('agent_conversation_messages')
                        ->where('id', $m->id)
                        ->update(['content_html' => $contentHtml]);
                }
            }

            return [
                'role' => $isAssistant ? 'assistant' : 'user',
                'content' => $content,
                'content_html' => $contentHtml,
                'attachments' => $attachments,
                'time' => Carbon::parse($m->created_at)->format('H:i'),
            ];
        })->toArray();

        $this->conversationId = $id;
    }

    /**
     * The one tip the user should see right now (or null). Memoized
     * per render — the blade hits this 4x (the @if plus three
     * attribute reads), and each resolve costs a Goal::find() and a
     * full catalog walk.
     */
    public function currentTip(): ?Tip
    {
        if ($this->memoTipResolved) {
            return $this->memoTip;
        }
        $this->memoTipResolved = true;

        $user = auth()->user();
        if (! $user) {
            return $this->memoTip = null;
        }

        $goal = $this->activeGoalId ? Goal::find($this->activeGoalId) : null;

        return $this->memoTip = app(TipResolver::class)->pick(
            $user,
            $goal,
            (array) session('coach.tips.dismissed', []),
        );
    }

    /**
     * Auto-send the tip's prompt as a user message and dismiss the
     * tip so it doesn't immediately reappear after the page rerenders.
     * Bypasses the form: the prompt is pre-written, no input needed.
     */
    public function clickTip(string $tipId): void
    {
        if ($this->thinking) {
            return;
        }

        $tip = app(TipResolver::class)->find($tipId);
        if ($tip === null) {
            return;
        }

        $prompt = $tip->prompt();

        $this->messages[] = [
            'role' => 'user',
            'content' => $prompt,
            'attachments' => [],
            'time' => now()->format('H:i'),
        ];

        $this->thinking = true;
        $this->pendingPrompt = $prompt;
        $this->pendingAttachments = [];

        $this->dismissTip($tipId);

        $this->js('$wire.runAi()');
    }

    public function dismissTip(string $tipId): void
    {
        $dismissed = (array) session('coach.tips.dismissed', []);
        if (! in_array($tipId, $dismissed, true)) {
            $dismissed[] = $tipId;
            session(['coach.tips.dismissed' => $dismissed]);
        }
    }

    public function send(): void
    {
        Log::info('Coach.send entered', [
            'user_id' => auth()->id(),
            'active_goal_id' => $this->activeGoalId,
            'conversation_id' => $this->conversationId,
            'thinking' => $this->thinking,
            'message_count' => count($this->messages),
        ]);

        if ($this->thinking) {
            return;
        }

        $data = $this->form->getState();
        $userMessage = trim($data['message'] ?? '');
        $attachmentPaths = $data['attachments'] ?? [];

        if ($userMessage === '' && empty($attachmentPaths)) {
            return;
        }

        $attachmentNames = array_map(
            fn ($p) => basename($p),
            is_array($attachmentPaths) ? $attachmentPaths : [],
        );

        $this->messages[] = [
            'role' => 'user',
            'content' => $userMessage ?: __('coach.attachments.sent_indicator'),
            'attachments' => $attachmentNames,
            'time' => now()->format('H:i'),
        ];

        $this->thinking = true;
        $this->pendingPrompt = $userMessage;
        $this->pendingAttachments = is_array($attachmentPaths) ? $attachmentPaths : [];
        $this->form->fill(['message' => '', 'attachments' => []]);

        // Defer AI processing to a second Livewire request so the user message
        // and "thinking" state render immediately. The frontend triggers runAi().
        $this->js('$wire.runAi()');
    }

    public function runAi(): void
    {
        Log::info('Coach.runAi entered', [
            'user_id' => auth()->id(),
            'active_goal_id' => $this->activeGoalId,
            'conversation_id' => $this->conversationId,
            'thinking' => $this->thinking,
            'pending_prompt_length' => strlen((string) $this->pendingPrompt),
            'pending_attachments' => count($this->pendingAttachments ?? []),
        ]);

        $userMessage = (string) $this->pendingPrompt;
        $attachmentPaths = $this->pendingAttachments ?? [];

        $this->pendingPrompt = null;
        $this->pendingAttachments = [];

        if (! $this->thinking) {
            return;
        }

        try {
            // Defense against a client-side race: if the cached
            // conversationId points to a conversation in a different goal
            // than the current activeGoalId (e.g. user clicked a goal and
            // submitted a message before the wire:click round-trip
            // finished), drop the stale conversation id so a fresh one is
            // started in the active goal. Without this, the new turn lands
            // in the previous workspace and the activeGoalId-aware tools
            // (CreateAction, LogWhy, LogWorry) stamp the wrong goal.
            if ($this->conversationId !== null && $this->activeGoalId !== null) {
                $convGoalId = DB::table('agent_conversations')
                    ->where('id', $this->conversationId)
                    ->value('goal_id');

                if ($convGoalId !== null && $convGoalId !== $this->activeGoalId) {
                    Log::info('Coach drift detected — dropping stale conversation', [
                        'cached_conversation_id' => $this->conversationId,
                        'cached_conversation_goal_id' => $convGoalId,
                        'active_goal_id' => $this->activeGoalId,
                    ]);
                    $this->conversationId = null;
                }
            }

            $documents = [];
            foreach ($attachmentPaths as $relativePath) {
                if (! Storage::disk('local')->exists($relativePath)) {
                    Log::warning('Coach attachment missing on disk', ['path' => $relativePath]);

                    continue;
                }

                $absolutePath = Storage::disk('local')->path($relativePath);
                $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

                $documents[] = match ($extension) {
                    'png', 'jpg', 'jpeg', 'webp', 'gif' => Image::fromPath($absolutePath),
                    default => Document::fromPath($absolutePath),
                };
            }

            Log::info('Coach prompt', [
                'message' => $userMessage,
                'attachment_count' => count($documents),
                'attachment_paths' => $attachmentPaths,
            ]);

            $promptToSend = $userMessage ?: __('coach.attachments.analyze_default');

            if (! empty($documents)) {
                // Runtime reminder of the 4-step format. The TABLE shape itself
                // is locale-aware and defined in the system prompt's
                // "## Attachment analysis template" (loaded from
                // resources/prompts/locale/{locale}.md). This prefix only
                // enforces the ORDER and the qualitative-analysis depth — no
                // duplication of the table fields, which would drift out of
                // sync with the per-locale template.
                $promptToSend .= "\n\n---\n\n"
                    ."MANDATORY FORMAT for your response:\n\n"
                    .'1️⃣ FIRST: render the markdown table from your '
                    ."'## Attachment analysis template' section verbatim, with the document's values filled in. "
                    ."Empty fields become '—'.\n\n"
                    ."2️⃣ AFTER the table, do a QUALITATIVE ANALYSIS of the content (DO NOT SKIP):\n"
                    ."   - If a credit card invoice/statement: list 3-5 most relevant line items (above-average values, patterns, recurring).\n"
                    .'     EXPLICITLY identify expenses that look like business charges on a personal card: Google Cloud, AWS, Vercel, GitHub, '
                    ."     Microsoft, Figma, Workspace, hosting, dev SaaS. Sum the total of those and say what would make sense to migrate.\n"
                    ."   - If a bank statement: identify spending patterns — recurring categories, spikes, leaks.\n"
                    ."   - If a payment slip / tax doc: fiscal context and implications of being late.\n"
                    ."   Use bullets or short paragraphs. Doesn't have to be exhaustive, but has to be USEFUL.\n\n"
                    ."3️⃣ THEN, 1-2 sentences on what the user should do.\n\n"
                    ."4️⃣ LASTLY call RememberFact with label and summary.\n\n"
                    .'CRITICAL RULE: the table + analysis + text must appear in your visible message FIRST. '
                    .'NEVER end with only a tool call without text. '
                    .'DO NOT SKIP the qualitative analysis — it\'s what matters most to the user.';
            }

            $this->streamingText = '';

            // Delegate the agent orchestration (build, stream, retry-on-broken,
            // decorate, persist) to CoachInteraction. The chat page keeps only
            // what's UI-flavoured: form state, Livewire streaming, drift
            // detection, Markdown rendering, and the messages array. The same
            // service runs from the email webhook so both paths share retry,
            // decoration, and goal-stamping behaviour.
            $result = app(CoachInteraction::class)->run(
                user: auth()->user(),
                promptText: $promptToSend,
                documents: $documents,
                conversationId: $this->conversationId,
                activeGoalId: $this->activeGoalId,
                onChunk: fn (string $chunk) => $this->stream(to: 'coach-stream', content: $chunk),
                modelKey: 'interactive',
            );

            $this->conversationId = $result->conversationId;
            $this->activeGoalId = $result->effectiveGoalId;
            $rawText = $result->text;

            // laravel/ai inserts agent_conversations rows without our goal_id.
            // Stamp the active goal so this thread is owned by the right
            // workspace (sidebar ordering, history, plan scoping all rely
            // on it). The service handles the SwitchToGoal sync, but not the
            // initial stamping for a freshly-started conversation.
            if ($this->conversationId !== null && $this->activeGoalId !== null) {
                DB::table('agent_conversations')
                    ->where('id', $this->conversationId)
                    ->whereNull('goal_id')
                    ->update(['goal_id' => $this->activeGoalId]);
            }

            $renderable = BudgetSnapshot::expandPlaceholders($rawText);
            $contentHtml = Str::markdown($renderable, [
                'html_input' => 'escape',
                'allow_unsafe_links' => false,
            ]);
            $this->messages[] = [
                'role' => 'assistant',
                'content' => $rawText,
                'content_html' => $contentHtml,
                'attachments' => [],
                'time' => now()->format('H:i'),
            ];
            // Seed the content_html cache for the message that was just written
            // (CoachInteraction inserts/overwrites the row with raw content but
            // doesn't render markdown). Saves the next loadConversation a
            // render+UPDATE round trip for this row.
            if ($this->conversationId !== null) {
                $latestAssistantId = DB::table('agent_conversation_messages')
                    ->where('conversation_id', $this->conversationId)
                    ->where('role', 'assistant')
                    ->orderByDesc('created_at')
                    ->value('id');
                if ($latestAssistantId !== null) {
                    DB::table('agent_conversation_messages')
                        ->where('id', $latestAssistantId)
                        ->update(['content_html' => $contentHtml]);
                }
            }

            $this->streamingText = null;
            $this->loadGoals();
            $this->memoPendingPlanCount = null;
            // Ask the embedded PlanFlyout to refresh after the agent may
            // have created/updated actions via tools. The current goal id
            // is passed so the component picks up SwitchToGoal moves too.
            $this->dispatch('plan-refreshed', activeGoalId: $this->activeGoalId);
        } catch (Throwable $e) {
            // When the upstream LLM (Gemini) rejects the request, the
            // RequestException carries a Response with the real reason
            // in the body — "HTTP 400" by itself is useless. Pull the
            // body out so we can see whether it's a tool-schema issue,
            // a content-policy block, a token-limit hit, etc.
            $upstreamBody = null;
            if ($e instanceof RequestException) {
                $upstreamBody = mb_substr((string) $e->response->body(), 0, 2000);
            }

            Log::error('Coach.runAi threw', [
                'user_id' => auth()->id(),
                'active_goal_id' => $this->activeGoalId,
                'conversation_id' => $this->conversationId,
                'exception_class' => $e::class,
                'message' => $e->getMessage(),
                'upstream_body' => $upstreamBody,
                'file' => $e->getFile().':'.$e->getLine(),
                'trace' => collect($e->getTrace())
                    ->take(15)
                    ->map(fn ($f) => ($f['file'] ?? '?').':'.($f['line'] ?? '?').' '.($f['class'] ?? '').($f['type'] ?? '').($f['function'] ?? ''))
                    ->all(),
            ]);

            $this->messages[] = [
                'role' => 'error',
                'content' => __('coach.errors.prefix').$e->getMessage(),
                'attachments' => [],
                'time' => now()->format('H:i'),
            ];
        }

        $this->thinking = false;
    }

    /**
     * Kept as the public alias the blade template binds to. Internally
     * routes to startNewConversationInActiveGoal — a fresh thread within
     * the user's currently selected workspace, not a new goal.
     */
    public function newConversation(): void
    {
        $this->startNewConversationInActiveGoal();
    }

    protected function humanTime($timestamp): string
    {
        $date = Carbon::parse($timestamp);
        $now = now();

        if ($date->isSameDay($now)) {
            return $date->format('H:i');
        }
        if ($date->isYesterday()) {
            return 'ontem';
        }

        $days = (int) floor($date->diffInDays($now, true));
        if ($days < 7) {
            return $days.'d';
        }

        return $date->format('d/m');
    }
}
