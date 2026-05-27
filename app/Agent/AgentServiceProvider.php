<?php

namespace App\Agent;

use Illuminate\Support\ServiceProvider;

/**
 * The Agent layer — see ADR 0003.
 *
 * Phase 1 (this PR): only registers the config flag and short-circuits
 * if disabled. No agent code has moved here yet — CoachAgent, tools,
 * Conversation, Memory, the webhook controller, and the chat page all
 * still live under app/Ai, app/Models, app/Services, app/Http, and
 * app/Filament. They move in Phase 3.
 */
class AgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Phase 3: bind agent services into the container.
    }

    public function boot(): void
    {
        if (! config('coach.agent.enabled', true)) {
            return;
        }

        // Phase 3: register the webhook route, the chat page, the tool
        // registry, and the prompt builder.
    }
}
