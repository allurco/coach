<?php

namespace App\Ai\Tools;

use App\Models\Budget;
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
 * Returns the {{budget:current}} placeholder so the chat's
 * PlaceholderRenderer expands it to the full markdown table at
 * render time — same shape BudgetSnapshot uses for its output.
 */
class ReadBudget implements Tool
{
    public function description(): Stringable|string
    {
        return 'Lê o orçamento atual do usuário (o mais recente persistido) e devolve a tabela formatada. '
            .'Use quando o usuário perguntar "como está meu orçamento?", "qual minha situação financeira?", '
            .'ou qualquer pergunta sobre o budget existente. NÃO use pra criar — pra isso use BudgetSnapshot.';
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

        return '{{budget:current}}';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
