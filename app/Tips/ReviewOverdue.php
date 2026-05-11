<?php

namespace App\Tips;

use App\Models\Action;
use App\Models\Goal;
use App\Models\User;

class ReviewOverdue extends Tip
{
    public function id(): string
    {
        return 'review-overdue';
    }

    public function priority(): int
    {
        return 75;
    }

    public function applies(User $user, ?Goal $goal): bool
    {
        return Action::query()
            ->withoutGlobalScope('owner')
            ->where('user_id', $user->id)
            ->whereIn('status', Action::OPEN_STATUSES)
            ->whereDate('deadline', '<', now()->subDays(3)->toDateString())
            ->exists();
    }

    public function title(): string
    {
        return (string) __('coach.tips.review_overdue.title');
    }

    public function prompt(): string
    {
        return (string) __('coach.tips.review_overdue.prompt');
    }
}
