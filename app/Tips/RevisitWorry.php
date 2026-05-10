<?php

namespace App\Tips;

use App\Models\CoachMemory;
use App\Models\Goal;
use App\Models\User;

class RevisitWorry extends Tip
{
    public function id(): string
    {
        return 'revisit-worry';
    }

    public function priority(): int
    {
        return 45;
    }

    public function applies(User $user, ?Goal $goal): bool
    {
        return CoachMemory::query()
            ->withoutGlobalScope('owner')
            ->where('user_id', $user->id)
            ->where('kind', 'worry')
            ->where('created_at', '<', now()->subDays(14))
            ->exists();
    }

    public function title(): string
    {
        return (string) __('coach.tips.revisit_worry.title');
    }

    public function prompt(): string
    {
        return (string) __('coach.tips.revisit_worry.prompt');
    }
}
