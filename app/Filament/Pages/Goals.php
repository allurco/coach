<?php

namespace App\Filament\Pages;

use App\Models\Action;
use App\Models\Goal;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

/**
 * The Goals tool — manage workspaces beyond what the chat sidebar offers.
 *
 * Chat sidebar handles: view active goals + switch + create new.
 * Goals tool adds: archived view, edit (name/label/color), archive +
 * unarchive, reorder (up/down), per-goal stats (open action count,
 * last conversation date).
 *
 * Listed under the "Tool Box" navigation group alongside Plan (and
 * future tools). See ADR 0004 for layer-owned Filament UI.
 */
class Goals extends Page
{
    protected string $view = 'filament.pages.goals';

    protected static ?string $slug = 'goals';

    protected static ?string $navigationLabel = 'Goals';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|\UnitEnum|null $navigationGroup = 'Tool Box';

    protected static ?int $navigationSort = 2;

    // Two collections — active is what shows in the chat sidebar; archived
    // is everything else. Pre-computed with stats so the view doesn't
    // run N+1 queries.
    public array $activeGoals = [];

    public array $archivedGoals = [];

    // Edit modal state.
    public ?int $editingGoalId = null;

    public string $editName = '';

    public string $editLabel = 'general';

    // New goal modal state.
    public bool $newGoalOpen = false;

    public string $newGoalName = '';

    public string $newGoalLabel = 'general';

    public function mount(): void
    {
        $this->loadGoals();
    }

    public function getHeading(): string
    {
        return (string) __('coach.sidebar.title');
    }

    /**
     * Pull all the user's goals (active + archived) with per-goal stats
     * folded in: open action count and last conversation date.
     *
     * One query per scoped collection, one aggregated query each for
     * action counts and conversation dates — no N+1 in the view.
     */
    public function loadGoals(): void
    {
        $goals = Goal::query()->orderBy('sort_order')->orderBy('id')->get();

        if ($goals->isEmpty()) {
            $this->activeGoals = [];
            $this->archivedGoals = [];

            return;
        }

        $goalIds = $goals->pluck('id')->all();

        $openCounts = Action::query()
            ->whereIn('goal_id', $goalIds)
            ->whereIn('status', Action::OPEN_STATUSES)
            ->select('goal_id', DB::raw('COUNT(*) as c'))
            ->groupBy('goal_id')
            ->pluck('c', 'goal_id')
            ->all();

        $lastConvs = DB::table('agent_conversations')
            ->whereIn('goal_id', $goalIds)
            ->select('goal_id', DB::raw('MAX(updated_at) as last_at'))
            ->groupBy('goal_id')
            ->pluck('last_at', 'goal_id')
            ->all();

        $shaped = $goals->map(fn (Goal $g) => [
            'id' => $g->id,
            'name' => $g->name,
            'label' => $g->label,
            'label_display' => Goal::LABELS[$g->label] ?? $g->label,
            'color' => $g->color,
            'sort_order' => $g->sort_order,
            'is_archived' => (bool) $g->is_archived,
            'open_count' => (int) ($openCounts[$g->id] ?? 0),
            'last_conv_at' => $lastConvs[$g->id] ?? null,
        ])->all();

        $this->activeGoals = array_values(array_filter($shaped, fn ($g) => ! $g['is_archived']));
        $this->archivedGoals = array_values(array_filter($shaped, fn ($g) => $g['is_archived']));
    }

    // Edit ----------------------------------------------------------------

    public function startEdit(int $goalId): void
    {
        $goal = Goal::find($goalId);
        if (! $goal) {
            return;
        }
        $this->editingGoalId = $goal->id;
        $this->editName = $goal->name;
        $this->editLabel = $goal->label ?? 'general';
    }

    public function cancelEdit(): void
    {
        $this->editingGoalId = null;
        $this->editName = '';
        $this->editLabel = 'general';
    }

    public function confirmEdit(): void
    {
        if ($this->editingGoalId === null) {
            return;
        }

        $name = trim($this->editName);
        if ($name === '') {
            return;
        }

        Goal::where('id', $this->editingGoalId)->update([
            'name' => $name,
            'label' => $this->editLabel,
        ]);

        $this->cancelEdit();
        $this->loadGoals();
    }

    // Archive / unarchive --------------------------------------------------

    public function archive(int $goalId): void
    {
        $goal = Goal::find($goalId);
        if (! $goal) {
            return;
        }

        try {
            $goal->update(['is_archived' => true]);
        } catch (\DomainException) {
            // Model guards against archiving the last active goal — silent
            // no-op here is fine; the UI surfaces no archive button when
            // there's only one active goal (the count from activeGoals
            // covers this).
        }

        $this->loadGoals();
    }

    public function unarchive(int $goalId): void
    {
        Goal::where('id', $goalId)->update(['is_archived' => false]);
        $this->loadGoals();
    }

    // Reorder --------------------------------------------------------------

    /**
     * Swap sort_order with the neighbour in the given direction. Reorder
     * is constrained to active goals; archived goals don't appear in
     * the chat sidebar so ordering them is wasted effort.
     */
    public function moveGoal(int $goalId, string $direction): void
    {
        $active = collect($this->activeGoals);
        $index = $active->search(fn ($g) => $g['id'] === $goalId);
        if ($index === false) {
            return;
        }

        $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;
        if ($targetIndex < 0 || $targetIndex >= $active->count()) {
            return;
        }

        $current = $active[$index];
        $target = $active[$targetIndex];

        // Persist both new sort_orders. Use raw queries to avoid the
        // creating hook on update.
        DB::table('goals')->where('id', $current['id'])->update(['sort_order' => $target['sort_order']]);
        DB::table('goals')->where('id', $target['id'])->update(['sort_order' => $current['sort_order']]);

        $this->loadGoals();
    }

    // New goal -------------------------------------------------------------

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

        $maxSortOrder = Goal::query()->max('sort_order') ?? 0;

        Goal::create([
            'name' => $name,
            'label' => $this->newGoalLabel,
            'sort_order' => $maxSortOrder + 1,
        ]);

        $this->cancelNewGoal();
        $this->loadGoals();
    }
}
