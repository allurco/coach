<?php

namespace App\Packs;

use Illuminate\Support\ServiceProvider;

/**
 * Base class every Domain Pack extends. A Pack is a Laravel
 * ServiceProvider plus an identity (`label()`) that matches a
 * `Goal.label` value — see ADR 0001.
 *
 * Subclasses fill in register()/boot() with their own wiring
 * (migrations, lang files, tools, models, Filament resources).
 * The shared behaviour here is: when booted, register this pack
 * with the runtime PackRegistry.
 */
abstract class DomainPack extends ServiceProvider
{
    abstract public function label(): string;

    public function boot(): void
    {
        $this->app->make(PackRegistry::class)->add($this);
    }
}
