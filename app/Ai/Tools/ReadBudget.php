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
        return 'Lê o orçamento atual do usuário (o mais recente persistido) e devolve a tabela formatada — '
            .'incluindo o breakdown linha por linha de cada bucket. '
            .'Use SEMPRE que o usuário perguntar QUALQUER valor monetário atual. Cobre 3 níveis: '
            .'(1) panorama: "como está meu orçamento?", "qual minha situação?", "o que sobrou esse mês?"; '
            .'(2) bucket: "quanto pra investimento / investir?", "quanto pra reserva / emergência?", '
            .'"quanto pra lazer?", "quanto em custos fixos?", "qual minha renda líquida?"; '
            .'(3) linha específica do breakdown: "quanto eu gasto com aluguel / mercado / transporte / '
            .'alimentação / contas / assinaturas?". '
            .'Os números vêm DAQUI, não da sua memória. NÃO invente, NÃO estime, NÃO recupere de '
            .'mensagens antigas — se a linha que o usuário pediu não existir no breakdown, diga '
            .'explicitamente que não tem essa linha no orçamento atual em vez de chutar. '
            .'NÃO use pra criar — pra isso use BudgetSnapshot.';
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
