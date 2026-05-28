<?php

use App\Services\TipResolver;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Lang;

/**
 * Guards the ADR-0006 self-registration contract for the Finance pack:
 * the pack contributes its Tips, commands, and schedule into Core's
 * catalogs from its own ServiceProvider — Core never names them.
 */
it('contributes its tips into the resolved TipResolver', function () {
    $resolver = app(TipResolver::class);

    expect($resolver->find('set-up-budget'))->not->toBeNull()
        ->and($resolver->find('refresh-budget'))->not->toBeNull();
});

it('registers its console commands', function () {
    $commands = array_keys(Artisan::all());

    expect($commands)
        ->toContain('coach:carry-budget-forward')
        ->toContain('coach:monthly-budget-reminder');
});

it('schedules its console commands from the pack, not from Core', function () {
    $scheduled = collect(app(Schedule::class)->events())
        ->map(fn ($event) => $event->command)
        ->filter();

    expect($scheduled->contains(fn ($c) => str_contains($c, 'coach:carry-budget-forward')))->toBeTrue()
        ->and($scheduled->contains(fn ($c) => str_contains($c, 'coach:monthly-budget-reminder')))->toBeTrue();
});

it('owns its tip copy in the finance:: namespace, in both locales', function () {
    foreach (['en', 'pt_BR'] as $locale) {
        expect(Lang::has('finance::tips.set_up_budget.title', $locale))->toBeTrue("missing set_up_budget.title for {$locale}")
            ->and(Lang::has('finance::tips.set_up_budget.prompt', $locale))->toBeTrue("missing set_up_budget.prompt for {$locale}")
            ->and(Lang::has('finance::tips.refresh_budget.title', $locale))->toBeTrue("missing refresh_budget.title for {$locale}")
            ->and(Lang::has('finance::tips.refresh_budget.prompt', $locale))->toBeTrue("missing refresh_budget.prompt for {$locale}");
    }
});

it('no longer carries Finance tip copy in Core lang', function () {
    foreach (['en', 'pt_BR'] as $locale) {
        expect(Lang::has('coach.tips.set_up_budget.title', $locale))->toBeFalse()
            ->and(Lang::has('coach.tips.refresh_budget.title', $locale))->toBeFalse();
    }
});
