<?php

namespace App\Ai\Tools;

use App\Models\Budget;
use App\Services\PlaceholderRenderer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Reads the user's current budget without creating a new one. Pair to
 * BudgetSnapshot: that tool *creates*, this tool *reads*. When the
 * user asks "como está meu orçamento?" the agent should call this
 * (cheap, no input from user needed) instead of asking the user for
 * income/expenses again.
 *
 * Returns the FULLY EXPANDED markdown table (income, buckets, breakdowns,
 * targets vs actuals) — NOT the {{budget:current}} placeholder. The
 * placeholder pattern only works for outbound chat/email rendering; tool
 * results go straight back to the LLM, so we need real data here, not
 * a template string. Production bug discovered when the agent reported
 * "the tool isn't working" because it saw literal "{{budget:current}}"
 * as the tool output.
 */
class ReadBudget implements Tool
{
    public function description(): Stringable|string
    {
        return 'Reads the user\'s current budget (the most recently persisted one) and returns the '
            .'formatted table — including the line-by-line breakdown of each bucket. '
            .'Use ALWAYS when the user asks ANY current monetary value. Covers 3 levels: '
            .'(1) overview: "how\'s my budget?", "what\'s my situation?", "what\'s left this month?"; '
            .'(2) bucket: "how much for investment / to invest?", "how much for the emergency fund?", '
            .'"how much for leisure?", "how much in fixed costs?", "what\'s my net income?"; '
            .'(3) specific breakdown line: "how much do I spend on rent / groceries / transport / '
            .'food / bills / subscriptions?". '
            .'The numbers come FROM HERE, not from your memory. DO NOT invent, DO NOT estimate, DO NOT '
            .'recover from old messages — if the line the user asked about does not exist in the breakdown, '
            .'say explicitly that there\'s no such line in the current budget instead of guessing. '
            .'DO NOT use to create — for that use BudgetSnapshot.';
    }

    public function handle(Request $request): Stringable|string
    {
        $userId = auth()->id();
        if (! $userId) {
            return (string) __('coach.read_budget.unauthenticated');
        }

        $budget = Budget::currentForUser($userId);
        if (! $budget) {
            return (string) __('coach.read_budget.none');
        }

        // Expand the placeholder server-side so the LLM receives real budget
        // data (markdown table + breakdowns) instead of the template string.
        return (new PlaceholderRenderer)->render('{{budget:current}}', $userId);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
