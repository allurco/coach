<?php

use App\Filament\Pages\Coach;
use App\Models\Action;
use App\Models\CoachMemory;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

// pendingPlanCount() ---------------------------------------------------------

it('counts zero pending actions when the plan is empty', function () {
    $page = new Coach;
    $page->planActions = [];

    expect($page->pendingPlanCount())->toBe(0);
});

it('counts only pendente and em_andamento statuses', function () {
    $page = new Coach;
    $page->planActions = [
        ['status' => 'pendente'],
        ['status' => 'em_andamento'],
        ['status' => 'pendente'],
        ['status' => 'concluido'],
        ['status' => 'cancelado'],
    ];

    expect($page->pendingPlanCount())->toBe(3);
});

it('returns zero when every action is concluido', function () {
    $page = new Coach;
    $page->planActions = [
        ['status' => 'concluido'],
        ['status' => 'concluido'],
    ];

    expect($page->pendingPlanCount())->toBe(0);
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
    $this->user->forceFill(['name' => ''])->save();

    $page = new Coach;

    expect($page->userFirstName())->toBe('');
});

// suggestionsKey() -----------------------------------------------------------

it('returns coach.suggestions_first for a brand new user', function () {
    // No actions, no memories — isFirstTimer() is true.
    $page = new Coach;
    $page->planActions = [];

    expect($page->suggestionsKey())->toBe('coach.suggestions_first');
});

it('returns coach.suggestions_active when the user has a non-empty plan', function () {
    Action::create(['title' => 'Pagar boleto', 'status' => 'pendente']);
    // Seeding a memory takes the user out of first-timer territory.
    CoachMemory::create([
        'kind' => 'perfil',
        'label' => 'income',
        'summary' => 'Renda R$ 25k',
        'is_active' => true,
    ]);

    $page = new Coach;
    $page->planActions = [['status' => 'pendente']];

    expect($page->suggestionsKey())->toBe('coach.suggestions_active');
});

it('returns coach.suggestions when there is history but no current plan', function () {
    // Memory exists so isFirstTimer() is false, but planActions is empty.
    CoachMemory::create([
        'kind' => 'perfil',
        'label' => 'income',
        'summary' => 'Renda R$ 25k',
        'is_active' => true,
    ]);

    $page = new Coach;
    $page->planActions = [];

    expect($page->suggestionsKey())->toBe('coach.suggestions');
});
