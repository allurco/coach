<?php

namespace App\Providers;

use App\Notifications\ResetPassword;
use App\Services\TipResolver;
use App\Tips\AddFirstAction;
use App\Tips\AddSecondGoal;
use App\Tips\LogFirstWin;
use App\Tips\LogTheWhy;
use App\Tips\PickFocusArea;
use App\Tips\ReviewOverdue;
use App\Tips\RevisitDormantGoal;
use App\Tips\RevisitWorry;
use App\Tips\TrimHeavyPlan;
use App\Tools\Tool;
use App\Tools\ToolRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Replace Filament's default password-reset notification with our
        // branded version (Coach. layout + pt_BR/en translations). Filament
        // resolves the parent class via the container, so binding here is
        // enough — no other call sites need to change.
        $this->app->bind(
            \Filament\Auth\Notifications\ResetPassword::class,
            ResetPassword::class,
        );

        // Core tip catalog — adding a Core tip is just appending a class
        // here and shipping its lang keys. Order doesn't matter; the
        // resolver sorts by priority(). Domain Packs contribute their own
        // tips by extending this singleton from their ServiceProvider
        // (see ADR 0006) — Core never lists pack-owned tips.
        $this->app->singleton(TipResolver::class, fn () => new TipResolver([
            new PickFocusArea,
            new AddFirstAction,
            new ReviewOverdue,
            new TrimHeavyPlan,
            new LogTheWhy,
            new LogFirstWin,
            new RevisitWorry,
            new RevisitDormantGoal,
            new AddSecondGoal,
        ]));

        // Core Tool catalog — the workspace surfaces every Goal has. Domain
        // Packs contribute their own Tools by extending this singleton from
        // their ServiceProvider (see ADR 0006/0007); Core never lists pack
        // Tools. Each `component` is a Livewire alias the Workspace mounts.
        $this->app->singleton(ToolRegistry::class, fn () => (new ToolRegistry)->register(
            new Tool(key: 'plan', label: 'tools.plan', icon: 'heroicon-o-clipboard-document-list', component: 'plan-flyout', scope: 'core'),
            new Tool(key: 'contacts', label: 'tools.contacts', icon: 'heroicon-o-users', component: 'contacts-tool', scope: 'core'),
        ));
    }

    public function boot(): void
    {
        $this->configureDefaults();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Surface N+1 queries during development by throwing on any lazy
        // relationship load. Disabled in production — there a missed eager
        // load should slow the page, not break it for the user.
        Model::preventLazyLoading(! app()->isProduction());

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
