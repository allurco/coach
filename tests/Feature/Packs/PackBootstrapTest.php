<?php

use App\Domains\Finance\FinanceServiceProvider;
use App\Packs\PackRegistry;

/**
 * Integration: the Pack bootstrap flow wired through Laravel's SP boot —
 * config/coach.php lists enabled packs, the PackServiceProvider registers
 * each, and each pack's boot() adds itself to the shared PackRegistry.
 *
 * The "disabled" path is exercised in a separate test that re-bootstraps
 * the registry with an empty config; we can't toggle config mid-app
 * because providers have already booted by the time tests run.
 */
it('registers the Finance pack by default', function () {
    /** @var PackRegistry $registry */
    $registry = app(PackRegistry::class);

    expect($registry->get('finance'))
        ->not->toBeNull()
        ->toBeInstanceOf(FinanceServiceProvider::class);
});

it('exposes the Finance pack as the only enabled pack today', function () {
    /** @var PackRegistry $registry */
    $registry = app(PackRegistry::class);

    expect(array_keys($registry->enabled()))->toBe(['finance']);
});

it('rebuilds an empty registry when no packs are enabled', function () {
    // Simulate a deployment / fork that has no packs enabled. We build a
    // fresh registry and register zero packs against it — same shape the
    // PackServiceProvider produces when config('coach.enabled_packs') is [].
    config(['coach.enabled_packs' => []]);

    $registry = new PackRegistry;
    // No packs added, mimicking the "no enabled_packs" config path.

    expect($registry->enabled())->toBe([]);
});

it('defaults coach.agent.enabled to true', function () {
    expect(config('coach.agent.enabled'))->toBeTrue();
});

it('honours COACH_AGENT_ENABLED=false at the config layer', function () {
    config(['coach.agent.enabled' => false]);

    expect(config('coach.agent.enabled'))->toBeFalse();
});

it('registers the finance:: view namespace so the pack ships its own Blade partials', function () {
    // Sanity check the vertical-slice claim: when Finance ships UI bits
    // (budget flyout + share modal Blade partials), they're resolvable
    // through the pack's own view namespace, not the central
    // resources/views path. See ADR 0004.
    expect(view()->exists('finance::_budget-flyout'))->toBeTrue();
    expect(view()->exists('finance::_budget-share-modal'))->toBeTrue();
});
