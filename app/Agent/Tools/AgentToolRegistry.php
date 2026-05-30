<?php

namespace App\Agent\Tools;

use App\Agent\Agents\CoachAgent;
use Closure;

/**
 * The catalog of agent (LLM) tools that Domain Packs contribute. Core's
 * own agent tools (CreateAction, RememberFact, WebSearch, …) remain
 * hard-coded in CoachAgent::tools() because they depend on the agent's
 * mutable state (active goal id, conversation id). Pack tools push
 * factory closures here from their ServiceProvider — same shape as
 * ToolRegistry, PlaceholderRegistry, and TipResolver (ADR 0006).
 *
 * Factories receive the CoachAgent so future stateful pack tools (e.g.
 * a hypothetical LogWorkout that needs the active goal) have access to
 * the same state core tools do. Today's pack tools are stateless and
 * just ignore the argument.
 *
 * Closes the last "Agent → Pack" import in app/Agent/Agents/CoachAgent.php.
 * See tests/Feature/Architecture/AgentLayerDoesNotImportPacksTest.php.
 */
class AgentToolRegistry
{
    /** @var list<Closure(CoachAgent): object> */
    protected array $factories = [];

    public function register(Closure $factory): self
    {
        $this->factories[] = $factory;

        return $this;
    }

    /**
     * Instantiate every contributed tool for the given agent turn.
     *
     * @return list<object>
     */
    public function build(CoachAgent $agent): array
    {
        return array_map(fn (Closure $factory) => $factory($agent), $this->factories);
    }
}
