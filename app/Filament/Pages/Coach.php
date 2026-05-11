<?php

namespace App\Filament\Pages;

use App\Ai\Agents\FinanceCoach;
use App\Ai\Tools\BudgetSnapshot;
use App\Exceptions\ShareFailedException;
use App\Models\Action;
use App\Models\CoachMemory;
use App\Models\Goal;
use App\Services\Sharer;
use App\Services\TipResolver;
use App\Tips\Tip;
use BackedEnum;
use Carbon\Carbon;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolCall;
use Laravel\Ai\Streaming\Events\ToolResult;
use Throwable;

class Coach extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.coach';

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

    public ?int $activeGoalId = null;

    public array $goalHistory = [];

    public bool $historyOpen = false;

    public bool $newGoalOpen = false;

    public string $newGoalName = '';

    public string $newGoalLabel = 'general';

    public ?string $streamingText = null;

    public ?string $pendingPrompt = null;

    public array $pendingAttachments = [];

    public array $planActions = [];

    public string $planFilter = 'pendente';

    public ?int $completingActionId = null;

    public ?string $completingActionTitle = null;

    public string $completingNotes = '';

    public ?int $sharingMessageIndex = null;

    public string $shareRecipient = '';

    public string $shareSubject = '';

    public string $shareBody = '';

    public ?string $shareError = null;

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
        $this->activateDefaultGoal();
    }

    /**
     * Pick the user's defaultGoal as the active workspace and load its
     * latest conversation. No-op when the user has no goals (shouldn't
     * happen — UserObserver creates one on signup).
     */
    protected function activateDefaultGoal(): void
    {
        $defaultGoal = auth()->user()?->defaultGoal();

        if ($defaultGoal === null) {
            return;
        }

        // setActiveGoal does its own Goal::find. Hand off the already-
        // resolved Goal model to skip the redundant lookup.
        $this->activateGoal($defaultGoal);
    }

    /**
     * Inner setActiveGoal that takes an already-loaded Goal model — skips
     * the extra Goal::find($id) query that setActiveGoal does. Used by
     * activateDefaultGoal on mount and by setActiveGoal after a sidebar
     * click.
     */
    protected function activateGoal(Goal $goal): void
    {
        $this->activeGoalId = $goal->id;
        $latest = $goal->latestConversation();

        if ($latest) {
            $this->loadConversation($latest->id);
        } else {
            $this->messages = [];
            $this->conversationId = null;
        }

        $this->historyOpen = false;
        $this->goalHistory = [];
        $this->loadPlan();
    }

    public function loadPlan(): void
    {
        $query = Action::query();

        // Scope plan to the active goal so each workspace shows only its
        // own actions. Falls back to user-wide if no goal is active yet.
        if ($this->activeGoalId !== null) {
            $query->where('goal_id', $this->activeGoalId);
        }

        if ($this->planFilter !== 'todas') {
            $query->where('status', $this->planFilter);
        }

        $this->planActions = $query
            ->orderByRaw("CASE status WHEN 'em_andamento' THEN 0 WHEN 'pendente' THEN 1 WHEN 'concluido' THEN 2 ELSE 3 END")
            ->orderByRaw('deadline IS NULL, deadline ASC')
            ->orderByRaw("CASE priority WHEN 'alta' THEN 0 WHEN 'media' THEN 1 ELSE 2 END")
            ->limit(100)
            ->get()
            ->map(function (Action $a) {
                $attachments = collect($a->attachments ?? [])
                    ->filter(fn ($p) => is_string($p) && $p !== '')
                    ->map(fn (string $path) => [
                        'path' => $path,
                        'name' => basename($path),
                    ])
                    ->values()
                    ->all();

                $hasDetails = filled($a->description)
                    || filled($a->importance)
                    || filled($a->difficulty)
                    || filled($a->snooze_until)
                    || filled($a->result_notes)
                    || filled($a->completed_at)
                    || filled($attachments);

                return [
                    'id' => $a->id,
                    'title' => $a->title,
                    'category' => $a->category,
                    'priority' => $a->priority,
                    'status' => $a->status,
                    'deadline' => $a->deadline?->format('d/m/Y'),
                    'is_overdue' => $a->isOverdue(),
                    'is_due_soon' => $a->isDueSoon(),
                    'description' => $a->description,
                    'importance' => $a->importance,
                    'difficulty' => $a->difficulty,
                    'snooze_until' => $a->snooze_until?->format('d/m/Y'),
                    'result_notes' => $a->result_notes,
                    'completed_at' => $a->completed_at?->format('d/m/Y'),
                    'attachments' => $attachments,
                    'has_details' => $hasDetails,
                ];
            })
            ->toArray();
    }

    public function setPlanFilter(string $filter): void
    {
        $this->planFilter = $filter;
        $this->loadPlan();
    }

    public function startCompleteAction(int $id): void
    {
        $action = Action::find($id);
        if (! $action) {
            return;
        }
        $this->completingActionId = $id;
        $this->completingActionTitle = $action->title;
        $this->completingNotes = '';
    }

    public function cancelCompleteAction(): void
    {
        $this->completingActionId = null;
        $this->completingActionTitle = null;
        $this->completingNotes = '';
    }

    public function confirmCompleteAction(): void
    {
        if ($this->completingActionId === null) {
            return;
        }

        $payload = [
            'status' => 'concluido',
            'completed_at' => now(),
            'snooze_until' => null,
        ];

        $notes = trim($this->completingNotes);
        if ($notes !== '') {
            $payload['result_notes'] = $notes;
        }

        Action::where('id', $this->completingActionId)->update($payload);

        $this->cancelCompleteAction();
        $this->loadPlan();
    }

    public function snoozeAction(int $id, string $duration): void
    {
        $until = match ($duration) {
            'tomorrow' => now()->addDay(),
            '3days' => now()->addDays(3),
            'week' => now()->addWeek(),
            'month' => now()->addMonth(),
            default => null,
        };

        Action::where('id', $id)->update(['snooze_until' => $until?->toDateString()]);
        $this->loadPlan();
    }

    /**
     * Open the share modal with the body pre-filled from the assistant
     * message at $messageIndex. Silently no-ops for invalid indices and
     * user-authored messages — sharing your own input doesn't make
     * sense (the recipient would get back the question, not the
     * answer).
     */
    public function openShareModal(int $messageIndex): void
    {
        if (! isset($this->messages[$messageIndex])) {
            return;
        }

        $msg = $this->messages[$messageIndex];
        if (($msg['role'] ?? null) !== 'assistant') {
            return;
        }

        $this->sharingMessageIndex = $messageIndex;
        $this->shareRecipient = '';
        $this->shareSubject = (string) __('coach.share_modal.default_subject', [
            'date' => now()->format('d/m/Y'),
        ]);
        $this->shareBody = (string) ($msg['content'] ?? '');
        $this->shareError = null;
    }

    public function cancelShare(): void
    {
        $this->sharingMessageIndex = null;
        $this->shareRecipient = '';
        $this->shareSubject = '';
        $this->shareBody = '';
        $this->shareError = null;
    }

    public function confirmShare(): void
    {
        if ($this->sharingMessageIndex === null) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            $this->shareError = (string) __('coach.share.errors.unauthenticated');

            return;
        }

        try {
            $message = app(Sharer::class)->send(
                user: $user,
                to: $this->shareRecipient,
                subject: $this->shareSubject,
                body: $this->shareBody,
            );

            Notification::make()
                ->title($message)
                ->success()
                ->send();

            $this->cancelShare();
        } catch (ShareFailedException $e) {
            $this->shareError = $e->getMessage();
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('message')
                    ->hiddenLabel()
                    ->placeholder(__('coach.composer.placeholder'))
                    ->rows(2)
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

    /**
     * Open + in-progress actions in the current plan view. Drives the
     * badge on the "Plano" button.
     */
    public function pendingPlanCount(): int
    {
        return $this->memoPendingPlanCount ??= collect($this->planActions)
            ->whereIn('status', Action::OPEN_STATUSES)
            ->count();
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

        if (! empty($this->planActions)) {
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

            $renderable = $isAssistant ? BudgetSnapshot::expandPlaceholders($content) : $content;

            return [
                'role' => $isAssistant ? 'assistant' : 'user',
                'content' => $content,
                'content_html' => $isAssistant ? Str::markdown($renderable, [
                    'html_input' => 'escape',
                    'allow_unsafe_links' => false,
                ]) : null,
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

            $coach = new FinanceCoach;

            if ($this->conversationId) {
                $coach = $coach->continue($this->conversationId, as: auth()->user());
            } else {
                $coach = $coach->forUser(auth()->user());
            }

            $coach = $coach->forGoal($this->activeGoalId);

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
                $promptToSend .= "\n\n---\n\n"
                    ."FORMATO OBRIGATÓRIO da sua resposta:\n\n"
                    ."1️⃣ PRIMEIRO a tabela markdown estruturada do documento:\n\n"
                    ."| Campo | Valor |\n"
                    ."|---|---|\n"
                    ."| Tipo | (fatura cartão / boleto / extrato / certidão / contrato / nota fiscal / DARF / GPS / DAS / outro) |\n"
                    ."| Emissor | (banco/empresa/órgão) |\n"
                    ."| Pagador | (nome ou CPF/CNPJ) |\n"
                    ."| Categoria | PF / PJ / Híbrido |\n"
                    ."| Valor total | R\$ X,XX |\n"
                    ."| Vencimento | DD/MM/AAAA |\n"
                    ."| Data emissão | DD/MM/AAAA |\n"
                    ."| Identificador | (número/código) |\n"
                    ."| Pontos críticos | (parcelamento/juros/atraso/observações) |\n\n"
                    ."2️⃣ DEPOIS da tabela, faça uma ANÁLISE QUALITATIVA do conteúdo (NÃO PULE):\n"
                    ."   - Se for fatura de cartão: liste 3-5 lançamentos mais relevantes (valores acima da média, padrões, recorrentes).\n"
                    .'     IDENTIFIQUE explicitamente despesas que parecem PJ no cartão pessoal: Google Cloud, AWS, Vercel, GitHub, '
                    ."     Microsoft, Figma, Workspace, hosting, SaaS de dev. Some o total dessas e diga o que faria sentido migrar.\n"
                    ."   - Se for extrato: identifique padrões de gasto — categorias recorrentes, picos, sangrias.\n"
                    ."   - Se for boleto/imposto: contexto fiscal e implicações de atraso.\n"
                    ."   Use bullets ou parágrafos curtos. Não tem que ser exaustivo, mas tem que ser ÚTIL.\n\n"
                    ."3️⃣ DEPOIS, 1-2 frases sobre o que o usuário deveria fazer.\n\n"
                    ."4️⃣ POR ÚLTIMO chame RememberFact com label e summary.\n\n"
                    .'REGRA CRÍTICA: a tabela + análise + texto devem aparecer na sua mensagem visível PRIMEIRO. '
                    .'NUNCA termine apenas com tool call sem texto. '
                    .'NÃO PULE a análise qualitativa — é o que mais importa pro usuário.';
            }

            $this->streamingText = '';

            ['text' => $accumulated, 'tools' => $toolActivity, 'streamText' => $streamText] =
                $this->streamOnePass($coach, $promptToSend, $documents);

            // Auto-retry once if the first pass shows truncation or
            // hallucinated tool calls. The model continues the same
            // conversation, so it sees its prior (broken) reply and the
            // corrective nudge below — a clean second attempt is usually
            // enough for Gemini to finish the work.
            $rawText = trim($accumulated !== '' ? $accumulated : (string) ($streamText ?? ''));
            $shouldRetry = $rawText !== ''
                && $this->decorateAssistantResponse($rawText, $toolActivity) !== $rawText;

            if ($shouldRetry) {
                Log::warning('Coach auto-retrying after broken first pass', [
                    'goal_id' => $this->activeGoalId,
                    'tools_called' => array_column($toolActivity, 'name'),
                    'accumulated_tail' => mb_substr($rawText, -120),
                ]);

                $this->stream(to: 'coach-stream', content: "\n\n— _reexecutando…_ —\n\n");

                $retryPrompt = '[Sistema]: Sua resposta anterior narrou ações ("criei", "adicionei", "atualizei", "marquei") '
                    .'mas NÃO chamou as tools correspondentes (CreateAction / UpdateAction / RememberFact), '
                    .'OU terminou no meio de uma frase. Execute AGORA as tools necessárias e finalize com texto curto. '
                    .'NÃO narre — execute. Se for caso de listar, chame ListActions.';

                ['text' => $retryAccumulated, 'tools' => $retryTools, 'streamText' => $retryStreamText] =
                    $this->streamOnePass($coach, $retryPrompt, []);

                $accumulated = $retryAccumulated;
                $toolActivity = array_merge($toolActivity, $retryTools);
                $streamText = $retryStreamText;
            }

            // Capture the conversation ID after streaming so the next turn continues it.
            $this->conversationId = $coach->currentConversation() ?? $this->conversationId;

            // laravel/ai inserts agent_conversations rows without our goal_id.
            // Stamp the active goal so this thread is owned by the right
            // workspace (sidebar ordering, history, plan scoping all rely
            // on it).
            if ($this->conversationId !== null && $this->activeGoalId !== null) {
                DB::table('agent_conversations')
                    ->where('id', $this->conversationId)
                    ->whereNull('goal_id')
                    ->update(['goal_id' => $this->activeGoalId]);
            }

            // SwitchToGoal tool may have re-pointed the conversation at a
            // different goal during this turn. Re-read the conversation's
            // goal_id and sync activeGoalId so the sidebar highlight, plan
            // flyout, and CreateAction default all follow the move.
            if ($this->conversationId !== null) {
                $convGoalId = DB::table('agent_conversations')
                    ->where('id', $this->conversationId)
                    ->value('goal_id');

                if ($convGoalId !== null && $convGoalId !== $this->activeGoalId) {
                    $this->activeGoalId = $convGoalId;
                }
            }

            $rawText = trim($accumulated !== '' ? $accumulated : (string) ($streamText ?? ''));

            if ($rawText === '') {
                $rawText = $this->summarizeToolActivity($toolActivity);
            } else {
                $rawText = $this->decorateAssistantResponse($rawText, $toolActivity);
            }

            // Diagnostic: log when the response shows signs of a Gemini
            // truncation or hallucinated tool call so we can correlate
            // user reports with server-side state.
            if ($rawText !== $accumulated) {
                Log::warning('Coach response decorated', [
                    'conversation_id' => $this->conversationId,
                    'goal_id' => $this->activeGoalId,
                    'accumulated_length' => mb_strlen($accumulated),
                    'tools_called' => array_column($toolActivity, 'name'),
                    'accumulated_tail' => mb_substr(trim($accumulated), -120),
                ]);
            }

            // The AI SDK persists the bare model text in
            // agent_conversation_messages.content. We overwrite that with
            // $rawText so verbatim-injected tool output (e.g. the
            // BudgetSnapshot table) and any decorator warnings survive a
            // page reload or goal switch — otherwise the table is only
            // visible during the live stream and disappears afterward.
            if ($this->conversationId !== null && $rawText !== '' && $rawText !== ($streamText ?? '')) {
                $latestAssistantId = DB::table('agent_conversation_messages')
                    ->where('conversation_id', $this->conversationId)
                    ->where('role', 'assistant')
                    ->orderByDesc('created_at')
                    ->value('id');
                if ($latestAssistantId !== null) {
                    DB::table('agent_conversation_messages')
                        ->where('id', $latestAssistantId)
                        ->update(['content' => $rawText, 'updated_at' => now()]);
                }
            }

            $renderable = BudgetSnapshot::expandPlaceholders($rawText);
            $this->messages[] = [
                'role' => 'assistant',
                'content' => $rawText,
                'content_html' => Str::markdown($renderable, [
                    'html_input' => 'escape',
                    'allow_unsafe_links' => false,
                ]),
                'attachments' => [],
                'time' => now()->format('H:i'),
            ];

            $this->streamingText = null;
            $this->loadGoals();
            $this->loadPlan();
        } catch (Throwable $e) {
            Log::error('Coach.runAi threw', [
                'user_id' => auth()->id(),
                'active_goal_id' => $this->activeGoalId,
                'conversation_id' => $this->conversationId,
                'exception_class' => $e::class,
                'message' => $e->getMessage(),
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

    /**
     * Single streaming pass: accumulates text deltas, batches tool
     * calls into UI indicators, and returns the final text + activity
     * log. Extracted so runAi() can call it twice when an auto-retry
     * is warranted.
     *
     * @return array{text:string, tools:list<array{name:string,count:int,ok:int}>, streamText:?string}
     */
    protected function streamOnePass(FinanceCoach $coach, string $promptToSend, array $documents): array
    {
        $accumulated = '';
        $toolLabels = (array) __('coach.tool_labels');
        // Tools whose markdown output should be rendered verbatim in the
        // chat. The agent tends to paraphrase, but for these the structured
        // output IS the message — we want the user to see the actual table.
        $verbatimTools = ['BudgetSnapshot'];
        $batch = ['name' => null, 'calls' => 0, 'ok' => 0, 'verbatim' => []];
        $toolActivity = [];
        // Verbatim payloads collected across all batches in this pass —
        // persisted into this pass's assistant message so the placeholder
        // survives an auto-retry that drops it from $accumulated.
        $passVerbatims = [];

        $flushBatch = function () use (&$batch, &$toolActivity, &$accumulated, &$passVerbatims, $toolLabels) {
            if ($batch['name'] === null) {
                return;
            }
            $label = $toolLabels[$batch['name']] ?? $batch['name'];
            $count = $batch['calls'];
            $allOk = $batch['ok'] === $count;
            $icon = $allOk ? '✓' : '⚠';
            $suffix = $count > 1 ? " ({$count}x)" : '';
            $this->stream(to: 'coach-stream', content: " {$icon}{$suffix}\n\n");
            foreach ($batch['verbatim'] as $payload) {
                // Persist the raw placeholder so coach_budgets remains the
                // source of truth at view time. But the live stream needs
                // the expanded markdown — the user is watching the bubble
                // fill in, and a literal `{{budget:5}}` would flash by.
                $expanded = BudgetSnapshot::expandPlaceholders($payload);
                $this->stream(to: 'coach-stream', content: $expanded."\n\n");
                $accumulated .= $payload."\n\n";
                $passVerbatims[] = $payload;
            }
            $toolActivity[] = ['name' => $batch['name'], 'count' => $count, 'ok' => $batch['ok']];
            $batch = ['name' => null, 'calls' => 0, 'ok' => 0, 'verbatim' => []];
        };

        $stream = $coach->stream(
            $promptToSend,
            attachments: $documents,
            provider: Lab::Gemini,
            model: config('coach.models.interactive'),
        );

        foreach ($stream as $event) {
            if ($event instanceof TextDelta) {
                $flushBatch();
                $accumulated .= $event->delta;
                $this->stream(to: 'coach-stream', content: $event->delta);
            } elseif ($event instanceof ToolCall) {
                if ($batch['name'] !== null && $batch['name'] !== $event->toolCall->name) {
                    $flushBatch();
                }
                if ($batch['name'] === null) {
                    $label = $toolLabels[$event->toolCall->name] ?? $event->toolCall->name;
                    $this->stream(to: 'coach-stream', content: "\n⏳ {$label}…");
                    $batch['name'] = $event->toolCall->name;
                }
                $batch['calls']++;
            } elseif ($event instanceof ToolResult) {
                if ($event->successful) {
                    $batch['ok']++;
                    $name = $event->toolResult->name;
                    if (in_array($name, $verbatimTools, true) && $batch['name'] === $name) {
                        $batch['verbatim'][] = (string) $event->toolResult->result;
                    }
                }
            }
        }

        $flushBatch();

        // Anchor the verbatim placeholders to THIS pass's assistant
        // message. The SDK has already persisted $stream->text into
        // agent_conversation_messages.content; we prepend our placeholders
        // so a follow-up auto-retry (which writes its own message and may
        // overwrite later) can't strip the snapshot from the conversation.
        $conversationId = $this->conversationId ?? $coach->currentConversation();
        if (! empty($passVerbatims) && $conversationId !== null) {
            $latest = DB::table('agent_conversation_messages')
                ->where('conversation_id', $conversationId)
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
        ];
    }

    /**
     * Append a discreet warning when the assistant response looks
     * truncated (ends mid-thought) or narrates an action it didn't
     * actually execute as a tool call. Both patterns happen when
     * Gemini stops mid-stream — the "criei" word is in the text,
     * but no CreateAction tool fired, so the plan is unchanged.
     *
     * @param  list<array{name:string,count:int,ok:int}>  $toolActivity
     */
    protected function decorateAssistantResponse(string $text, array $toolActivity = []): string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return $text;
        }

        $toolsCalled = collect($toolActivity)->pluck('name')->all();

        // A trailing colon (or em-dash) usually opens a list/sentence that
        // was never finished — the LLM cut off before the tool call or the
        // continuation. Check this BEFORE the narration heuristic so a
        // truncated response gets the right hint even when its dangling
        // text happens to mention an action verb.
        $endsOpenEnded = preg_match('/[:\-—]\s*$/u', $trimmed) === 1;
        if ($endsOpenEnded && empty($toolsCalled)) {
            return $trimmed."\n\n".__('coach.errors.truncated_warning');
        }

        // Verb forms only — adjectives like "atualizado" / "concluída" can
        // appear in normal commentary without claiming a tool ran. We only
        // flag clear 1st/3rd-person preterite forms.
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

    protected function summarizeToolActivity(array $activity): string
    {
        if (empty($activity)) {
            return __('coach.errors.no_text_returned');
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
            return __('coach.recap.done');
        }

        return __('coach.recap.with_results', ['parts' => implode(', ', $parts)]);
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
