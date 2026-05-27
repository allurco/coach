<?php

use App\Packs\DomainPack;
use App\Packs\PackRegistry;

/**
 * Pure-logic tests for the PackRegistry — no Laravel container.
 * Integration with config/SP-boot is exercised in
 * tests/Feature/Packs/PackBootstrapTest.php.
 */
it('starts empty', function () {
    $registry = new PackRegistry;

    expect($registry->enabled())->toBe([]);
});

it('indexes added packs by their label', function () {
    $registry = new PackRegistry;
    $finance = new FakeFinancePack(app());

    $registry->add($finance);

    expect($registry->enabled())->toHaveKey('finance');
    expect($registry->enabled()['finance'])->toBe($finance);
});

it('returns the matching pack by label', function () {
    $registry = new PackRegistry;
    $finance = new FakeFinancePack(app());

    $registry->add($finance);

    expect($registry->get('finance'))->toBe($finance);
});

it('returns null for an unknown label', function () {
    $registry = new PackRegistry;

    expect($registry->get('health'))->toBeNull();
});

it('replaces a pack registered twice with the same label', function () {
    $registry = new PackRegistry;
    $first = new FakeFinancePack(app());
    $second = new FakeFinancePack(app());

    $registry->add($first);
    $registry->add($second);

    expect($registry->enabled())->toHaveCount(1);
    expect($registry->get('finance'))->toBe($second);
});

/**
 * Minimal DomainPack double for these unit tests so we don't depend on
 * the real Finance pack's wiring.
 */
class FakeFinancePack extends DomainPack
{
    public function label(): string
    {
        return 'finance';
    }
}
