<?php

namespace App\Tips;

use App\Models\Goal;
use App\Models\User;

/**
 * A single in-screen tip — a small banner the user sees nudging them
 * toward a feature or action they haven't tried yet. Each Tip is its
 * own class so adding a new one means writing a small, self-contained
 * file (predicate + copy keys) and registering it; no other surface
 * of the app changes.
 *
 * Conventions:
 *   - id() returns a stable, kebab-case slug. It doubles as the lang
 *     namespace (`coach.tips.{id}.title|prompt`) and the dismissal key.
 *   - priority() is 0-100. Higher wins ties. Roughly: 90+ = onboarding /
 *     blocking gaps, 60-89 = feature discovery, 30-59 = housekeeping,
 *     0-29 = idle nudges.
 *   - applies() runs once per page load — keep it cheap. No N+1 queries.
 */
abstract class Tip
{
    abstract public function id(): string;

    abstract public function priority(): int;

    abstract public function applies(User $user, ?Goal $goal): bool;

    /**
     * The short headline the user sees. Localized via lang keys by
     * convention; concrete tips return `__('coach.tips.<id>.title')`.
     */
    abstract public function title(): string;

    /**
     * The pre-written user message that's auto-sent into the chat
     * when the user clicks the tip. Short, first-person, in the
     * user's locale.
     */
    abstract public function prompt(): string;
}
