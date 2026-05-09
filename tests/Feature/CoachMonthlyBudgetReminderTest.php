<?php

use App\Console\Commands\CoachMonthlyBudgetReminder;
use App\Models\Goal;
use App\Models\User;

beforeEach(function () {
    $this->command = new CoachMonthlyBudgetReminder;
});

function eligibleUsersOf(CoachMonthlyBudgetReminder $cmd): array
{
    $ref = new ReflectionMethod($cmd, 'eligibleUsers');
    $ref->setAccessible(true);

    return $ref->invoke($cmd)->all();
}

it('selects users with at least one active finance goal', function () {
    $rogers = User::factory()->create(['email' => 'r@allur.co']);
    auth()->login($rogers);
    Goal::create(['label' => 'finance', 'name' => 'Sair do vermelho']);

    auth()->logout();

    $maria = User::factory()->create(['email' => 'm@example.com']);
    auth()->login($maria);
    Goal::create(['label' => 'fitness', 'name' => 'Treinos']);

    auth()->logout();

    $eligible = eligibleUsersOf($this->command);
    $emails = collect($eligible)->pluck('email')->all();

    expect($emails)->toContain('r@allur.co')
        ->not->toContain('m@example.com');
});

it('skips users whose finance goals are all archived', function () {
    $u = User::factory()->create();
    auth()->login($u);
    Goal::create(['label' => 'fitness', 'name' => 'Active fitness']); // need another active goal first
    $finance = Goal::create(['label' => 'finance', 'name' => 'Old finance']);
    $finance->update(['is_archived' => true]);
    auth()->logout();

    $emails = collect(eligibleUsersOf($this->command))->pluck('email')->all();
    expect($emails)->not->toContain($u->email);
});

it('skips users that have not accepted their invitation yet (no password)', function () {
    $invited = User::factory()->create([
        'password' => null,
        'invitation_token' => 'pending',
    ]);
    auth()->login($invited);
    Goal::create(['label' => 'finance', 'name' => 'Finance']);
    auth()->logout();

    $emails = collect(eligibleUsersOf($this->command))->pluck('email')->all();
    expect($emails)->not->toContain($invited->email);
});

it('returns deduplicated users even with multiple finance goals', function () {
    $u = User::factory()->create();
    auth()->login($u);
    Goal::create(['label' => 'finance', 'name' => 'Sair do vermelho']);
    Goal::create(['label' => 'finance', 'name' => 'Comprar casa']);
    auth()->logout();

    $eligible = eligibleUsersOf($this->command);
    $emails = collect($eligible)->pluck('email')->all();
    $count = collect($eligible)->where('email', $u->email)->count();

    expect($count)->toBe(1)
        ->and($emails)->toContain($u->email);
});
