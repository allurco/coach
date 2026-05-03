<?php

namespace App\Services;

class EmailReplyParser
{
    /**
     * Extract only the user's new reply, stripping quoted history.
     *
     * Handles common reply markers from Gmail, Outlook, Apple Mail, Yahoo etc.
     */
    public static function extractReply(string $body): string
    {
        $text = self::stripHtml($body);

        $patterns = [
            // Gmail: "On Mon, Jan 1, 2024 at 10:00 AM <user@example.com> wrote:"
            '/\n\s*Em\s+\w{3,9},?\s+\d{1,2}\s+de\s+\w{3,9}.*?escreveu:/iu',
            '/\n\s*On\s+\w{3,9},?\s+\w{3,9}\s+\d{1,2},.*?wrote:/iu',
            '/\n\s*El\s+\w{3,9}.*?escribió:/iu',

            // Outlook: "From: ..."
            '/\n\s*-{2,}\s*Mensagem\s+original\s*-{2,}/iu',
            '/\n\s*-{2,}\s*Original\s+Message\s*-{2,}/iu',
            '/\n\s*From:\s+.+?(\n|\r)/iu',
            '/\n\s*De:\s+.+?(\n|\r)/iu',

            // Apple Mail
            '/\n\s*>\s*Em\s.+escreveu/iu',
            '/\n\s*>\s*On\s.+wrote/iu',

            // Generic quoted blocks (>>>)
            '/\n\s*-{2,}\s*$/m',

            // "Begin forwarded message"
            '/\n\s*Begin\s+forwarded\s+message:/i',
            '/\n\s*Mensagem\s+encaminhada/iu',
        ];

        $earliest = strlen($text);

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE)) {
                $pos = $m[0][1];
                if ($pos < $earliest) {
                    $earliest = $pos;
                }
            }
        }

        $reply = substr($text, 0, $earliest);

        // Strip lines starting with > (quoted)
        $lines = explode("\n", $reply);
        $clean = array_filter($lines, fn ($l) => ! preg_match('/^\s*>/', $l));

        return trim(implode("\n", $clean));
    }

    protected static function stripHtml(string $body): string
    {
        if (! preg_match('/<\/?[a-z][^>]*>/i', $body)) {
            return $body;
        }

        // Convert common HTML to text equivalents
        $body = preg_replace('/<br\s*\/?>/i', "\n", $body);
        $body = preg_replace('/<\/p>/i', "\n\n", $body);
        $body = preg_replace('/<blockquote[^>]*>.*?<\/blockquote>/is', '', $body);
        $body = strip_tags($body);

        return html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
