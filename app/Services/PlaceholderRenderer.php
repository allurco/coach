<?php

namespace App\Services;

use App\Placeholders\PlaceholderRegistry;

/**
 * Expands `{{name[:arg[:arg]…]}}` tokens inside agent/email markdown
 * into authoritative renderings sourced from the database. The agent
 * writes prose around the tokens; data comes from registered
 * `PlaceholderHandler`s so numbers and lists never depend on the
 * model's recall.
 *
 * This class is a pure dispatcher — it knows the placeholder syntax
 * but nothing about any specific domain. Core registers handlers for
 * its own concepts (Plan) in AppServiceProvider; Domain Packs
 * self-register theirs (e.g. Finance's Budget) from their own
 * ServiceProvider via `$this->app->extend(PlaceholderRegistry::class, …)`.
 * See ADR 0006 for the contribution pattern.
 *
 * Placeholders with no registered handler pass through untouched on
 * purpose, so a mistyped token surfaces in the output instead of
 * silently dropping.
 */
class PlaceholderRenderer
{
    public function __construct(protected PlaceholderRegistry $registry) {}

    /**
     * Render every supported placeholder in $text. When $userId is
     * null we fall back to auth()->id(); pass it explicitly when
     * running outside an authenticated request (queues, mail jobs).
     */
    public function render(string $text, ?int $userId = null): string
    {
        $userId = $userId ?? auth()->id();

        return (string) preg_replace_callback(
            '/\{\{([a-z][a-z0-9_-]*)((?::[^}]*)?)\}\}/i',
            function (array $match) use ($userId): string {
                $name = $match[1];
                $rest = (string) $match[2];
                $args = $rest === '' ? [] : array_slice(explode(':', $rest), 1);

                $handler = $this->registry->handler($name);

                if ($handler === null) {
                    return $match[0];
                }

                return $handler->render($userId, $args);
            },
            $text,
        );
    }
}
