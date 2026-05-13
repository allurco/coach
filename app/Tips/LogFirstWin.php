<?php

namespace App\Tips;

use App\Models\Action;
use App\Models\Goal;
use App\Models\User;

class LogFirstWin extends Tip
{
    public function id(): string
    {
        return 'log-first-win';
    }

    public function priority(): int
    {
        return 50;
    }

    public function applies(User $user, ?Goal $goal): bool
    {
        return Action::query()
            ->withoutGlobalScope('owner')
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereNull('result_notes')
            ->exists();
    }

    public function title(): string
    {
        return (string) __('coach.tips.log_first_win.title');
    }

    public function prompt(): string
    {
        return (string) __('coach.tips.log_first_win.prompt');
    }
}
