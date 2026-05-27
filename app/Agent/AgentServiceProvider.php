<?php

namespace App\Agent;

use Illuminate\Support\ServiceProvider;

/**
 * The Agent layer — see ADR 0003.
 *
 * Owns:
 *  - CoachAgent + the tool registry (app/Agent/Tools/)
 *  - AgentConversation + CoachMemory models (app/Agent/Models/)
 *  - CoachReplyProcessor + EmailReplyParser (app/Agent/Services/)
 *  - CoachWebhookController + the /webhooks/coach-email route
 *
 * Gating: when `coach.agent.enabled` is false (env COACH_AGENT_ENABLED=false),
 * boot() short-circuits before loading any of the agent's external surface
 * (currently the webhook route). The Filament chat page is still discovered
 * by the panel today — moving it under app/Agent and gating its registration
 * here is Candidate 1 / a later phase.
 */
class AgentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! config('coach.agent.enabled', true)) {
            return;
        }

        $this->loadRoutesFrom(__DIR__.'/routes.php');
    }
}
