<?php

namespace App\Agent\Agents;

use App\Agent\Services\CoachPromptBuilder;
use App\Agent\Tools\CreateAction;
use App\Agent\Tools\CreateGoal;
use App\Agent\Tools\ListActions;
use App\Agent\Tools\LogWhy;
use App\Agent\Tools\LogWorry;
use App\Agent\Tools\MoveAction;
use App\Agent\Tools\RecallFacts;
use App\Agent\Tools\RememberFact;
use App\Agent\Tools\ShareViaEmail;
use App\Agent\Tools\SwitchToGoal;
use App\Agent\Tools\UpdateAction;
use App\Agent\Tools\WebFetch;
use App\Agent\Tools\WebSearch;
use App\Domains\Finance\Tools\BudgetSnapshot;
use App\Domains\Finance\Tools\ReadBudget;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * The coach agent — laravel/ai's hook into the LLM. Responsibilities are
 * intentionally narrow: hold the active-goal pointer, expose tools to the
 * LLM, hand the system prompt to the SDK.
 *
 * Prompt construction was extracted to CoachPromptBuilder in Phase 9 — it
 * was a 320-line god-method mixed with Eloquent queries and locale loading,
 * and end-to-end-only testable. The builder is now unit-testable in
 * isolation; this class just composes.
 */
class CoachAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    protected ?int $activeGoalId = null;

    public function forGoal(?int $goalId): static
    {
        $this->activeGoalId = $goalId;

        return $this;
    }

    public function instructions(): Stringable|string
    {
        $user = $this->resolveUser();
        if ($user === null) {
            return '';
        }

        return app(CoachPromptBuilder::class)->build(
            user: $user,
            activeGoalId: $this->activeGoalId,
            conversationId: $this->conversationId ?? null,
        );
    }

    public function tools(): iterable
    {
        $activeGoalId = $this->resolveActiveGoalId();

        return [
            new ListActions,
            new CreateAction($activeGoalId),
            new UpdateAction,
            new MoveAction,
            new CreateGoal,
            new SwitchToGoal($this->conversationId),
            new BudgetSnapshot,
            new ReadBudget,
            new LogWhy($activeGoalId),
            new LogWorry($activeGoalId),
            new RememberFact,
            new RecallFacts,
            new ShareViaEmail,
            new WebSearch,
            new WebFetch,
        ];
    }

    /**
     * The acting user — set explicitly via Promptable::forUser($user) by the
     * caller (CoachInteraction, the chat page, the webhook). Falls back to
     * auth() for legacy callers that haven't been updated. Returns null when
     * neither is available; instructions() and tools() short-circuit on that.
     */
    protected function resolveUser(): ?User
    {
        if (isset($this->user) && $this->user instanceof User) {
            return $this->user;
        }

        $id = auth()->id();

        return $id ? User::find($id) : null;
    }

    /**
     * The active goal's id for tool construction (CreateAction, LogWhy,
     * LogWorry need it stamped on inserts). Mirrors the resolveActiveGoal
     * logic in CoachPromptBuilder: explicit goal wins, then the
     * conversation's goal, then the user's most-recent non-archived goal.
     *
     * Returns just the id (not the model) because tools only need the id.
     */
    protected function resolveActiveGoalId(): ?int
    {
        if ($this->activeGoalId !== null) {
            return $this->activeGoalId;
        }

        if ($this->conversationId !== null) {
            $goalId = DB::table('agent_conversations')
                ->where('id', $this->conversationId)
                ->value('goal_id');

            if ($goalId !== null) {
                return (int) $goalId;
            }
        }

        return Goal::where('is_archived', false)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->value('id');
    }
}
