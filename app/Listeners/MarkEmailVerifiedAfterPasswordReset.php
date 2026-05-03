<?php

namespace App\Listeners;

use Illuminate\Auth\Events\PasswordReset;

class MarkEmailVerifiedAfterPasswordReset
{
    /**
     * After a successful password reset, mark the email as verified if it
     * wasn't already. The user just proved they own the inbox.
     */
    public function handle(PasswordReset $event): void
    {
        $user = $event->user;

        if ($user->email_verified_at !== null) {
            return;
        }

        $user->forceFill(['email_verified_at' => now()])->save();
    }
}
