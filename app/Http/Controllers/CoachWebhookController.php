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

        Log::info('Coach webhook: processing', [
            'from' => $user->email,
            'subject' => $data['subject'] ?? null,
            'reply_length' => strlen($reply),
            'conversation_id' => $data['conversation_id'] ?? null,
        ]);

        try {
            $result = $processor->process(
                user: $user,
                reply: $reply,
                conversationId: $data['conversation_id'] ?? null,
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
}
