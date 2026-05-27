<?php

use App\Models\Goal;
use App\Models\User;
use App\Packs\DomainPack;

/**
 * Tests for the DomainPack base contract. The signal-contribution
 * default is the load-bearing one: packs that have nothing to say
 * for a given user/goal must be able to opt out by simply not
 * overriding contributeSignal().
 */
it('returns null from contributeSignal by default', function () {
    $pack = new FakePackWithoutSignal(app());

    $user = User::factory()->make();

    expect($pack->contributeSignal($user))->toBeNull();
});

it('passes through a custom signal when the pack overrides the method', function () {
    $pack = new FakePackWithSignal(app());

    $user = User::factory()->make();

    expect($pack->contributeSignal($user))->toBe('hello from a pack');
});

it('passes the active goal through to contributeSignal', function () {
    $pack = new FakePackEchoingGoal(app());

    $user = User::factory()->make();
    $goal = new Goal(['name' => 'My focus area']);

    expect($pack->contributeSignal($user, $goal))->toBe('My focus area');
    expect($pack->contributeSignal($user))->toBe('no goal');
});

class FakePackWithoutSignal extends DomainPack
{
    public function label(): string
    {
        return 'no-signal';
    }
}

class FakePackWithSignal extends DomainPack
{
    public function label(): string
    {
        return 'with-signal';
    }

    public function contributeSignal(User $user, ?Goal $activeGoal = null): ?string
    {
        return 'hello from a pack';
    }
}

class FakePackEchoingGoal extends DomainPack
{
    public function label(): string
    {
        return 'echo-goal';
    }

    public function contributeSignal(User $user, ?Goal $activeGoal = null): ?string
    {
        return $activeGoal?->name ?? 'no goal';
    }
}
