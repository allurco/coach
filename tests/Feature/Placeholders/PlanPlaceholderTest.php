<?php

use App\Models\Action;
use App\Models\Goal;
use App\Models\User;
use App\Placeholders\PlanPlaceholder;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->handler = new PlanPlaceholder;
});

it('renders the user’s open actions as a markdown list', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Sair do vermelho']);
    Action::create([
        'goal_id' => $goal->id,
        'title' => 'Pedir extrato banco X',
        'status' => 'pending',
        'priority' => 'high',
    ]);
    Action::create([
        'goal_id' => $goal->id,
        'title' => 'Ligar pro contador',
        'status' => 'in_progress',
        'priority' => 'medium',
    ]);

    $result = $this->handler->render($this->user->id, []);

    expect($result)
        ->toContain('Pedir extrato banco X')
        ->toContain('Ligar pro contador');
});

it('falls back when the user has no open actions', function () {
    $result = $this->handler->render($this->user->id, []);

    expect($result)->toBe((string) __('coach.placeholders.plan_empty'));
});

it('returns the empty fallback when no userId is provided', function () {
    $result = $this->handler->render(null, []);

    expect($result)->toBe((string) __('coach.placeholders.plan_empty'));
});

it('does not leak actions from another user', function () {
    $other = User::factory()->create();
    $otherGoal = Goal::withoutGlobalScope('owner')->create([
        'user_id' => $other->id,
        'label' => 'finance',
        'name' => 'Other goal',
    ]);
    Action::withoutGlobalScope('owner')->create([
        'user_id' => $other->id,
        'goal_id' => $otherGoal->id,
        'title' => 'Other user secret',
        'status' => 'pending',
    ]);

    $result = $this->handler->render($this->user->id, []);

    expect($result)
        ->not->toContain('Other user secret')
        ->toBe((string) __('coach.placeholders.plan_empty'));
});
