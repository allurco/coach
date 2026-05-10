<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class Share extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $emailSubject  Subject line, supplied by the agent.
     *                                Renamed to avoid colliding with the
     *                                parent Mailable's untyped $subject
     *                                property — envelope() copies it
     *                                across, so $mail->subject still
     *                                holds the right value at assert time.
     * @param  string  $body  Markdown body with placeholders already
     *                        expanded by PlaceholderRenderer.
     * @param  string  $senderName  Name of the user doing the sharing.
     */
    public function __construct(
        public string $emailSubject,
        public string $body,
        public string $senderName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.share',
            with: [
                'body' => $this->body,
                'senderName' => $this->senderName,
            ],
        );
    }
}
