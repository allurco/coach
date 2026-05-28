<?php

namespace App\Domains\Finance\Tips;

use App\Domains\Finance\Models\Budget;
use App\Models\Goal;
use App\Models\User;
use App\Tips\Tip;

/**
 * Surface the Budget Planner when the user is clearly in finance mode
 * but hasn't run it yet. Gated to finance goals so a fitness/learning
 * user never gets a cold push toward financial planning they didn't
 * ask for.
 *
 * A pack-owned Tip: its copy lives in the pack's own lang namespace
 * (`finance::tips.*`), not Core's `coach.tips.*` (see ADR 0006).
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
        return (string) __('finance::tips.set_up_budget.title');
    }

    public function prompt(): string
    {
        return (string) __('finance::tips.set_up_budget.prompt');
    }
}
