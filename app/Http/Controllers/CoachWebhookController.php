<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CoachReplyProcessor;
use App\Services\EmailReplyParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CoachWebhookController extends Controller
{
    /**
     * Inbound email webhook.
     *
     * Generic shape — each provider (Resend, Mailgun, Postmark, etc.) maps to this:
     * {
     *   "from": "rogers@example.com",
     *   "subject": "Re: ☀️ Foco do dia",
     *   "text": "já paguei a fatura, marca como concluído",
     *   "html": "<p>já paguei...</p>",
     *   "in_reply_to": null|"<message-id>",
     *   "conversation_id": null|"uuid"   // optional, takes precedence over in_reply_to
     * }
     *
     * Auth: shared secret in `X-Coach-Secret` header (set COACH_WEBHOOK_SECRET in .env).
     */
    public function handle(Request $request, CoachReplyProcessor $processor): JsonResponse
    {
        $expectedSecret = config('coach.webhook_secret');
        if ($expectedSecret && ! hash_equals($expectedSecret, (string) $request->header('X-Coach-Secret'))) {
            Log::warning('Coach webhook auth failed', ['ip' => $request->ip()]);

            return response()->json(['error' => 'unauthorized'], 401);
        }

        $data = $request->validate([
            'from' => 'required|string|email',
            'to' => 'nullable|string',
            'subject' => 'nullable|string|max:500',
            'text' => 'nullable|string',
            'html' => 'nullable|string',
            'conversation_id' => 'nullable|string|size:36',
        ]);

        $user = User::where('email', $data['from'])->first();

        if (! $user) {
            Log::info('Coach webhook: unknown sender', ['from' => $data['from']]);

            return response()->json(['error' => 'unknown sender'], 404);
        }

        $rawBody = $data['text'] ?? $data['html'] ?? '';
        $reply = EmailReplyParser::extractReply($rawBody);

        if (trim($reply) === '') {
            Log::info('Coach webhook: empty reply', ['from' => $data['from']]);

            return response()->json(['ok' => true, 'note' => 'empty reply, ignored']);
        }

        // Resolve conversation: explicit conversation_id wins; otherwise try to
        // extract it from the To address subaddressing (reply+CONVID@...);
        // otherwise fall back to subject matching inside the processor.
        $conversationId = $data['conversation_id']
            ?? $this->extractConversationIdFromTo($data['to'] ?? null);

        Log::info('Coach webhook: processing', [
            'from' => $user->email,
            'to' => $data['to'] ?? null,
            'subject' => $data['subject'] ?? null,
            'reply_length' => strlen($reply),
            'conversation_id' => $conversationId,
        ]);

        try {
            $result = $processor->process(
                user: $user,
                reply: $reply,
                conversationId: $conversationId,
                subjectHint: $data['subject'] ?? null,
            );

            return response()->json([
                'ok' => true,
                'conversation_id' => $result['conversation_id'],
                'response_preview' => mb_substr($result['response'], 0, 200),
            ]);
        } catch (\Throwable $e) {
            Log::error('Coach webhook: processing error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'processing failed'], 500);
        }
    }

    /**
     * Pull a conversation UUID out of a To: header that uses subaddressing,
     * e.g. "reply+019dee27-cbd7-7196-9ec8-0ddb4f585bec@coach.allur.co" or
     * `Coach <reply+...@coach.allur.co>`. Returns null when no match.
     */
    protected function extractConversationIdFromTo(?string $to): ?string
    {
        if (! $to) {
            return null;
        }

        // UUID v7 (used by laravel/ai) and v4 both fit this 8-4-4-4-12 hex pattern.
        if (preg_match(
            '/reply\+([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})@/i',
            $to,
            $m,
        )) {
            return strtolower($m[1]);
        }

        return null;
    }
}
