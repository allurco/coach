<?php

namespace App\Tools;

/**
 * The catalog of workspace Tools. Core registers its always-available
 * Tools (Plan, Contacts); Domain Packs self-register their own from
 * their ServiceProvider via $this->app->extend(ToolRegistry::class, …),
 * the same self-registration pattern as Tips and commands
 * (see ADR 0006 / ADR 0007). The Workspace reads this to build its tab
 * bar — it never names a pack itself.
 */
class ToolRegistry
{
    /** @var list<Tool> */
    protected array $tools = [];

    public function register(Tool ...$tools): self
    {
        foreach ($tools as $tool) {
            $this->tools[] = $tool;
        }

        return $this;
    }

    /**
     * Every registered Tool, in registration order.
     *
     * @return list<Tool>
     */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * Core Tools only — the ones every Goal has.
     *
     * @return list<Tool>
     */
    public function core(): array
    {
        return array_values(array_filter($this->tools, fn (Tool $t) => $t->isCore()));
    }

    /**
     * The Tools available to a Goal with the given pack label: core Tools
     * plus the Tools scoped to that pack. Order is preserved.
     *
     * @return list<Tool>
     */
    public function forGoalLabel(string $goalLabel): array
    {
        return array_values(array_filter($this->tools, fn (Tool $t) => $t->appliesTo($goalLabel)));
    }

    /**
     * The pack's primary Tool for a Goal label — the slot next to Chat in
     * the tab bar. Null when the active pack designates no primary (e.g.
     * 'general' Goals, which only carry core Tools).
     */
    public function primaryFor(string $goalLabel): ?Tool
    {
        foreach ($this->tools as $tool) {
            if ($tool->isPrimary && ! $tool->isCore() && $tool->scope === $goalLabel) {
                return $tool;
            }
        }

        return null;
    }
}
