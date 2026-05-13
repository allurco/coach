<?php

use App\Ai\Tools\CreateAction;
use App\Ai\Tools\ListActions;
use App\Ai\Tools\RecallFacts;
use App\Ai\Tools\RememberFact;
use App\Ai\Tools\UpdateAction;
use App\Models\Action;
use App\Models\CoachMemory;
use App\Models\User;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->alice = User::factory()->create(['email' => 'alice@example.com']);
    $this->bob = User::factory()->create(['email' => 'bob@example.com']);
});

it('auto-fills user_id from auth on create', function () {
    $this->actingAs($this->alice);

    (new CreateAction)->handle(new Request(['title' => 'Alice action']));

    $action = Action::withoutGlobalScope('owner')->first();
    expect($action->user_id)->toBe($this->alice->id);
});

it('global scope hides other users actions in queries', function () {
    Action::withoutGlobalScope('owner')->create([
        'user_id' => $this->bob->id,
        'title' => 'Bob private action',
        'status' => 'pending',
    ]);

    $this->actingAs($this->alice);
    expect(Action::count())->toBe(0)
        ->and(Action::all())->toBeEmpty();
});

it('ListActions tool only returns actions for the current user', function () {
    Action::withoutGlobalScope('owner')->create([
        'user_id' => $this->alice->id,
        'title' => 'Alice action',
        'status' => 'pending',
    ]);
    Action::withoutGlobalScope('owner')->create([
        'user_id' => $this->bob->id,
        'title' => 'Bob action',
        'status' => 'pending',
    ]);

    $this->actingAs($this->alice);
    $output = (string) (new ListActions)->handle(new Request);

    expect($output)
        ->toContain('Alice action')
        ->not->toContain('Bob action');
});

it('UpdateAction tool refuses to update another users action', function () {
    $bobsAction = Action::withoutGlobalScope('owner')->create([
        'user_id' => $this->bob->id,
        'title' => 'Bob action',
        'status' => 'pending',
    ]);

    $this->actingAs($this->alice);
    $result = (string) (new UpdateAction)->handle(new Request([
        'id' => $bobsAction->id,
        'status' => 'completed',
    ]));

    expect($result)->toContain('not found');

    // And Bob's action stays untouched.
    expect($bobsAction->fresh()->status)->toBe('pending');
});

it('Action::find on someone elses action returns null', function () {
    $bobsAction = Action::withoutGlobalScope('owner')->create([
        'user_id' => $this->bob->id,
        'title' => 'Bob action',
        'status' => 'pending',
    ]);

    $this->actingAs($this->alice);

    expect(Action::find($bobsAction->id))->toBeNull();
});

it('global scope is bypassed for unauthenticated contexts', function () {
    Action::withoutGlobalScope('owner')->create([
        'user_id' => $this->alice->id,
        'title' => 'A',
        'status' => 'pending',
    ]);
    Action::withoutGlobalScope('owner')->create([
        'user_id' => $this->bob->id,
        'title' => 'B',
        'status' => 'pending',
    ]);

    // No auth — scope should not filter; both rows visible.
    expect(Action::count())->toBe(2);
});

// ========== Memory isolation ==========

it('CoachMemory global scope hides other users memories', function () {
    CoachMemory::withoutGlobalScope('owner')->create([
        'user_id' => $this->bob->id,
        'kind' => 'fato',
        'label' => 'Bob fact',
        'summary' => 'Private to Bob',
    ]);

    $this->actingAs($this->alice);
    expect(CoachMemory::count())->toBe(0);
});

it('RememberFact tool auto-fills user_id from auth', function () {
    $this->actingAs($this->alice);

    (new RememberFact)->handle(new Request([
        'kind' => 'fato',
        'label' => 'Renda',
        'summary' => 'Alice ganha 10K',
    ]));

    $memory = CoachMemory::withoutGlobalScope('owner')->first();
    expect($memory->user_id)->toBe($this->alice->id);
});

it('RecallFacts tool only returns memories for the current user', function () {
    CoachMemory::withoutGlobalScope('owner')->create([
        'user_id' => $this->alice->id,
        'kind' => 'fato',
        'label' => 'Alice fact',
        'summary' => 'Alice info',
        'is_active' => true,
    ]);
    CoachMemory::withoutGlobalScope('owner')->create([
        'user_id' => $this->bob->id,
        'kind' => 'fato',
        'label' => 'Bob fact',
        'summary' => 'Bob info',
        'is_active' => true,
    ]);

    $this->actingAs($this->alice);
    $output = (string) (new RecallFacts)->handle(new Request);

    expect($output)
        ->toContain('Alice')
        ->not->toContain('Bob');
});
