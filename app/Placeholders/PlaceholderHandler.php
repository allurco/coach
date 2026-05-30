<?php

namespace App\Placeholders;

/**
 * One handler per placeholder name space (the leading word before the
 * first colon — e.g. `{{budget:42}}` → handler keyed "budget"). Core
 * registers handlers for its own concepts (Plan, future Contacts);
 * Domain Packs self-register their own from their ServiceProvider via
 * `$this->app->extend(PlaceholderRegistry::class, ...)`, the same
 * pattern ToolRegistry/TipResolver use (ADR 0006).
 *
 * Handlers never see the full text — only the args that followed the
 * colon. The dispatcher (PlaceholderRenderer) handles the scan and
 * substitution; handlers only have to render one occurrence.
 */
interface PlaceholderHandler
{
    /**
     * Render one match. Returns the substitution string (markdown is
     * fine). $userId is the effective owner for owner-scoped lookups;
     * it may be null when the renderer is invoked outside an
     * authenticated context (queues, mail jobs without an explicit
     * user) — handlers must decide on a safe fallback in that case.
     *
     * @param  list<string>  $args  Whatever followed the colon, split
     *                              by colon. For `{{budget:42}}` this
     *                              is `["42"]`; for `{{plan}}` it is
     *                              an empty array.
     */
    public function render(?int $userId, array $args): string;
}
