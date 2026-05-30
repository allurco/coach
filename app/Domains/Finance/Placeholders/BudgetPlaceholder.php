<?php

namespace App\Domains\Finance\Placeholders;

use App\Domains\Finance\Models\Budget;
use App\Domains\Finance\Tools\BudgetSnapshot;
use App\Placeholders\PlaceholderHandler;

/**
 * Finance-pack handler for `{{budget:N}}` and `{{budget:current}}`.
 *
 *  - Numeric arg (`{{budget:42}}`) renders snapshot #42, bypassing the
 *    owner global scope so historical messages stay legible even if
 *    the agent's auth context has shifted.
 *  - The literal "current" arg (`{{budget:current}}`) resolves to the
 *    user's most recent budget for whoever owns the surrounding text.
 *
 * Either form falls back to `coach.placeholders.budget_missing` when
 * the snapshot can't be found, so a deleted row leaves a visible
 * marker instead of silently disappearing.
 */
class BudgetPlaceholder implements PlaceholderHandler
{
    public function render(?int $userId, array $args): string
    {
        $arg = $args[0] ?? null;

        $budget = match (true) {
            $arg === 'current' => $userId ? Budget::currentForUser($userId) : null,
            is_string($arg) && ctype_digit($arg) => Budget::withoutGlobalScope('owner')->find((int) $arg),
            default => null,
        };

        return $budget
            ? (new BudgetSnapshot)->renderForBudget($budget)
            : (string) __('coach.placeholders.budget_missing');
    }
}
