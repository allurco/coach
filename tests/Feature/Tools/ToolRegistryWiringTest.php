<?php

use App\Tools\ToolRegistry;
use Illuminate\Support\Facades\Lang;

it('registers core Plan and Contacts tools for any goal', function () {
    $keys = collect(app(ToolRegistry::class)->forGoalLabel('general'))->pluck('key')->all();

    expect($keys)->toContain('plan')->toContain('contacts');
});

it('lets the Finance pack contribute Budget as the finance primary', function () {
    $registry = app(ToolRegistry::class);

    expect(collect($registry->forGoalLabel('finance'))->pluck('key')->all())->toContain('budget')
        ->and($registry->primaryFor('finance')?->key)->toBe('budget');
});

it('does not surface the Budget tool on non-finance goals', function () {
    $keys = collect(app(ToolRegistry::class)->forGoalLabel('fitness'))->pluck('key')->all();

    expect($keys)->not->toContain('budget');
});

it('omits the Budget tool when the Finance pack is disabled (fork)', function () {
    // Simulate a fork with no Finance pack: drop the pack's extender (its
    // self-registration) and the resolved instance, so the registry rebuilds
    // from the Core singleton alone.
    app()->forgetExtenders(ToolRegistry::class);
    app()->forgetInstance(ToolRegistry::class);

    expect(collect(app(ToolRegistry::class)->forGoalLabel('finance'))->pluck('key')->all())
        ->toContain('plan')
        ->not->toContain('budget');
});

it('has core tool labels in both locales', function () {
    foreach (['en', 'pt_BR'] as $locale) {
        expect(Lang::has('tools.plan', $locale))->toBeTrue("missing tools.plan for {$locale}")
            ->and(Lang::has('tools.contacts', $locale))->toBeTrue("missing tools.contacts for {$locale}");
    }
});
