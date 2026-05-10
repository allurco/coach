<?php

namespace App\Services;

use App\Models\Goal;
use App\Models\User;
use App\Tips\Tip;

/**
 * Picks the single highest-priority Tip the user should see right
 * now. Pure: no database fallback, no auth() side-effects — the
 * caller passes everything in. Tips themselves do the lookups in
 * their applies() predicate.
 *
 * @phpstan-type DismissedList list<string>
 */
class TipResolver
{
    /**
     * @param  array<int,Tip>  $tips  The registered tip catalog. Order
     *                                doesn't matter — pick() sorts by
     *                                priority.
     */
    public function __construct(protected array $tips) {}

    /**
     * Returns the tip with the highest priority among the ones that
     * apply to (user, goal) and haven't been dismissed. Null when no
     * tip qualifies.
     *
     * @param  DismissedList  $dismissed  Tip ids the user has dismissed
     *                                    in the current session.
     */
    public function pick(User $user, ?Goal $goal, array $dismissed = []): ?Tip
    {
        $candidates = [];

        foreach ($this->tips as $tip) {
            if (in_array($tip->id(), $dismissed, true)) {
                continue;
            }

            if (! $tip->applies($user, $goal)) {
                continue;
            }

            $candidates[] = $tip;
        }

        usort($candidates, fn (Tip $a, Tip $b) => $b->priority() <=> $a->priority());

        return $candidates[0] ?? null;
    }

    /**
     * Look up a registered tip by id — used when reacting to a user
     * click where we already know the id and just need the prompt.
     * Independent of applies()/dismissed state.
     */
    public function find(string $id): ?Tip
    {
        foreach ($this->tips as $tip) {
            if ($tip->id() === $id) {
                return $tip;
            }
        }

        return null;
    }
}
