<?php

use App\Agent\Filament\Pages\Coach;
use App\Agent\Models\CoachMemory;
use App\Models\Action;
use App\Models\Goal;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

/**
 * Phase 8 — pendingPlanCount() now queries the DB directly (the chat
 * page no longer holds its own planActions array — that moved into the
 * embedded PlanFlyout Livewire component, per ADR 0005). These tests
 * seed real Action rows to exercise the query.
 */

// pendingPlanCount() ---------------------------------------------------------

it('counts zero pending actions when the plan is empty', function () {
    $page = new Coach;

    expect($page->pendingPlanCount())->toBe(0);
});

it('counts only pending and in_progress statuses', function () {
    $goal = $this->user->defaultGoal();

    Action::create(['goal_id' => $goal->id, 'title' => 'a', 'status' => 'pending']);
    Action::create(['goal_id' => $goal->id, 'title' => 'b', 'status' => 'in_progress']);
    Action::create(['goal_id' => $goal->id, 'title' => 'c', 'status' => 'pending']);
    Action::create(['goal_id' => $goal->id, 'title' => 'd', 'status' => 'completed']);
    Action::create(['goal_id' => $goal->id, 'title' => 'e', 'status' => 'cancelled']);

    $page = new Coach;
    $page->activeGoalId = $goal->id;

    expect($page->pendingPlanCount())->toBe(3);
});

it('returns zero when every action is completed', function () {
    $goal = $this->user->defaultGoal();

    Action::create(['goal_id' => $goal->id, 'title' => 'a', 'status' => 'completed']);
    Action::create(['goal_id' => $goal->id, 'title' => 'b', 'status' => 'completed']);

    $page = new Coach;
    $page->activeGoalId = $goal->id;

    expect($page->pendingPlanCount())->toBe(0);
});

it('scopes the count to the active goal', function () {
    $finance = Goal::create(['label' => 'finance', 'name' => 'Finanças']);
    $health = Goal::create(['label' => 'health', 'name' => 'Saúde']);

    Action::create(['goal_id' => $finance->id, 'title' => 'a', 'status' => 'pending']);
    Action::create(['goal_id' => $finance->id, 'title' => 'b', 'status' => 'pending']);
    Action::create(['goal_id' => $health->id, 'title' => 'c', 'status' => 'pending']);

    $page = new Coach;
    $page->activeGoalId = $finance->id;

    expect($page->pendingPlanCount())->toBe(2);
});

// userFirstName() ------------------------------------------------------------

it('returns the first word of the authenticated user name', function () {
    $this->user->update(['name' => 'Rogers Sampaio']);

    $page = new Coach;

    expect($page->userFirstName())->toBe('Rogers');
});

it('returns an empty string when no user is authenticated', function () {
    auth()->logout();

    $page = new Coach;

    expect($page->userFirstName())->toBe('');
});

it('returns an empty string when the user name is empty', function () {
    $this->user->update(['name' => '']);

    $page = new Coach;

    expect($page->userFirstName())->toBe('');
});

// suggestionsKey() -----------------------------------------------------------

it('returns coach.suggestions_first for a brand new user', function () {
    // No actions, no memories — isFirstTimer() is true.
    $page = new Coach;

    expect($page->suggestionsKey())->toBe('coach.suggestions_first');
});

it('returns coach.suggestions_active when the user has open actions', function () {
    $goal = $this->user->defaultGoal();
    Action::create(['goal_id' => $goal->id, 'title' => 'Pagar boleto', 'status' => 'pending']);
    CoachMemory::create([
        'kind' => 'perfil',
        'label' => 'income',
        'summary' => 'Renda R$ 25k',
        'is_active' => true,
    ]);

    $page = new Coach;
    $page->activeGoalId = $goal->id;

    expect($page->suggestionsKey())->toBe('coach.suggestions_active');
});

it('returns coach.suggestions when there is history but no current plan', function () {
    // Memory exists so isFirstTimer() is false, but no open actions.
    CoachMemory::create([
        'kind' => 'perfil',
        'label' => 'income',
        'summary' => 'Renda R$ 25k',
        'is_active' => true,
    ]);

    $page = new Coach;

    expect($page->suggestionsKey())->toBe('coach.suggestions');
});
