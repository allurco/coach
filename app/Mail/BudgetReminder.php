<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BudgetReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $kind,            // 'recurring' | 'intro'
        public string $body,            // markdown body from the agent
        public ?string $conversationId = null,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->kind === 'recurring'
            ? '💰 '.__('coach.budget_reminder.subject_recurring')
            : '💰 '.__('coach.budget_reminder.subject_intro');

        $envelope = new Envelope(subject: $subject);

        // Reply-To with subaddressing so the user can answer this email
        // and the inbound webhook routes the reply back into the same
        // finance conversation. Same pattern used by CoachPing.
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
            markdown: 'mail.budget-reminder',
            with: [
                'body' => $this->body,
                'kind' => $this->kind,
            ],
        );
    }
}
