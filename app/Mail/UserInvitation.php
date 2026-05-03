<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $acceptUrl,
        public ?string $invitedByName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('invitation.mail.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.user-invitation',
            with: [
                'user' => $this->user,
                'acceptUrl' => $this->acceptUrl,
                'invitedByName' => $this->invitedByName,
            ],
        );
    }
}
