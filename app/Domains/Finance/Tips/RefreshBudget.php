<?php

namespace App\Domains\Finance\Tips;

use App\Domains\Finance\Models\Budget;
use App\Models\Goal;
use App\Models\User;
use App\Tips\Tip;

/**
 * Nudge the user to refresh the current month's budget when they have
 * last month's snapshot but none for this month yet. Gated to finance
 * goals. Copy lives in the pack's `finance::tips.*` namespace (ADR 0006).
 */
class RefreshBudget extends Tip
{
    public function id(): string
    {
        return 'refresh-budget';
    }

    public function priority(): int
    {
        return 60;
    }

    public function applies(User $user, ?Goal $goal): bool
    {
        if ($goal === null || $goal->label !== 'finance') {
            return false;
        }

        $currentMonth = now()->format('Y-m');
        $previousMonth = now()->subMonth()->format('Y-m');

        $hasCurrent = Budget::query()
            ->withoutGlobalScope('owner')
            ->where('user_id', $user->id)
            ->where('month', $currentMonth)
            ->exists();

        if ($hasCurrent) {
            return false;
        }

        return Budget::query()
            ->withoutGlobalScope('owner')
            ->where('user_id', $user->id)
            ->where('month', $previousMonth)
            ->exists();
    }

    public function title(): string
    {
        return (string) __('finance::tips.refresh_budget.title');
    }

    public function prompt(): string
    {
        return (string) __('finance::tips.refresh_budget.prompt');
    }
}
