<?php

namespace App\Filament\Pages;

use App\Models\Goal;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * The Plan tool — the user's full-screen view of their actions across
 * all goals (or filtered to one). Renders the same PlanFlyout Livewire
 * component the chat sidebar uses, but at full width with a goal picker
 * (see ADR 0005 for why the shared UI lives in app/Livewire/).
 *
 * Listed under the "Tool Box" navigation group alongside future tools
 * (Goals, Contacts, Budget from Finance pack, etc.).
 */
class Plan extends Page
{
    protected string $view = 'filament.pages.plan';

    protected static ?string $slug = 'plan';

    protected static ?string $navigationLabel = 'Plan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Tool Box';

    protected static ?int $navigationSort = 1;

    /**
     * The current goal-picker selection. null = "All goals". The
     * embedded PlanFlyout listens for plan-refreshed events to keep its
     * view in sync.
     */
    public ?int $selectedGoalId = null;

    public array $goalOptions = [];

    public function mount(): void
    {
        $this->loadGoalOptions();
    }

    public function updatedSelectedGoalId(): void
    {
        // Forward the picker change into the embedded component so it
        // reloads against the new goal scope.
        $this->dispatch('plan-refreshed', activeGoalId: $this->selectedGoalId);
    }

    /**
     * Goals available in the picker. Includes "All goals" (null) plus
     * each non-archived goal. Archived goals are excluded — they're not
     * useful planning targets and would clutter the picker.
     */
    protected function loadGoalOptions(): void
    {
        $goals = Goal::query()
            ->where('is_archived', false)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'label']);

        $this->goalOptions = $goals->map(fn (Goal $g) => [
            'id' => $g->id,
            'name' => $g->name,
            'label' => $g->label,
        ])->all();
    }

    public function getHeading(): string
    {
        return (string) __('coach.plan_flyout.eyebrow');
    }
}
