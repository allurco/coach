<?php

use App\Filament\Pages\Coach;
use App\Models\Budget;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function makeFlyoutBudget(array $overrides = []): Budget
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

// hasBudget() — drives whether the header button renders --------------------

it('hasBudget returns false for a brand new user', function () {
    $page = new Coach;

    expect($page->hasBudget())->toBeFalse();
});

it('hasBudget returns true when the user has at least one budget', function () {
    makeFlyoutBudget();
    $page = new Coach;

    expect($page->hasBudget())->toBeTrue();
});

it('hasBudget does not pick up another user\'s budget (multi-tenant)', function () {
    $intruder = User::factory()->create();
    Budget::withoutGlobalScope('owner')->create([
        'user_id' => $intruder->id,
        'goal_id' => null,
        'month' => '2026-06',
        'net_income' => 9999,
        'fixed_costs_subtotal' => 0,
        'fixed_costs_total' => 0,
        'investments_total' => 0,
        'savings_total' => 0,
        'leisure_amount' => 9999,
    ]);

    $page = new Coach;

    expect($page->hasBudget())->toBeFalse();
});

// openBudget / closeBudget --------------------------------------------------

it('openBudget loads the current budget into the flyout state', function () {
    $budget = makeFlyoutBudget();
    $page = new Coach;

    $page->openBudget();

    expect($page->budgetOpen)->toBeTrue()
        ->and($page->budgetData)->not->toBeNull()
        ->and($page->budgetData['id'])->toBe($budget->id)
        ->and($page->budgetData['month'])->toBe('2026-06')
        ->and((float) $page->budgetData['net_income'])->toBe(7200.0)
        ->and((float) $page->budgetData['leisure_amount'])->toBe(2550.0);
});

it('openBudget exposes breakdowns for all three editable buckets', function () {
    makeFlyoutBudget();
    $page = new Coach;

    $page->openBudget();

    expect($page->budgetData['fixed_costs_breakdown'])->toBe(['Aluguel' => 1800, 'Mercado' => 1200])
        ->and($page->budgetData['investments_breakdown'])->toBe(['Aposentadoria' => 720])
        ->and($page->budgetData['savings_breakdown'])->toBe(['Emergência' => 480]);
});

it('openBudget is a no-op when the user has no budget', function () {
    $page = new Coach;

    $page->openBudget();

    expect($page->budgetOpen)->toBeFalse()
        ->and($page->budgetData)->toBeNull();
});

it('closeBudget clears the flyout state', function () {
    makeFlyoutBudget();
    $page = new Coach;

    $page->openBudget();
    $page->closeBudget();

    expect($page->budgetOpen)->toBeFalse()
        ->and($page->budgetData)->toBeNull();
});

it('openBudget never returns another user\'s budget (multi-tenant)', function () {
    $intruder = User::factory()->create();
    Budget::withoutGlobalScope('owner')->create([
        'user_id' => $intruder->id,
        'goal_id' => null,
        'month' => '2026-06',
        'net_income' => 9999,
        'fixed_costs_subtotal' => 0,
        'fixed_costs_total' => 0,
        'investments_total' => 0,
        'savings_total' => 0,
        'leisure_amount' => 9999,
    ]);

    $page = new Coach;
    $page->openBudget();

    expect($page->budgetOpen)->toBeFalse()
        ->and($page->budgetData)->toBeNull();
});
