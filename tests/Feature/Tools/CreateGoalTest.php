<?php

use App\Ai\Tools\CreateGoal;
use App\Models\Goal;
use App\Models\User;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->tool = new CreateGoal;
});

it('creates a goal with name and label', function () {
    $result = $this->tool->handle(new Request([
        'name' => 'Voltar a treinar',
        'label' => 'fitness',
    ]));

    $goal = Goal::where('name', 'Voltar a treinar')->first();
    expect($goal)->not->toBeNull()
        ->and($goal->label)->toBe('fitness')
        ->and($goal->is_archived)->toBeFalse()
        ->and($goal->user_id)->toBe($this->user->id)
        ->and((string) $result)->toContain('Voltar a treinar');
});

it('defaults label to general when not provided', function () {
    $this->tool->handle(new Request(['name' => 'Algum lance']));

    $goal = Goal::where('name', 'Algum lance')->first();
    expect($goal->label)->toBe('general');
});

it('defaults label to general when invalid label given', function () {
    $this->tool->handle(new Request([
        'name' => 'Aprender a fazer pão',
        'label' => 'cooking',
    ]));

    $goal = Goal::where('name', 'Aprender a fazer pão')->first();
    expect($goal->label)->toBe('general');
});

it('refuses to create when name is empty and reports the failure', function () {
    $countBefore = Goal::count();

    $result = $this->tool->handle(new Request(['name' => '   ']));

    expect(Goal::count())->toBe($countBefore)
        ->and((string) $result)->toMatch('/erro|error|nome|name/i');
});

it('accepts every built-in label', function (string $label) {
    $this->tool->handle(new Request([
        'name' => "Goal {$label}",
        'label' => $label,
    ]));

    expect(Goal::where('name', "Goal {$label}")->first()->label)->toBe($label);
})->with(['general', 'finance', 'legal', 'emotional', 'health', 'fitness', 'learning']);

it('is scoped to the authenticated user (no cross-tenant leak)', function () {
    $other = User::factory()->create();

    $this->tool->handle(new Request([
        'name' => 'Mine',
        'label' => 'finance',
    ]));

    expect(Goal::where('name', 'Mine')->first()->user_id)->toBe($this->user->id);

    auth()->logout();
    auth()->login($other);

    expect(Goal::where('name', 'Mine')->exists())->toBeFalse(); // global scope blocks
});
