<?php

use App\Ai\Tools\CreateAction;
use App\Models\Action;
use App\Models\Goal;
use App\Models\User;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->tool = new CreateAction;
});

it('stamps the active goal id when one is passed in', function () {
    $fitness = Goal::create(['label' => 'fitness', 'name' => 'Fitness']);

    $tool = new CreateAction($fitness->id);
    $tool->handle(new Request(['title' => 'Caminhada 30min']));

    $action = Action::where('title', 'Caminhada 30min')->first();
    expect($action->goal_id)->toBe($fitness->id);
});

it('falls back to the user default goal when no active goal is provided', function () {
    // The user already has a default Goal from the UserObserver. With no
    // goal id passed to the tool, Action::creating fills it via
    // User::defaultGoal() — this is the legacy behaviour that the fix
    // must keep intact for cron/email flows.
    $defaultGoal = $this->user->defaultGoal();

    $this->tool->handle(new Request(['title' => 'Sem goal explícito']));

    $action = Action::where('title', 'Sem goal explícito')->first();
    expect($action->goal_id)->toBe($defaultGoal->id);
});

it('creates an action with required title only', function () {
    $result = $this->tool->handle(new Request(['title' => 'Pagar fatura']));

    expect(Action::count())->toBe(1)
        ->and($result)->toContain('Action created with ID');

    $action = Action::first();
    expect($action->title)->toBe('Pagar fatura')
        ->and($action->status)->toBe('pending')
        ->and($action->category)->toBe('financial')
        ->and($action->priority)->toBe('medium')
        ->and($action->deadline)->toBeNull();
});

it('respects all provided fields', function () {
    $this->tool->handle(new Request([
        'title' => 'Falar com contador',
        'description' => 'urgente',
        'category' => 'tax',
        'priority' => 'high',
        'importance' => 'critical',
        'difficulty' => 'medium',
    ]));

    $action = Action::first();
    expect($action->category)->toBe('tax')
        ->and($action->priority)->toBe('high')
        ->and($action->importance)->toBe('critical')
        ->and($action->difficulty)->toBe('medium')
        ->and($action->description)->toBe('urgente');
});

it('parses relative deadline shorthand: 1d', function () {
    $this->tool->handle(new Request(['title' => 'X', 'deadline' => '1d']));

    expect(Action::first()->deadline->toDateString())
        ->toBe(now()->addDay()->toDateString());
});

it('parses relative deadline shorthand: 2w', function () {
    $this->tool->handle(new Request(['title' => 'X', 'deadline' => '2w']));

    expect(Action::first()->deadline->toDateString())
        ->toBe(now()->addWeeks(2)->toDateString());
});

it('parses relative deadline shorthand: 1m', function () {
    $this->tool->handle(new Request(['title' => 'X', 'deadline' => '1m']));

    expect(Action::first()->deadline->toDateString())
        ->toBe(now()->addMonth()->toDateString());
});

it('parses keyword deadline: hoje', function () {
    $this->tool->handle(new Request(['title' => 'X', 'deadline' => 'hoje']));

    expect(Action::first()->deadline->toDateString())
        ->toBe(now()->toDateString());
});

it('parses keyword deadline: amanhã', function () {
    $this->tool->handle(new Request(['title' => 'X', 'deadline' => 'amanhã']));

    expect(Action::first()->deadline->toDateString())
        ->toBe(now()->addDay()->toDateString());
});

it('parses Brazilian date format DD/MM/YYYY', function () {
    $this->tool->handle(new Request(['title' => 'X', 'deadline' => '15/05/2026']));

    expect(Action::first()->deadline->toDateString())->toBe('2026-05-15');
});

it('parses ISO date format YYYY-MM-DD', function () {
    $this->tool->handle(new Request(['title' => 'X', 'deadline' => '2026-05-15']));

    expect(Action::first()->deadline->toDateString())->toBe('2026-05-15');
});

it('returns null for unparseable deadline', function () {
    $this->tool->handle(new Request(['title' => 'X', 'deadline' => 'gibberish-xyz']));

    expect(Action::first()->deadline)->toBeNull();
});
