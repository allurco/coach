<?php

namespace App\Tips;

use App\Models\Goal;
use App\Models\User;

/**
 * The cold-start nudge — user has no goals yet, or only the synthetic
 * "general" placeholder. Surfaces before anything else so the rest of
 * the catalog has a meaningful goal context to gate against.
 */
class PickFocusArea extends Tip
{
    public function id(): string
    {
        return 'pick-focus-area';
    }

    public function priority(): int
    {
        return 95;
    }

    public function applies(User $user, ?Goal $goal): bool
    {
        $real = Goal::query()
            ->withoutGlobalScope('owner')
            ->where('user_id', $user->id)
            ->where('label', '!=', 'general')
            ->where('is_archived', false)
            ->exists();

        return ! $real;
    }

    public function title(): string
    {
        return (string) __('coach.tips.pick_focus_area.title');
    }

    public function prompt(): string
    {
        return (string) __('coach.tips.pick_focus_area.prompt');
    }
}
