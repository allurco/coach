<?php

namespace App\Agent\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Email-reply entry point into the agent. Resolves the right conversation
 * (explicit id wins, then subaddressing in the To: header — handled by
 * the webhook controller — then subject-line matching here), then hands
 * off to CoachInteraction for the actual agent orchestration.
 *
 * Was previously a near-duplicate of Coach.php::runAi without the retry,
 * decoration, or conversation-goal stamping. After Phase 7 the email
 * path gets all three for free by routing through CoachInteraction.
 */
class CoachReplyProcessor
{
    public function __construct(protected CoachInteraction $interaction) {}

    /**
     * Process an inbound email reply: route to the matching conversation
     * (if any) and prompt the agent. The response is persisted via the
     * SDK's Conversational trait — the user can see it in the web chat.
     *
     * @return array{conversation_id: string, response: string}
     */
    public function process(
        User $user,
        string $reply,
        ?string $conversationId = null,
        ?string $subjectHint = null,
    ): array {
        if ($conversationId === null) {
            $conversationId = $this->guessConversationFromSubject($user, $subjectHint);
        }

        $result = $this->interaction->run(
            user: $user,
            promptText: $reply,
            conversationId: $conversationId,
            // Lets the agent know this came in via email vs the web chat and
            // is allowed to use tools (mark something, create an action, save
            // a fact) when the user explicitly asked.
            promptPrefix: "[message arrived via email — process normally; you may use tools to update the plan if the user asked you to mark something, create an action, or save a fact]\n\n",
            modelKey: 'background',
        );

        return [
            'conversation_id' => $result->conversationId ?? $conversationId,
            'response' => $result->text,
        ];
    }

    /**
     * Try to match the email subject to an existing conversation title.
     * If no match, return null and a fresh conversation will be started.
     */
    protected function guessConversationFromSubject(User $user, ?string $subject): ?string
    {
        if (! $subject) {
            return null;
        }

        // Strip common email subject prefixes (Re:, Fwd:, RES:, ENC:, etc.)
        $clean = preg_replace('/^(\s*(re|res|fw|fwd|enc|encaminhada?):\s*)+/iu', '', $subject);
        $clean = trim((string) $clean);

        if ($clean === '') {
            return null;
        }

        // Strip emoji prefixes from coach pings (☀️ Foco do dia → Foco do dia).
        // Includes Mark-Nonspacing for the U+FE0F variation selector that follows
        // many emojis ("☀\u{FE0F}").
        $clean = preg_replace('/^[\p{S}\p{Mn}\s]+/u', '', $clean);
        $clean = trim($clean);

        return DB::table('agent_conversations')
            ->where('user_id', $user->id)
            ->where('title', 'like', '%'.mb_substr($clean, 0, 40).'%')
            ->orderByDesc('updated_at')
            ->value('id');
    }
}
