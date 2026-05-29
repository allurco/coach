<?php

use App\Agent\Filament\Pages\Coach;
use App\Domains\Finance\Livewire\BudgetTool;
use App\Domains\Finance\Models\Budget;
use App\Livewire\PlanFlyout;
use App\Models\Action;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

// Plan list — date alignment + overdue stripe regression ----------------------

// The plan list is rendered by the PlanFlyout component (embedded in the
// Workspace). Target it directly so these layout regressions don't depend
// on the Coach landing flow (ADR 0007).
it('puts every plan-item deadline in its own grid column (alignment regression)', function () {
    $goalId = $this->user->defaultGoal()->id;
    Action::create(['goal_id' => $goalId, 'title' => 'Action with deadline', 'status' => 'pending', 'deadline' => now()->addDays(5)]);
    Action::create(['goal_id' => $goalId, 'title' => 'Action without deadline', 'status' => 'pending']);

    Livewire::test(PlanFlyout::class, ['activeGoalId' => $goalId])
        ->assertSeeHtmlInOrder([
            'plan-item-main',
            'plan-item-deadline-col',
        ]);
});

it('marks overdue plan-items with the is-overdue class for the left-edge stripe', function () {
    $goalId = $this->user->defaultGoal()->id;
    Action::create([
        'goal_id' => $goalId,
        'title' => 'Late on this',
        'status' => 'pending',
        'deadline' => now()->subDays(3)->toDateString(),
    ]);

    $html = (string) Livewire::test(PlanFlyout::class, ['activeGoalId' => $goalId])->html();

    expect($html)->toContain('is-overdue');
});

it('does NOT mark non-overdue items with is-overdue', function () {
    $goalId = $this->user->defaultGoal()->id;
    Action::create([
        'goal_id' => $goalId,
        'title' => 'Not late yet',
        'status' => 'pending',
        'deadline' => now()->addDays(10)->toDateString(),
    ]);

    $html = (string) Livewire::test(PlanFlyout::class, ['activeGoalId' => $goalId])->html();

    expect($html)->not->toContain('is-overdue');
});

// Budget flyout — end-to-end through Livewire (state + rendered HTML) ----------

function makePlanLayoutBudget(array $overrides = []): Budget
{
    return Budget::create(array_merge([
        'goal_id' => null,
        'month' => '2026-06',
        'net_income' => 7200,
        'fixed_costs_subtotal' => 3000,
        'fixed_costs_total' => 3450,
        'fixed_costs_breakdown' => ['Aluguel' => 1800, 'Mercado' => 1200],
        'investments_total' => 720,
        'investments_breakdown' => ['Aposentadoria' => 720],
        'savings_total' => 480,
        'savings_breakdown' => ['Emergência' => 480],
        'leisure_amount' => 2550,
    ], $overrides));
}

// BudgetTool is now embedded in the Workspace tool rail (ADR 0007): it
// auto-hydrates on mount and renders the editor body inline (no toggle
// button — Budget launches from the tab bar). Unit coverage of every
// method is in tests/Feature/Finance/BudgetToolTest.
it('renders the budget editor inline (embedded) when a budget exists', function () {
    makePlanLayoutBudget();

    Livewire::test(BudgetTool::class)
        ->assertSet('budgetData.month', '2026-06')
        ->assertSeeHtml('budget-flyout-body')
        ->assertSeeHtml('wire:model.live.debounce.400ms="budgetData.net_income"')
        ->assertDontSeeHtml('budget-toggle-btn');
});

it('shows the empty editor body when the user has no budget', function () {
    $rendered = (string) Livewire::test(BudgetTool::class)->html();

    expect($rendered)
        ->toContain('budget-flyout-body')
        ->not->toContain('budget-toggle-btn');
});

it('opens the flyout with editable inputs bound to budgetData paths', function () {
    makePlanLayoutBudget();

    $page = Livewire::test(BudgetTool::class)
        ->call('openBudget')
        ->assertSet('budgetOpen', true)
        ->assertSet('budgetData.month', '2026-06');

    $html = (string) $page->html();
    // The recalcable cells must be bound, not static text.
    expect($html)
        ->toContain('wire:model.live.debounce.400ms="budgetData.net_income"')
        ->toContain('wire:model.live.debounce.400ms="budgetData.fixed_costs_lines.0.label"')
        ->toContain('wire:model.live.debounce.400ms="budgetData.fixed_costs_lines.0.amount"');
});

it('full edit cycle: open → add line → save creates a new snapshot', function () {
    makePlanLayoutBudget();

    Livewire::test(BudgetTool::class)
        ->call('openBudget')
        ->call('addBudgetLine', 'investments')
        ->call('saveBudget')
        ->assertSet('budgetOpen', true)
        ->assertNotSet('budgetData', null);

    expect(Budget::count())->toBe(2);
});

it('share modal opens with pre-filled subject and body placeholder', function () {
    makePlanLayoutBudget(['month' => '2026-06']);

    $page = Livewire::test(BudgetTool::class)
        ->call('openBudget')
        ->call('openBudgetShare')
        ->assertSet('budgetShareOpen', true);

    expect($page->get('budgetShareSubject'))->toContain('2026-06')
        ->and($page->get('budgetShareBody'))->toContain('{{budget:current}}');
});
