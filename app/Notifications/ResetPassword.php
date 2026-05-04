<?php

namespace App\Notifications;

use Filament\Auth\Notifications\ResetPassword as FilamentResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Replaces Filament's default ResetPassword notification with one that
 * renders the email through our markdown template + translations. Keeps
 * the parent's ShouldQueue + Queueable behavior, so delivery still goes
 * through the queue.
 *
 * Wired up via container binding in AppServiceProvider so anywhere
 * Filament resolves the parent class, ours is built instead.
 */
class ResetPassword extends FilamentResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $expiryMinutes = config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject((string) __('passwords.mail.subject'))
            ->markdown('mail.password-reset', [
                'user' => $notifiable,
                'resetUrl' => $this->url,
                'expiryMinutes' => $expiryMinutes,
            ]);
    }
}
