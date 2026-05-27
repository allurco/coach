<?php

namespace App\Packs;

use App\Models\Goal;
use App\Models\User;
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

    /**
     * Contribute a Signal to the agent's prompt — see ADR 0002 and
     * CONTEXT.md "Signal". The agent collects every enabled pack's
     * signal on every prompt, regardless of which pack owns the
     * active goal. That's what makes the coach holistic.
     *
     * Default: return null (pack contributes nothing for this user).
     * Override in subclasses to publish a domain-specific summary.
     */
    public function contributeSignal(User $user, ?Goal $activeGoal = null): ?string
    {
        return null;
    }
}
