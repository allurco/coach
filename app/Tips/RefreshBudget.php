<?php

namespace App\Tips;

use App\Models\Budget;
use App\Models\Goal;
use App\Models\User;

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
        return (string) __('coach.tips.refresh_budget.title');
    }

    public function prompt(): string
    {
        return (string) __('coach.tips.refresh_budget.prompt');
    }
}
