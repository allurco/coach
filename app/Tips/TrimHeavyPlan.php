<?php

namespace App\Tips;

use App\Models\Action;
use App\Models\Goal;
use App\Models\User;

class TrimHeavyPlan extends Tip
{
    public const OPEN_ACTION_THRESHOLD = 10;

    public function id(): string
    {
        return 'trim-heavy-plan';
    }

    public function priority(): int
    {
        return 65;
    }

    public function applies(User $user, ?Goal $goal): bool
    {
        return Action::query()
            ->withoutGlobalScope('owner')
            ->where('user_id', $user->id)
            ->whereIn('status', ['pendente', 'em_andamento'])
            ->count() >= self::OPEN_ACTION_THRESHOLD;
    }

    public function title(): string
    {
        return (string) __('coach.tips.trim_heavy_plan.title');
    }

    public function prompt(): string
    {
        return (string) __('coach.tips.trim_heavy_plan.prompt');
    }
}
