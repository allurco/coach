<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetLink extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $resetUrl,
    ) {
        $this->to($user->email);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: (string) __('passwords.mail.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.password-reset',
            with: [
                'user' => $this->user,
                'resetUrl' => $this->resetUrl,
                'expiryMinutes' => config('auth.passwords.users.expire', 60),
            ],
        );
    }
}
