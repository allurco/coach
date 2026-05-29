<?php

namespace App\Livewire;

use App\Models\Action;
use Carbon\CarbonInterface;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Plan view — the user's open and completed actions, with complete-with-notes
 * and snooze interactions. Two consumers:
 *
 *   - The Coach chat page (agent layer) embeds this as a sidebar drawer
 *     bound to the active goal.
 *   - The Plan tool page (core) embeds this at full width with a goal
 *     picker, no drawer chrome.
 *
 * Cross-layer UI: lives in app/Livewire/ per ADR 0005. Depends only on
 * the core Action model so every layer can safely consume it.
 */
class PlanFlyout extends Component
{
    public ?int $activeGoalId = null;

    public bool $showGoalPicker = false;

    public bool $asDrawer = true;

    /**
     * Render the component's own editorial header (eyebrow + count). The
     * tool rail already shows the Tool's title + close in its bar, so it
     * embeds this with showHeader=false to avoid a duplicate "PLANO". The
     * standalone Plan tool page and the chat sidebar drawer keep it.
     */
    public bool $showHeader = true;

    public string $planFilter = 'pending';

    public array $planActions = [];

    public ?int $completingActionId = null;

    public ?string $completingActionTitle = null;

    public string $completingNotes = '';

    public function mount(
        ?int $activeGoalId = null,
        bool $showGoalPicker = false,
        bool $asDrawer = true,
        bool $showHeader = true,
    ): void {
        $this->activeGoalId = $activeGoalId;
        $this->showGoalPicker = $showGoalPicker;
        $this->asDrawer = $asDrawer;
        $this->showHeader = $showHeader;
        $this->loadPlan();
    }

    /**
     * Parent components dispatch `plan-refreshed` after they create or
     * update actions (chat page after runAi completes) OR when the
     * active goal changes (chat sidebar click). The optional goalId
     * param lets the parent push the new active goal into the embedded
     * component without forcing a full re-mount — Livewire's embedded
     * child components persist their own state across requests, so
     * prop changes don't reach them automatically.
     */
    #[On('plan-refreshed')]
    public function refreshPlan(?int $activeGoalId = null): void
    {
        if ($activeGoalId !== null) {
            $this->activeGoalId = $activeGoalId;
        }
        $this->loadPlan();
    }

    /**
     * Reload when the in-component goal-picker (Plan tool page) changes
     * the selection via wire:model.
     */
    public function updatedActiveGoalId(): void
    {
        $this->loadPlan();
    }

    public function loadPlan(): void
    {
        $query = Action::query();

        if ($this->activeGoalId !== null) {
            $query->where('goal_id', $this->activeGoalId);
        }

        if ($this->planFilter !== 'all') {
            $query->where('status', $this->planFilter);
        }

        $this->planActions = $query
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'pending' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END")
            ->orderByRaw('deadline IS NULL, deadline ASC')
            ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
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
                    'deadline' => $a->deadline ? $this->formatDeadlineLabel($a->deadline) : null,
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
            'status' => 'completed',
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
     * Open + in-progress actions in the current plan view — drives badge
     * counts in parent components. Computed from the already-loaded
     * planActions so it doesn't fire another query.
     */
    public function pendingCount(): int
    {
        return collect($this->planActions)
            ->whereIn('status', Action::OPEN_STATUSES)
            ->count();
    }

    /**
     * Natural-language deadline labels for nearby dates, ISO dates for
     * distant ones. 14 days covers most overdue/upcoming planning where
     * "in N days" reads more naturally than the full date.
     */
    protected function formatDeadlineLabel(CarbonInterface $date): string
    {
        $diff = (int) today()->diffInDays($date->copy()->startOfDay(), false);

        return match (true) {
            $diff === 0 => (string) __('coach.plan.deadline.today'),
            $diff === 1 => (string) __('coach.plan.deadline.tomorrow'),
            $diff === -1 => (string) __('coach.plan.deadline.yesterday'),
            $diff > 1 && $diff <= 14 => (string) __('coach.plan.deadline.in_days', ['n' => $diff]),
            $diff < -1 && $diff >= -14 => (string) __('coach.plan.deadline.days_ago', ['n' => abs($diff)]),
            default => $date->format('d/m/Y'),
        };
    }

    public function render()
    {
        return view('livewire.plan-flyout');
    }
}
