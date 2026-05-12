<?php

use App\Filament\Pages\Coach;
use App\Models\Action;
use App\Models\Budget;
use App\Models\Goal;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

// Plan list — date alignment + overdue stripe regression ----------------------

it('puts every plan-item deadline in its own grid column (alignment regression)', function () {
    // Use the user's auto-created default goal — that's what activeGoalId
    // points at after mount, so loadPlan() filters by it.
    $goalId = $this->user->defaultGoal()->id;
    Action::create(['goal_id' => $goalId, 'title' => 'Action with deadline', 'status' => 'pendente', 'deadline' => now()->addDays(5)]);
    Action::create(['goal_id' => $goalId, 'title' => 'Action without deadline', 'status' => 'pendente']);

    Livewire::test(Coach::class)
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
        'status' => 'pendente',
        'deadline' => now()->subDays(3)->toDateString(),
    ]);

    $html = (string) Livewire::test(Coach::class)->html();

    expect($html)->toContain('is-overdue');
});

it('does NOT mark non-overdue items with is-overdue', function () {
    $goalId = $this->user->defaultGoal()->id;
    Action::create([
        'goal_id' => $goalId,
        'title' => 'Not late yet',
        'status' => 'pendente',
        'deadline' => now()->addDays(10)->toDateString(),
    ]);

    $html = (string) Livewire::test(Coach::class)->html();

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

it('renders the Budget toggle button in the header when a budget exists', function () {
    makePlanLayoutBudget();

    Livewire::test(Coach::class)
        ->assertSeeHtml('budget-toggle-btn')
        ->assertSeeHtml('wire:click="openBudget"');
});

it('hides the Budget toggle button when no budget exists', function () {
    $rendered = (string) Livewire::test(Coach::class)->html();

    expect($rendered)->not->toContain('budget-toggle-btn');
});

it('opens the flyout with editable inputs bound to budgetData paths', function () {
    makePlanLayoutBudget();

    $page = Livewire::test(Coach::class)
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

    Livewire::test(Coach::class)
        ->call('openBudget')
        ->call('addBudgetLine', 'investments')
        ->call('saveBudget')
        ->assertSet('budgetOpen', true)
        ->assertNotSet('budgetData', null);

    expect(Budget::count())->toBe(2);
});

it('share modal opens with pre-filled subject and body placeholder', function () {
    makePlanLayoutBudget(['month' => '2026-06']);

    $page = Livewire::test(Coach::class)
        ->call('openBudget')
        ->call('openBudgetShare')
        ->assertSet('budgetShareOpen', true);

    expect($page->get('budgetShareSubject'))->toContain('2026-06')
        ->and($page->get('budgetShareBody'))->toContain('{{budget:current}}');
});
