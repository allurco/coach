<?php

namespace App\Tips;

use App\Models\Budget;
use App\Models\Goal;
use App\Models\User;

/**
 * Surface the Budget Planner when the user is clearly in finance mode
 * but hasn't run it yet. Gated to finance goals so a fitness/learning
 * user never gets a cold push toward financial planning they didn't
 * ask for.
 */
class SetUpBudget extends Tip
{
    public function id(): string
    {
        return 'set-up-budget';
    }

    public function priority(): int
    {
        return 70;
    }

    public function applies(User $user, ?Goal $goal): bool
    {
        if ($goal === null || $goal->label !== 'finance') {
            return false;
        }

        return Budget::currentForUser($user->id) === null;
    }

    public function title(): string
    {
        return (string) __('coach.tips.set_up_budget.title');
    }

    public function prompt(): string
    {
        return (string) __('coach.tips.set_up_budget.prompt');
    }
}
