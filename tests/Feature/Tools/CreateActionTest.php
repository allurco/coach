<?php

use App\Ai\Tools\CreateAction;
use App\Models\Action;
use App\Models\User;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->tool = new CreateAction;
});

it('creates an action with required title only', function () {
    $result = $this->tool->handle(new Request(['title' => 'Pagar fatura']));

    expect(Action::count())->toBe(1)
        ->and($result)->toContain('Ação criada com ID');

    $action = Action::first();
    expect($action->title)->toBe('Pagar fatura')
        ->and($action->status)->toBe('pendente')
        ->and($action->category)->toBe('financeiro')
        ->and($action->priority)->toBe('media')
        ->and($action->deadline)->toBeNull();
});

it('respects all provided fields', function () {
    $this->tool->handle(new Request([
        'title' => 'Falar com contador',
        'description' => 'urgente',
        'category' => 'fiscal',
        'priority' => 'alta',
        'importance' => 'critico',
        'difficulty' => 'medio',
    ]));

    $action = Action::first();
    expect($action->category)->toBe('fiscal')
        ->and($action->priority)->toBe('alta')
        ->and($action->importance)->toBe('critico')
        ->and($action->difficulty)->toBe('medio')
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
