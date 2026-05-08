<?php

use App\Filament\Pages\Coach;
use App\Models\Action;
use App\Models\CoachMemory;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('returns true for a brand new user with no plan and no memories', function () {
    $page = new Coach;

    expect($page->isFirstTimer())->toBeTrue();
});

it('returns false once the user has at least one plan action', function () {
    Action::create(['title' => 'Pagar boleto']);

    $page = new Coach;

    expect($page->isFirstTimer())->toBeFalse();
});

it('returns false once the user has at least one consolidated memory', function () {
    CoachMemory::create([
        'kind' => 'perfil',
        'label' => 'income',
        'summary' => 'Renda mensal R$ 25k',
        'is_active' => true,
    ]);

    $page = new Coach;

    expect($page->isFirstTimer())->toBeFalse();
});

it('does not consider another users plan/memories', function () {
    $other = User::factory()->create();

    Action::withoutGlobalScope('owner')->create([
        'user_id' => $other->id,
        'title' => 'Outro user',
    ]);

    $page = new Coach;

    expect($page->isFirstTimer())->toBeTrue();
});
