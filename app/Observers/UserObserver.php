<?php

namespace App\Observers;

use App\Models\Goal;
use App\Models\User;

class UserObserver
{
    /**
     * Every new user gets a default Goal so the rest of the app (actions,
     * conversations, memories) always has a workspace to attach to. Label
     * starts as 'general' — the user can rename or create more later.
     */
    public function created(User $user): void
    {
        Goal::withoutGlobalScope('owner')->create([
            'user_id' => $user->id,
            'label' => 'general',
            'name' => 'Geral',
            'sort_order' => 0,
            'is_archived' => false,
        ]);
    }
}
