<?php

namespace App\Tips;

use App\Models\Action;
use App\Models\Goal;
use App\Models\User;

class RevisitDormantGoal extends Tip
{
    public function id(): string
    {
        return 'revisit-dormant-goal';
    }

    public function priority(): int
    {
        return 40;
    }

    public function applies(User $user, ?Goal $goal): bool
    {
        // Only nudge on goals old enough to be considered dormant.
        // A brand-new goal with no activity is "fresh", not "stale".
        if ($goal === null || $goal->label === 'general') {
            return false;
        }

        if ($goal->created_at->gt(now()->subDays(14))) {
            return false;
        }

        $lastTouched = Action::query()
            ->withoutGlobalScope('owner')
            ->where('user_id', $user->id)
            ->where('goal_id', $goal->id)
            ->max('updated_at');

        if ($lastTouched === null) {
            return true;
        }

        return strtotime($lastTouched) < now()->subDays(14)->getTimestamp();
    }

    public function title(): string
    {
        return (string) __('coach.tips.revisit_dormant_goal.title');
    }

    public function prompt(): string
    {
        return (string) __('coach.tips.revisit_dormant_goal.prompt');
    }
}
