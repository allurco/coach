<?php

use App\Domains\Finance\Models\Budget;
use App\Domains\Finance\Tips\RefreshBudget;
use App\Domains\Finance\Tips\SetUpBudget;
use App\Models\Goal;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

/* ------------------------------------------------------------------ *
 * SetUpBudget
 * ------------------------------------------------------------------ */

it('SetUpBudget applies on finance goal with no budget', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);

    expect((new SetUpBudget)->applies($this->user, $goal))->toBeTrue();
});

it('SetUpBudget does not apply on a non-finance goal', function () {
    $goal = Goal::create(['label' => 'fitness', 'name' => 'Fitness']);

    expect((new SetUpBudget)->applies($this->user, $goal))->toBeFalse();
});

it('SetUpBudget does not apply once a budget exists', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);
    Budget::create([
        'goal_id' => $goal->id,
        'month' => now()->format('Y-m'),
        'net_income' => 5000,
        'fixed_costs_subtotal' => 1000,
        'fixed_costs_total' => 1150,
        'investments_total' => 500,
        'savings_total' => 250,
        'leisure_amount' => 3100,
    ]);

    expect((new SetUpBudget)->applies($this->user, $goal))->toBeFalse();
});

/* ------------------------------------------------------------------ *
 * RefreshBudget
 * ------------------------------------------------------------------ */

it('RefreshBudget applies when previous month has a budget but current does not', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);
    Budget::create([
        'goal_id' => $goal->id,
        'month' => now()->subMonth()->format('Y-m'),
        'net_income' => 5000,
        'fixed_costs_subtotal' => 1000,
        'fixed_costs_total' => 1150,
        'investments_total' => 500,
        'savings_total' => 250,
        'leisure_amount' => 3100,
    ]);

    expect((new RefreshBudget)->applies($this->user, $goal))->toBeTrue();
});

it('RefreshBudget does not apply when current month already has a budget', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);
    Budget::create([
        'goal_id' => $goal->id,
        'month' => now()->format('Y-m'),
        'net_income' => 5000,
        'fixed_costs_subtotal' => 1000,
        'fixed_costs_total' => 1150,
        'investments_total' => 500,
        'savings_total' => 250,
        'leisure_amount' => 3100,
    ]);

    expect((new RefreshBudget)->applies($this->user, $goal))->toBeFalse();
});

it('RefreshBudget does not apply on non-finance goals', function () {
    $goal = Goal::create(['label' => 'fitness', 'name' => 'Fitness']);
    // even with a budget from last month
    Budget::create([
        'goal_id' => null,
        'month' => now()->subMonth()->format('Y-m'),
        'net_income' => 5000,
        'fixed_costs_subtotal' => 1000,
        'fixed_costs_total' => 1150,
        'investments_total' => 500,
        'savings_total' => 250,
        'leisure_amount' => 3100,
    ]);

    expect((new RefreshBudget)->applies($this->user, $goal))->toBeFalse();
});
