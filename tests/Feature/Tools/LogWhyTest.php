<?php

use App\Ai\Tools\LogWhy;
use App\Models\CoachMemory;
use App\Models\Goal;
use App\Models\User;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('saves a why scoped to the active goal', function () {
    $finance = Goal::create(['label' => 'finance', 'name' => 'Sair do vermelho']);

    $tool = new LogWhy($finance->id);
    $result = $tool->handle(new Request([
        'why' => 'Quero dormir tranquilo sabendo que minhas contas estão em dia.',
    ]));

    $memory = CoachMemory::where('kind', 'why')->first();
    expect($memory)->not->toBeNull()
        ->and($memory->summary)->toBe('Quero dormir tranquilo sabendo que minhas contas estão em dia.')
        ->and($memory->goal_id)->toBe($finance->id)
        ->and($memory->is_active)->toBeTrue()
        ->and((string) $result)->toMatch('/salv|guarded|registr|saved/iu');
});

it('refuses an empty why', function () {
    $finance = Goal::create(['label' => 'finance', 'name' => 'Sair do vermelho']);
    $tool = new LogWhy($finance->id);

    $result = $tool->handle(new Request(['why' => '   ']));

    expect((string) $result)->toMatch('/erro|empty|vazi|error/iu')
        ->and(CoachMemory::where('kind', 'why')->count())->toBe(0);
});

it('saves without goal_id when no active goal is provided', function () {
    $tool = new LogWhy(null);
    $tool->handle(new Request(['why' => 'Pra ser livre.']));

    $memory = CoachMemory::where('kind', 'why')->first();
    expect($memory->goal_id)->toBeNull();
});

it('is scoped to the authenticated user', function () {
    $other = User::factory()->create();
    $finance = Goal::create(['label' => 'finance', 'name' => 'Mine']);

    $tool = new LogWhy($finance->id);
    $tool->handle(new Request(['why' => 'Mine']));

    expect(CoachMemory::where('summary', 'Mine')->first()->user_id)->toBe($this->user->id);

    auth()->logout();
    auth()->login($other);

    expect(CoachMemory::where('summary', 'Mine')->exists())->toBeFalse();
});
