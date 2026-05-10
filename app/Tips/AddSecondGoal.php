<?php

namespace App\Tips;

use App\Models\Goal;
use App\Models\User;

class AddSecondGoal extends Tip
{
    public function id(): string
    {
        return 'add-second-goal';
    }

    public function priority(): int
    {
        return 30;
    }

    public function applies(User $user, ?Goal $goal): bool
    {
        $realGoals = Goal::query()
            ->withoutGlobalScope('owner')
            ->where('user_id', $user->id)
            ->where('label', '!=', 'general')
            ->where('is_archived', false)
            ->get();

        if ($realGoals->count() !== 1) {
            return false;
        }

        return $realGoals->first()->created_at->lt(now()->subDays(7));
    }

    public function title(): string
    {
        return (string) __('coach.tips.add_second_goal.title');
    }

    public function prompt(): string
    {
        return (string) __('coach.tips.add_second_goal.prompt');
    }
}
