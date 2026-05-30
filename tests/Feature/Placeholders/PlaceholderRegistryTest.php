<?php

use App\Domains\Finance\Placeholders\BudgetPlaceholder;
use App\Placeholders\PlaceholderHandler;
use App\Placeholders\PlaceholderRegistry;
use App\Placeholders\PlanPlaceholder;

it('round-trips register + handler by name', function () {
    $registry = new PlaceholderRegistry;
    $handler = new class implements PlaceholderHandler
    {
        public function render(?int $userId, array $args): string
        {
            return 'rendered';
        }
    };

    $registry->register('demo', $handler);

    expect($registry->handler('demo'))->toBe($handler);
});

it('returns null for an unregistered name', function () {
    $registry = new PlaceholderRegistry;

    expect($registry->handler('nope'))->toBeNull();
});

it('returns Finance BudgetPlaceholder for "budget" after pack boot', function () {
    /** @var PlaceholderRegistry $registry */
    $registry = app(PlaceholderRegistry::class);

    expect($registry->handler('budget'))->toBeInstanceOf(BudgetPlaceholder::class);
});

it('returns core PlanPlaceholder for "plan" after core boot', function () {
    /** @var PlaceholderRegistry $registry */
    $registry = app(PlaceholderRegistry::class);

    expect($registry->handler('plan'))->toBeInstanceOf(PlanPlaceholder::class);
});

it('is bound as a container singleton', function () {
    $a = app(PlaceholderRegistry::class);
    $b = app(PlaceholderRegistry::class);

    expect($a)->toBe($b);
});
