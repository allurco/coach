<?php

use App\Models\Action;
use App\Models\Budget;
use App\Models\CoachMemory;
use App\Models\Goal;
use App\Models\User;
use App\Tips\AddFirstAction;
use App\Tips\AddSecondGoal;
use App\Tips\LogFirstWin;
use App\Tips\LogTheWhy;
use App\Tips\PickFocusArea;
use App\Tips\RefreshBudget;
use App\Tips\ReviewOverdue;
use App\Tips\RevisitDormantGoal;
use App\Tips\RevisitWorry;
use App\Tips\SetUpBudget;
use App\Tips\TrimHeavyPlan;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

/* ------------------------------------------------------------------ *
 * PickFocusArea
 * ------------------------------------------------------------------ */

it('PickFocusArea applies when user has no real goals', function () {
    expect((new PickFocusArea)->applies($this->user, null))->toBeTrue();

    Goal::create(['label' => 'general', 'name' => 'Geral']);
    expect((new PickFocusArea)->applies($this->user, null))->toBeTrue();
});

it('PickFocusArea does not apply when user has a real goal', function () {
    Goal::create(['label' => 'finance', 'name' => 'Finance']);

    expect((new PickFocusArea)->applies($this->user, null))->toBeFalse();
});

/* ------------------------------------------------------------------ *
 * AddFirstAction
 * ------------------------------------------------------------------ */

it('AddFirstAction applies when an active non-general goal has no actions', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);

    expect((new AddFirstAction)->applies($this->user, $goal))->toBeTrue();
});

it('AddFirstAction does not apply once any action exists in the goal', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);
    Action::create(['goal_id' => $goal->id, 'title' => 'X', 'status' => 'pending']);

    expect((new AddFirstAction)->applies($this->user, $goal))->toBeFalse();
});

it('AddFirstAction does not apply on a general placeholder goal', function () {
    $goal = Goal::create(['label' => 'general', 'name' => 'Geral']);

    expect((new AddFirstAction)->applies($this->user, $goal))->toBeFalse();
});

/* ------------------------------------------------------------------ *
 * ReviewOverdue
 * ------------------------------------------------------------------ */

it('ReviewOverdue applies when an open action is overdue by 3+ days', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);
    Action::create([
        'goal_id' => $goal->id,
        'title' => 'old',
        'status' => 'pending',
        'deadline' => now()->subDays(5),
    ]);

    expect((new ReviewOverdue)->applies($this->user, $goal))->toBeTrue();
});

it('ReviewOverdue does not apply for actions overdue less than 3 days', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);
    Action::create([
        'goal_id' => $goal->id,
        'title' => 'recent',
        'status' => 'pending',
        'deadline' => now()->subDays(1),
    ]);

    expect((new ReviewOverdue)->applies($this->user, $goal))->toBeFalse();
});

it('ReviewOverdue does not see another user’s overdue action', function () {
    $other = User::factory()->create();
    $otherGoal = Goal::withoutGlobalScope('owner')->create([
        'user_id' => $other->id,
        'label' => 'finance',
        'name' => 'their finance',
    ]);
    Action::withoutGlobalScope('owner')->create([
        'user_id' => $other->id,
        'goal_id' => $otherGoal->id,
        'title' => 'their old',
        'status' => 'pending',
        'deadline' => now()->subDays(30),
    ]);

    expect((new ReviewOverdue)->applies($this->user, null))->toBeFalse();
});

/* ------------------------------------------------------------------ *
 * LogFirstWin
 * ------------------------------------------------------------------ */

it('LogFirstWin applies when there’s a concluded action with no result_notes', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);
    Action::create([
        'goal_id' => $goal->id,
        'title' => 'done',
        'status' => 'completed',
        'result_notes' => null,
    ]);

    expect((new LogFirstWin)->applies($this->user, $goal))->toBeTrue();
});

it('LogFirstWin does not apply once result_notes is filled', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);
    Action::create([
        'goal_id' => $goal->id,
        'title' => 'done',
        'status' => 'completed',
        'result_notes' => 'how it went',
    ]);

    expect((new LogFirstWin)->applies($this->user, $goal))->toBeFalse();
});

/* ------------------------------------------------------------------ *
 * TrimHeavyPlan
 * ------------------------------------------------------------------ */

