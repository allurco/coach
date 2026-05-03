<?php

namespace App\Services;

use App\Ai\Agents\FinanceCoach;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Enums\Lab;

class CoachReplyProcessor
{
    /**
     * Process an inbound email reply: route to existing conversation if any,
     * otherwise start a new one. Coach response is persisted via the
     * Conversational trait — user can see it in the web chat.
     *
     * @return array{conversation_id: string, response: string}
     */
    public function process(
        User $user,
        string $reply,
        ?string $conversationId = null,
        ?string $subjectHint = null,
    ): array {
        // Authenticate so any tools the agent calls (CreateAction, UpdateAction,
        // ListActions) are scoped to this user via the Action global scope.
        auth()->login($user);

        if ($conversationId === null) {
            $conversationId = $this->guessConversationFromSubject($user, $subjectHint);
        }

        $coach = $conversationId
            ? (new FinanceCoach)->continue($conversationId, as: $user)
            : (new FinanceCoach)->forUser($user);

        $promptPrefix = "[mensagem chegou via email do Rogers, processe normalmente — pode usar tools pra atualizar o plano se ele te pediu pra marcar algo, criar ação, anotar fato]\n\n";

        $response = $coach->prompt(
            $promptPrefix.$reply,
            provider: Lab::Gemini,
            model: 'gemini-2.5-flash',
        );

        return [
            'conversation_id' => $response->conversationId ?? $conversationId,
            'response' => trim((string) $response),
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
