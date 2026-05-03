<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
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
        public ?string $conversationId = null,
    ) {}

    public function envelope(): Envelope
    {
        $emoji = match ($this->kind) {
            'morning' => '☀️',
            'weekly' => '📊',
            'stuck' => '⏳',
            default => '💬',
        };

        $envelope = new Envelope(
            subject: "{$emoji} {$this->heading}",
        );

        // Encode the conversation id into the Reply-To address using subaddressing
        // (reply+CONVID@domain). When the user replies, the inbound webhook
        // parses CONVID out of the To field and routes to the same conversation.
        if ($this->conversationId !== null) {
            $domain = config('coach.reply_domain') ?? parse_url((string) config('app.url'), PHP_URL_HOST);
            if ($domain) {
                $envelope = $envelope->replyTo([
                    new Address("reply+{$this->conversationId}@{$domain}", 'Coach'),
                ]);
            }
        }

        return $envelope;
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
