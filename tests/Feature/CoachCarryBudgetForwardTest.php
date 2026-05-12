<?php

use App\Models\Budget;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function makeCarryBudget(array $overrides = []): Budget
{
    return Budget::create(array_merge([
        'goal_id' => null,
        'month' => now()->format('Y-m'),
        'net_income' => 7200,
        'fixed_costs_subtotal' => 3000,
        'fixed_costs_total' => 3450,
        'fixed_costs_breakdown' => ['Aluguel' => 1800, 'Mercado' => 1200],
        'investments_total' => 720,
        'investments_breakdown' => ['Aposentadoria' => 720],
        'savings_total' => 480,
        'savings_breakdown' => ['Emergência' => 480],
        'leisure_amount' => 2550,
        'notes' => 'mês de teste',
    ], $overrides));
}

it('copies the current budget into next month with identical bucket values', function () {
    Carbon::setTestNow('2026-05-28 06:00:00');

    $current = makeCarryBudget(['month' => '2026-05']);

    $this->artisan('coach:carry-budget-forward')->assertSuccessful();

    $next = Budget::where('user_id', $this->user->id)
        ->where('month', '2026-06')
        ->first();

    expect($next)->not->toBeNull()
        ->and((float) $next->net_income)->toBe((float) $current->net_income)
        ->and((float) $next->fixed_costs_total)->toBe((float) $current->fixed_costs_total)
        ->and($next->fixed_costs_breakdown)->toEqual($current->fixed_costs_breakdown)
        ->and((float) $next->investments_total)->toBe((float) $current->investments_total)
        ->and((float) $next->savings_total)->toBe((float) $current->savings_total)
        ->and((float) $next->leisure_amount)->toBe((float) $current->leisure_amount);
});

it('resets notes on the carried-forward snapshot — the previous month\'s context does not apply', function () {
    Carbon::setTestNow('2026-05-28 06:00:00');

    makeCarryBudget(['month' => '2026-05', 'notes' => 'specific to May']);

    $this->artisan('coach:carry-budget-forward')->assertSuccessful();

    $next = Budget::where('user_id', $this->user->id)->where('month', '2026-06')->first();
    expect($next->notes)->toBeNull();
});

it('is idempotent — running twice does not duplicate the next month snapshot', function () {
    Carbon::setTestNow('2026-05-28 06:00:00');

    makeCarryBudget(['month' => '2026-05']);

    $this->artisan('coach:carry-budget-forward')->assertSuccessful();
    $this->artisan('coach:carry-budget-forward')->assertSuccessful();

    $count = Budget::where('user_id', $this->user->id)->where('month', '2026-06')->count();
    expect($count)->toBe(1);
});

it('skips users who already have a budget for next month (manual or otherwise)', function () {
    Carbon::setTestNow('2026-05-28 06:00:00');

    makeCarryBudget(['month' => '2026-05', 'net_income' => 5000]);
    makeCarryBudget(['month' => '2026-06', 'net_income' => 9999]);

    $this->artisan('coach:carry-budget-forward')->assertSuccessful();

    $next = Budget::where('user_id', $this->user->id)->where('month', '2026-06')->first();
    expect((float) $next->net_income)->toBe(9999.0);
});

it('skips users with no budget at all', function () {
    Carbon::setTestNow('2026-05-28 06:00:00');

    User::factory()->create();

    $this->artisan('coach:carry-budget-forward')->assertSuccessful();

    expect(Budget::withoutGlobalScope('owner')->count())->toBe(0);
});

it('carries forward the latest budget even when the current month has no snapshot', function () {
    Carbon::setTestNow('2026-05-28 06:00:00');

    makeCarryBudget(['month' => '2026-04', 'net_income' => 6500]);

    $this->artisan('coach:carry-budget-forward')->assertSuccessful();

    $next = Budget::where('user_id', $this->user->id)->where('month', '2026-06')->first();
    expect($next)->not->toBeNull()
        ->and((float) $next->net_income)->toBe(6500.0);
});

it('does not carry one user\'s budget into another user (multi-tenant)', function () {
    Carbon::setTestNow('2026-05-28 06:00:00');

    $intruder = User::factory()->create();
    Budget::withoutGlobalScope('owner')->create([
        'user_id' => $intruder->id,
        'goal_id' => null,
        'month' => '2026-05',
        'net_income' => 9999,
        'fixed_costs_subtotal' => 0,
        'fixed_costs_total' => 0,
        'investments_total' => 0,
        'savings_total' => 0,
        'leisure_amount' => 9999,
    ]);

    makeCarryBudget(['month' => '2026-05', 'net_income' => 5000]);

    $this->artisan('coach:carry-budget-forward')->assertSuccessful();

    $mine = Budget::where('user_id', $this->user->id)->where('month', '2026-06')->first();
    expect((float) $mine->net_income)->toBe(5000.0);

    $theirs = Budget::withoutGlobalScope('owner')
        ->where('user_id', $intruder->id)
        ->where('month', '2026-06')
        ->first();
    expect((float) $theirs->net_income)->toBe(9999.0);
});

it('preserves goal_id from the source budget', function () {
    Carbon::setTestNow('2026-05-28 06:00:00');

    $goal = Goal::create(['label' => 'finance', 'name' => 'Vida financeira']);
    makeCarryBudget(['month' => '2026-05', 'goal_id' => $goal->id]);

    $this->artisan('coach:carry-budget-forward')->assertSuccessful();

    $next = Budget::where('user_id', $this->user->id)->where('month', '2026-06')->first();
    expect($next->goal_id)->toBe($goal->id);
});
