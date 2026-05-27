<?php

namespace App\Packs;

use Illuminate\Support\ServiceProvider;

/**
 * Bootstraps the Domain Pack system. Binds the singleton PackRegistry
 * and registers every pack class listed in config('coach.enabled_packs')
 * as a Laravel ServiceProvider.
 *
 * Each pack then handles its own register()/boot() to load its
 * migrations, lang files, tools, etc., and (via DomainPack::boot())
 * registers itself with the PackRegistry.
 */
class PackServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PackRegistry::class);

        foreach (config('coach.enabled_packs', []) as $packClass) {
            $this->app->register($packClass);
        }
    }
}
