<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class WebFetch implements Tool
{
    /**
     * Hard cap on returned content length to keep prompt size bounded.
     */
    protected const MAX_CONTENT = 12_000;

    public function description(): Stringable|string
    {
        return 'Faz GET HTTP em uma URL específica e retorna o texto extraído (HTML é convertido pra texto). '
            .'Use quando o usuário compartilhar um link e você precisar ler o conteúdo, ou quando '
            .'um WebSearch deu uma URL relevante e você quer mais detalhe. Apenas http(s).';
    }

    public function handle(Request $request): Stringable|string
    {
        $url = trim((string) ($request['url'] ?? ''));

        if ($url === '') {
            return 'URL is required.';
        }

        $parts = parse_url($url);

        if ($parts === false) {
            return "Invalid URL: {$url}";
        }

        // Check scheme first so non-http URLs (file://, ftp://, javascript:) get
        // the targeted "only http(s)" rejection instead of generic "invalid".
        $scheme = strtolower($parts['scheme'] ?? '');
        if ($scheme !== '' && ! in_array($scheme, ['http', 'https'], true)) {
            return "Only http(s) URLs are supported (got {$scheme}).";
        }

        if (empty($parts['scheme']) || empty($parts['host'])) {
            return "Invalid URL: {$url}";
        }

        if ($this->isPrivateHost($parts['host'])) {
            return "Blocked: {$parts['host']} resolves to a private/loopback range. Public URLs only.";
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'Coach.-WebFetch/1.0'])
                ->get($url);

            if (! $response->successful()) {
                return "Fetch failed: {$response->status()} from {$url}";
            }

            $contentType = strtolower($response->header('Content-Type') ?? '');
            $body = $response->body();

            if (str_contains($contentType, 'html') || str_starts_with(ltrim($body), '<')) {
                $body = $this->htmlToText($body);
            }

            $body = trim($body);

            if (mb_strlen($body) > self::MAX_CONTENT) {
                $body = mb_substr($body, 0, self::MAX_CONTENT)."\n\n[truncated — content was longer than ".self::MAX_CONTENT.' chars]';
            }

            return $body !== '' ? $body : '(empty response body)';
        } catch (\Throwable $e) {
            Log::error('WebFetch: exception', ['url' => $url, 'message' => $e->getMessage()]);

            return "Fetch failed: network error for {$url}";
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()->required(),
        ];
    }

    protected function htmlToText(string $html): string
    {
        // Strip script/style entirely (their content is never useful as text).
        $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html);
        // Block-level tags become newlines so structure survives strip_tags.
        $html = preg_replace('/<\/(p|div|h[1-6]|li|tr|br|hr)[^>]*>/i', "\n", $html);
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $text = strip_tags((string) $html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Collapse runs of whitespace to keep the prompt tidy.
        $text = preg_replace("/[ \t]+/", ' ', (string) $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return (string) $text;
    }

    /**
     * Block IPs that point to localhost, link-local, RFC1918 private ranges,
     * and the AWS / cloud metadata endpoints — basic SSRF defense.
     */
    protected function isPrivateHost(string $host): bool
    {
        $host = strtolower($host);

        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return true;
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : @gethostbyname($host);

        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        // FILTER_FLAG_NO_PRIV_RANGE blocks 10/8, 172.16/12, 192.168/16, fc00::/7
        // FILTER_FLAG_NO_RES_RANGE blocks 127/8, 169.254/16 (link-local incl. AWS metadata), fe80::/10
        return ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );
    }
}
