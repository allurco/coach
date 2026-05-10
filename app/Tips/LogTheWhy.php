<?php

namespace App\Tips;

use App\Models\CoachMemory;
use App\Models\Goal;
use App\Models\User;

class LogTheWhy extends Tip
{
    public function id(): string
    {
        return 'log-the-why';
    }

    public function priority(): int
    {
        return 55;
    }

    public function applies(User $user, ?Goal $goal): bool
    {
        if ($goal === null || $goal->label === 'general') {
            return false;
        }

        return CoachMemory::query()
            ->withoutGlobalScope('owner')
            ->where('user_id', $user->id)
            ->where('goal_id', $goal->id)
            ->where('kind', 'why')
            ->where('is_active', true)
            ->doesntExist();
    }

    public function title(): string
    {
        return (string) __('coach.tips.log_the_why.title');
    }

    public function prompt(): string
    {
        return (string) __('coach.tips.log_the_why.prompt');
    }
}
