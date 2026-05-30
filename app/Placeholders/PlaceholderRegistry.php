<?php

namespace App\Placeholders;

/**
 * The catalog of `{{name[:arg…]}}` handlers. Core registers handlers
 * for its own concepts (Plan); Domain Packs self-register theirs from
 * their ServiceProvider via `$this->app->extend(PlaceholderRegistry::class, …)`,
 * the same self-registration pattern ToolRegistry and TipResolver use
 * (ADR 0006). PlaceholderRenderer dispatches against this registry —
 * Core never has to import a pack to render a snapshot.
 */
class PlaceholderRegistry
{
    /** @var array<string, PlaceholderHandler> */
    protected array $handlers = [];

    public function register(string $name, PlaceholderHandler $handler): self
    {
        $this->handlers[$name] = $handler;

        return $this;
    }

    public function handler(string $name): ?PlaceholderHandler
    {
        return $this->handlers[$name] ?? null;
    }
}
