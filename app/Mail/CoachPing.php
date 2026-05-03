<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CoachPing extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $kind,    // 'morning' | 'weekly' | 'stuck'
        public string $body,    // markdown content from Gemini
        public string $heading, // subject + visible heading
    ) {}

    public function envelope(): Envelope
    {
        $emoji = match ($this->kind) {
            'morning' => '☀️',
            'weekly' => '📊',
            'stuck' => '⏳',
            default => '💬',
        };

        return new Envelope(
            subject: "{$emoji} {$this->heading}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.coach-ping',
            with: [
                'kind' => $this->kind,
                'body' => $this->body,
                'heading' => $this->heading,
            ],
        );
    }
}
