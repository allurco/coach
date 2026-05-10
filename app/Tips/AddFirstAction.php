<?php

namespace App\Tips;

use App\Models\Action;
use App\Models\Goal;
use App\Models\User;

class AddFirstAction extends Tip
{
    public function id(): string
    {
        return 'add-first-action';
    }

    public function priority(): int
    {
        return 80;
    }

    public function applies(User $user, ?Goal $goal): bool
    {
        if ($goal === null || $goal->label === 'general') {
            return false;
        }

        return Action::query()
            ->withoutGlobalScope('owner')
            ->where('user_id', $user->id)
            ->where('goal_id', $goal->id)
            ->doesntExist();
    }

    public function title(): string
    {
        return (string) __('coach.tips.add_first_action.title');
    }

    public function prompt(): string
    {
        return (string) __('coach.tips.add_first_action.prompt');
    }
}
