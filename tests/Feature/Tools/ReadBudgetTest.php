<?php

use App\Ai\Tools\BudgetSnapshot;
use App\Ai\Tools\ReadBudget;
use App\Models\Budget;
use App\Models\User;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->tool = new ReadBudget;
});

it('returns the {{budget:current}} placeholder when the user has a budget', function () {
    Budget::create([
        'goal_id' => null,
        'month' => '2026-06',
        'net_income' => 24000,
        'fixed_costs_subtotal' => 10000,
        'fixed_costs_total' => 11500,
        'investments_total' => 2400,
        'savings_total' => 1200,
        'leisure_amount' => 8900,
    ]);

    $result = (string) $this->tool->handle(new Request([]));

    expect($result)->toBe('{{budget:current}}');
});

it('expanded placeholder renders the full markdown via PlaceholderRenderer', function () {
    $budget = Budget::create([
        'goal_id' => null,
        'month' => '2026-06',
        'net_income' => 24000,
        'fixed_costs_subtotal' => 10000,
        'fixed_costs_total' => 11500,
        'investments_total' => 2400,
        'savings_total' => 1200,
        'leisure_amount' => 8900,
    ]);

    $result = (string) $this->tool->handle(new Request([]));
    $rendered = BudgetSnapshot::expandPlaceholders($result);

    expect($rendered)
        ->toContain('Custos Fixos')
        ->toContain('Lazer')
        ->toContain('snapshot #'.$budget->id);
});

it('returns a "no budget" message when the user has none', function () {
    $result = (string) $this->tool->handle(new Request([]));

    expect($result)->toMatch('/sem|none|no budget/iu');
});

it('returns "no budget" when the only budgets belong to another user', function () {
    $intruder = User::factory()->create();
    Budget::withoutGlobalScope('owner')->create([
        'user_id' => $intruder->id,
        'goal_id' => null,
        'month' => '2026-06',
        'net_income' => 99999,
        'fixed_costs_subtotal' => 0,
        'fixed_costs_total' => 0,
        'investments_total' => 0,
        'savings_total' => 0,
        'leisure_amount' => 99999,
    ]);

    $result = (string) $this->tool->handle(new Request([]));

    expect($result)->toMatch('/sem|none|no budget/iu')
        ->and($result)->not->toContain('99999')
        ->and($result)->not->toContain('99.999');
});

it('returns an unauth message when no user is logged in', function () {
    auth()->logout();

    $result = (string) $this->tool->handle(new Request([]));

    expect($result)->toMatch('/aut|usu|user|login/iu');
});
