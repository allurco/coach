<?php

namespace App\Services;

use App\Exceptions\ShareFailedException;
use App\Mail\Share;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Single source of truth for "send a Share email on behalf of a user."
 * Both the ShareViaEmail agent tool and the per-message share modal in
 * Coach.php delegate here, so recipient resolution, rate limiting and
 * auto-BCC stay identical across entry points.
 */
class Sharer
{
    /**
     * Per-user cap on outbound shares. External email = one bug can
     * fan out to ten people; the cap bounds the tail risk without
     * blocking real-world use (a monthly email to an accountant
     * won't trip 5/hour).
     */
    public const MAX_PER_HOUR = 5;

    /**
     * Send a Share email. Returns the translated success message
     * suitable for surfacing to the user. Throws ShareFailedException
     * (with a translated message) when validation, recipient
     * resolution, or rate-limit checks fail.
     *
     * @param  list<string>  $cc
     * @param  list<string>  $bcc
     *
     * @throws ShareFailedException
     */
    public function send(
        User $user,
        string $to,
        string $subject,
        string $body,
        array $cc = [],
        array $bcc = [],
    ): string {
        $body = trim($body);
        if ($body === '') {
            throw new ShareFailedException((string) __('coach.share.errors.empty_body'));
        }

        $subject = trim($subject) ?: (string) __('coach.share.default_subject');

        $resolvedTo = $this->resolveAddress($to, $user->id);
        if ($resolvedTo === null) {
            throw new ShareFailedException((string) __('coach.share.errors.unknown_recipient', [
                'value' => $to,
            ]));
        }

        $resolvedCc = $this->resolveAddressList($cc, $user->id);
        $resolvedBcc = $this->resolveAddressList($bcc, $user->id);

        $key = 'share-via-email:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, self::MAX_PER_HOUR)) {
            throw new ShareFailedException((string) __('coach.share.errors.rate_limited', [
                'minutes' => (int) ceil(RateLimiter::availableIn($key) / 60),
            ]));
        }
        RateLimiter::hit($key, 3600);

        $expandedBody = (new PlaceholderRenderer)->render($body, $user->id);

        // Always BCC the sender — they need a copy of what went out
        // under their name.
        $resolvedBcc[] = $user->email;
        $resolvedBcc = array_values(array_unique(array_filter($resolvedBcc)));

        Mail::to($resolvedTo)
            ->cc($resolvedCc)
            ->bcc($resolvedBcc)
            ->send(new Share(
                emailSubject: $subject,
                body: $expandedBody,
                senderName: $user->name ?: 'Coach',
            ));

        return (string) __('coach.share.success', ['email' => $resolvedTo]);
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

        return Contact::forUserAndLabel($userId, $raw)?->email;
    }

    /**
     * @param  mixed  $raw  array of strings, JSON string, or scalar
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
