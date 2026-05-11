<?php

namespace App\Ai\Tools;

use App\Mail\Share;
use App\Models\Contact;
use App\Services\PlaceholderRenderer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ShareViaEmail implements Tool
{
    /**
     * Per-user cap on outbound shares. External email is the kind of
     * action where one bug = ten emails to the wrong person; the cap
     * keeps the tail risk bounded without blocking real-world use
     * (a monthly email to an accountant won't trip 5/hour).
     */
    public const MAX_PER_HOUR = 5;

    public function description(): Stringable|string
    {
        return 'Envia por email o que o usuário quiser compartilhar com terceiros — '
            .'contador, parceiro, advogado, etc. O agente escreve o corpo em markdown '
            .'e pode usar placeholders pra dados estruturados: '
            .'`{{budget:current}}` (último orçamento do usuário), '
            .'`{{budget:N}}` (snapshot específico), `{{plan}}` (ações em curso). '
            .'Cada destinatário pode ser um email literal OU o label de um Contact salvo. '
            .'O usuário recebe BCC automático pra ter cópia. CONFIRMAR DESTINATÁRIO '
            .'E ASSUNTO COM O USUÁRIO ANTES DE CHAMAR.';
    }

    public function handle(Request $request): Stringable|string
    {
        $userId = auth()->id();
        if (! $userId) {
            return (string) __('coach.share.errors.unauthenticated');
        }

        $body = trim((string) ($request['body'] ?? ''));
        if ($body === '') {
            return (string) __('coach.share.errors.empty_body');
        }

        $subject = trim((string) ($request['subject'] ?? '')) ?: (string) __('coach.share.default_subject');

        // Resolve the primary recipient first — fail fast so the rate
        // limiter doesn't tick up on bad input.
        $to = $this->resolveAddress((string) ($request['to'] ?? ''), $userId);
        if ($to === null) {
            return (string) __('coach.share.errors.unknown_recipient', [
                'value' => (string) ($request['to'] ?? ''),
            ]);
        }

        $cc = $this->resolveAddressList($request['cc'] ?? [], $userId);
        $bcc = $this->resolveAddressList($request['bcc'] ?? [], $userId);

        $key = 'share-via-email:'.$userId;
        if (RateLimiter::tooManyAttempts($key, self::MAX_PER_HOUR)) {
            return (string) __('coach.share.errors.rate_limited', [
                'minutes' => (int) ceil(RateLimiter::availableIn($key) / 60),
            ]);
        }
        RateLimiter::hit($key, 3600);

        $user = auth()->user();
        $expandedBody = (new PlaceholderRenderer)->render($body, $userId);

        // Auto-BCC the user so they always get a copy of what was sent
        // out under their name. Defensive; near-zero cost.
        $bcc[] = $user?->email ?? '';
        $bcc = array_values(array_unique(array_filter($bcc)));

        Mail::to($to)
            ->cc($cc)
            ->bcc($bcc)
            ->send(new Share(
                emailSubject: $subject,
                body: $expandedBody,
                senderName: $user?->name ?? 'Coach',
            ));

        return (string) __('coach.share.success', ['email' => $to]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'to' => $schema->string()->required()
                ->description('Email literal OU label de um Contact salvo (ex: "contador").'),
            'cc' => $schema->array()
                ->description('Lista de emails ou labels para CC.'),
            'bcc' => $schema->array()
                ->description('Lista de emails ou labels para BCC.'),
            'subject' => $schema->string()->required(),
            'body' => $schema->string()->required()
                ->description('Markdown. Pode conter {{budget:current}}, {{budget:N}}, {{plan}}.'),
        ];
    }

    /**
     * Coerce a "to/cc/bcc" entry into a real email. Accepts either a
     * literal email or a Contact label slug; returns null when the
     * value is neither a valid email nor a known label for this user.
     */
    protected function resolveAddress(string $raw, int $userId): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            return $raw;
        }

        $contact = Contact::forUserAndLabel($userId, $raw);

        return $contact?->email;
    }

    /**
     * @param  mixed  $raw
     * @return list<string>
     */
    protected function resolveAddressList($raw, int $userId): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [$raw];
        }

        if (! is_array($raw)) {
            return [];
        }

        $resolved = [];
        foreach ($raw as $entry) {
            $email = $this->resolveAddress((string) $entry, $userId);
            if ($email !== null) {
                $resolved[] = $email;
            }
        }

        return array_values(array_unique($resolved));
    }
}
