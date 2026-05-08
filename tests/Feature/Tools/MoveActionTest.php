<?php

use App\Ai\Tools\MoveAction;
use App\Models\Action;
use App\Models\Goal;
use App\Models\User;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->tool = new MoveAction;
});

it('moves an action to a different goal owned by the user', function () {
    $finance = Goal::create(['label' => 'finance', 'name' => 'Vida financeira']);
    $fitness = Goal::create(['label' => 'fitness', 'name' => 'Treinos']);

    $action = Action::create([
        'title' => 'Caminhada 30min',
        'goal_id' => $fitness->id,
    ]);

    $result = $this->tool->handle(new Request([
        'action_id' => $action->id,
        'goal_id' => $finance->id,
    ]));

    expect($action->fresh()->goal_id)->toBe($finance->id)
        ->and((string) $result)->toMatch('/movida|moved/iu');
});

it('refuses when the action does not exist', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Test']);

    $result = $this->tool->handle(new Request([
        'action_id' => 99999,
        'goal_id' => $goal->id,
    ]));

    expect((string) $result)->toMatch('/erro|n[ãa]o encontr|not found|error/iu');
});

it('refuses when the target goal does not exist', function () {
    $action = Action::create(['title' => 'X']);

    $result = $this->tool->handle(new Request([
        'action_id' => $action->id,
        'goal_id' => 99999,
    ]));

    expect((string) $result)->toMatch('/erro|n[ãa]o encontr|not found|error/iu')
        ->and($action->fresh()->goal_id)->not->toBe(99999);
});

it('refuses when the target goal is archived', function () {
    $other = Goal::create(['label' => 'finance', 'name' => 'Active backup']);
    $archived = Goal::create(['label' => 'fitness', 'name' => 'Old']);
    $archived->update(['is_archived' => true]);

    $action = Action::create(['title' => 'X']);

    $result = $this->tool->handle(new Request([
        'action_id' => $action->id,
        'goal_id' => $archived->id,
    ]));

    expect((string) $result)->toMatch('/arquivad|archived|erro|error/iu');
});

it('does not allow moving an action belonging to another user', function () {
    $other = User::factory()->create();
    $otherGoal = Goal::withoutGlobalScope('owner')->create([
        'user_id' => $other->id,
        'label' => 'finance',
        'name' => 'Other goal',
    ]);
    $otherAction = Action::withoutGlobalScope('owner')->create([
        'user_id' => $other->id,
        'goal_id' => $otherGoal->id,
        'title' => 'Other action',
    ]);

    $myGoal = Goal::create(['label' => 'finance', 'name' => 'Mine']);

    $result = $this->tool->handle(new Request([
        'action_id' => $otherAction->id,
        'goal_id' => $myGoal->id,
    ]));

    expect((string) $result)->toMatch('/erro|n[ãa]o encontr|not found|error/iu')
        ->and($otherAction->fresh()->goal_id)->toBe($otherGoal->id);
});

it('does not allow moving to another users goal', function () {
    $other = User::factory()->create();
    $otherGoal = Goal::withoutGlobalScope('owner')->create([
        'user_id' => $other->id,
        'label' => 'finance',
        'name' => 'Other goal',
    ]);

    $action = Action::create(['title' => 'X']);
    $originalGoalId = $action->goal_id;

    $result = $this->tool->handle(new Request([
        'action_id' => $action->id,
        'goal_id' => $otherGoal->id,
    ]));

    expect((string) $result)->toMatch('/erro|n[ãa]o encontr|not found|error/iu')
        ->and($action->fresh()->goal_id)->toBe($originalGoalId);
});