it('TrimHeavyPlan applies when open action count >= threshold', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);
    for ($i = 0; $i < 10; $i++) {
        Action::create([
            'goal_id' => $goal->id,
            'title' => "a{$i}",
            'status' => 'pending',
        ]);
    }

    expect((new TrimHeavyPlan)->applies($this->user, $goal))->toBeTrue();
});

it('TrimHeavyPlan does not apply below threshold', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);
    for ($i = 0; $i < 9; $i++) {
        Action::create([
            'goal_id' => $goal->id,
            'title' => "a{$i}",
            'status' => 'pending',
        ]);
    }

    expect((new TrimHeavyPlan)->applies($this->user, $goal))->toBeFalse();
});

/* ------------------------------------------------------------------ *
 * AddSecondGoal
 * ------------------------------------------------------------------ */

it('AddSecondGoal applies for exactly one real goal aged 7+ days', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);
    $goal->forceFill(['created_at' => now()->subDays(10)])->save();

    expect((new AddSecondGoal)->applies($this->user, $goal))->toBeTrue();
});

it('AddSecondGoal does not apply for a brand-new goal', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);

    expect((new AddSecondGoal)->applies($this->user, $goal))->toBeFalse();
});

it('AddSecondGoal does not apply when the user already has two goals', function () {
    $first = Goal::create(['label' => 'finance', 'name' => 'Finance']);
    $first->forceFill(['created_at' => now()->subDays(10)])->save();
    Goal::create(['label' => 'fitness', 'name' => 'Fitness']);

    expect((new AddSecondGoal)->applies($this->user, $first))->toBeFalse();
});

/* ------------------------------------------------------------------ *
 * RevisitDormantGoal
 * ------------------------------------------------------------------ */

it('RevisitDormantGoal applies when goal is old + no recent action activity', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);
    $goal->forceFill(['created_at' => now()->subDays(30)])->save();

    expect((new RevisitDormantGoal)->applies($this->user, $goal))->toBeTrue();
});

it('RevisitDormantGoal does not apply for a fresh goal', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);

    expect((new RevisitDormantGoal)->applies($this->user, $goal))->toBeFalse();
});

it('RevisitDormantGoal does not apply when actions were touched recently', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);
    $goal->forceFill(['created_at' => now()->subDays(30)])->save();
    Action::create(['goal_id' => $goal->id, 'title' => 'recent', 'status' => 'pending']);

    expect((new RevisitDormantGoal)->applies($this->user, $goal))->toBeFalse();
});

/* ------------------------------------------------------------------ *
 * LogTheWhy
 * ------------------------------------------------------------------ */

it('LogTheWhy applies when active goal has no why memory', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);

    expect((new LogTheWhy)->applies($this->user, $goal))->toBeTrue();
});

it('LogTheWhy does not apply once a why exists for that goal', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);
    CoachMemory::create([
        'kind' => 'why',
        'label' => 'why',
        'summary' => 'my why',
        'goal_id' => $goal->id,
        'is_active' => true,
    ]);

    expect((new LogTheWhy)->applies($this->user, $goal))->toBeFalse();
});

it('LogTheWhy does not see why memories from another user', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);
    $other = User::factory()->create();
    CoachMemory::withoutGlobalScope('owner')->create([
        'user_id' => $other->id,
        'kind' => 'why',
        'label' => 'why',
        'summary' => 'their why',
        'goal_id' => $goal->id,
        'is_active' => true,
    ]);

    expect((new LogTheWhy)->applies($this->user, $goal))->toBeTrue();
});

/* ------------------------------------------------------------------ *
 * RevisitWorry
 * ------------------------------------------------------------------ */

it('RevisitWorry applies when there’s a worry older than 14 days', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);
    $worry = CoachMemory::create([
        'kind' => 'worry',
        'label' => 'worry',
        'summary' => 'old worry',
        'goal_id' => $goal->id,
        'is_active' => true,
    ]);
    $worry->forceFill(['created_at' => now()->subDays(20)])->save();

    expect((new RevisitWorry)->applies($this->user, null))->toBeTrue();
});

it('RevisitWorry does not apply for a fresh worry', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);
    CoachMemory::create([
        'kind' => 'worry',
        'label' => 'worry',
        'summary' => 'fresh worry',
        'goal_id' => $goal->id,
        'is_active' => true,
    ]);

    expect((new RevisitWorry)->applies($this->user, null))->toBeFalse();
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
