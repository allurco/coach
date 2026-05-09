<?php

use App\Ai\Tools\LogWorry;
use App\Models\CoachMemory;
use App\Models\Goal;
use App\Models\User;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('saves a worry as a kind=worry memory', function () {
    $finance = Goal::create(['label' => 'finance', 'name' => 'Sair do vermelho']);

    $tool = new LogWorry($finance->id);
    $result = $tool->handle(new Request([
        'worry' => 'E se eu perder o cliente principal antes de quitar a fatura?',
        'topic' => 'cliente principal',
    ]));

    $memory = CoachMemory::where('kind', 'worry')->first();
    expect($memory)->not->toBeNull()
        ->and($memory->summary)->toBe('E se eu perder o cliente principal antes de quitar a fatura?')
        ->and($memory->label)->toBe('cliente principal')
        ->and($memory->goal_id)->toBe($finance->id)
        ->and($memory->is_active)->toBeTrue()
        ->and((string) $result)->toMatch('/registr|salv|logged|saved/iu');
});

it('records the logged_at date so we can revisit later', function () {
    $tool = new LogWorry(null);
    $tool->handle(new Request([
        'worry' => 'Vou conseguir bater a meta?',
        'topic' => 'meta',
    ]));

    $memory = CoachMemory::where('kind', 'worry')->first();
    expect($memory->event_date?->format('Y-m-d'))->toBe(now()->format('Y-m-d'));
});

it('refuses an empty worry', function () {
    $tool = new LogWorry(null);
    $result = $tool->handle(new Request(['worry' => '   ', 'topic' => 'algo']));

    expect((string) $result)->toMatch('/erro|empty|vazi|error/iu')
        ->and(CoachMemory::where('kind', 'worry')->count())->toBe(0);
});

it('defaults the topic to "geral" when not given', function () {
    $tool = new LogWorry(null);
    $tool->handle(new Request(['worry' => 'Tô com medo de não dar conta.']));

    expect(CoachMemory::where('kind', 'worry')->first()->label)->toBe('geral');
});

it('is scoped to the authenticated user', function () {
    $other = User::factory()->create();

    $tool = new LogWorry(null);
    $tool->handle(new Request(['worry' => 'Mine', 'topic' => 't']));

    expect(CoachMemory::where('summary', 'Mine')->first()->user_id)->toBe($this->user->id);

    auth()->logout();
    auth()->login($other);

    expect(CoachMemory::where('summary', 'Mine')->exists())->toBeFalse();
});
