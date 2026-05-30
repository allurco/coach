<?php

use App\Domains\Finance\Models\Budget;
use App\Domains\Finance\Placeholders\BudgetPlaceholder;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->handler = new BudgetPlaceholder;
});

function makeBudgetForHandler(array $overrides = []): Budget
{
    return Budget::create(array_merge([
        'goal_id' => null,
        'month' => now()->format('Y-m'),
        'net_income' => 7200,
        'fixed_costs_subtotal' => 4000,
        'fixed_costs_total' => 4000,
        'investments_total' => 720,
        'savings_total' => 480,
        'leisure_amount' => 2000,
    ], $overrides));
}

it('renders {{budget:N}} into the snapshot markdown when given a numeric arg', function () {
    $budget = makeBudgetForHandler(['leisure_amount' => 600]);

    $result = $this->handler->render($this->user->id, [(string) $budget->id]);

    expect($result)->toContain('Plano de Gastos');
});

it('falls back when {{budget:N}} references a missing snapshot', function () {
    $result = $this->handler->render($this->user->id, ['99999']);

    expect($result)->toBe((string) __('coach.placeholders.budget_missing'));
});

it('renders {{budget:current}} using the user’s latest budget', function () {
    makeBudgetForHandler(['month' => '2026-04', 'leisure_amount' => -100]);
    makeBudgetForHandler(['month' => '2026-05', 'leisure_amount' => 600]);

    $result = $this->handler->render($this->user->id, ['current']);

    expect($result)->toContain('Plano de Gastos');
});

it('falls back when {{budget:current}} has no data', function () {
    $result = $this->handler->render($this->user->id, ['current']);

    expect($result)->toBe((string) __('coach.placeholders.budget_missing'));
});

it('returns missing when {{budget:current}} is called without a user', function () {
    $result = $this->handler->render(null, ['current']);

    expect($result)->toBe((string) __('coach.placeholders.budget_missing'));
});
