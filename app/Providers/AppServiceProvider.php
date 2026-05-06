<?php

namespace App\Providers;

use App\Notifications\ResetPassword;
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
