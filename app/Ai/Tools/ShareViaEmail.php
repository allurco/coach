<?php

namespace App\Ai\Tools;

use App\Exceptions\ShareFailedException;
use App\Services\Sharer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ShareViaEmail implements Tool
{
    public function description(): Stringable|string
    {
        return 'Sends an email with whatever the user wants to share with third parties — '
            .'accountant, partner, lawyer, etc. The agent writes the body in markdown '
            .'and can use placeholders for structured data: '
            .'`{{budget:current}}` (user\'s most recent budget), '
            .'`{{budget:N}}` (specific snapshot), `{{plan}}` (active actions). '
            .'Each recipient can be a literal email OR the label of a saved Contact. '
            .'The user receives an automatic BCC copy. CONFIRM RECIPIENT '
            .'AND SUBJECT WITH THE USER BEFORE CALLING.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = auth()->user();
        if (! $user) {
            return (string) __('coach.share.errors.unauthenticated');
        }

        try {
            return app(Sharer::class)->send(
                user: $user,
                to: (string) ($request['to'] ?? ''),
                subject: (string) ($request['subject'] ?? ''),
                body: (string) ($request['body'] ?? ''),
                cc: $this->coerceList($request['cc'] ?? []),
                bcc: $this->coerceList($request['bcc'] ?? []),
            );
        } catch (ShareFailedException $e) {
            return $e->getMessage();
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'to' => $schema->string()->required()
                ->description('Email literal OU label de um Contact salvo (ex: "contador").'),
            // Gemini rejects array params without an `items` schema
            // ("missing field" 400). Both lists carry strings (literal
            // emails or Contact labels).
            'cc' => $schema->array()
                ->items($schema->string())
                ->description('Lista de emails ou labels para CC.'),
            'bcc' => $schema->array()
                ->items($schema->string())
                ->description('Lista de emails ou labels para BCC.'),
            'subject' => $schema->string()->required(),
            'body' => $schema->string()->required()
                ->description('Markdown. Pode conter {{budget:current}}, {{budget:N}}, {{plan}}.'),
        ];
    }

    /**
     * The LLM sometimes hands cc/bcc as a JSON-encoded string instead
     * of a real array — normalize before passing to the service.
     *
     * @param  mixed  $raw
     * @return list<string>
     */
    protected function coerceList($raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? array_values($decoded) : [$raw];
        }

        return is_array($raw) ? array_values($raw) : [];
    }
}
