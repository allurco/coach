<?php

namespace App\Ai\Tools;

use App\Models\Budget;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class BudgetSnapshot implements Tool
{
    /**
     * @param  ?int  $activeGoalId  The finance goal this snapshot belongs to.
     *                              Optional — cross-goal snapshots are valid
     *                              (e.g. user has only "Geral" but is doing
     *                              budget planning).
     */
    public function __construct(protected ?int $activeGoalId = null) {}

    public function description(): Stringable|string
    {
        return 'Captura uma fotografia mensal do orçamento do usuário em 4 caixas: '
            .'Custos Fixos (alvo 50-60% da renda), Investimentos (alvo 10%), '
            .'Reservas (alvo 5-10%), Lazer (sobra automática, alvo 20-35%). '
            .'Aplica buffer de 15% sobre os custos fixos pra cobrir linhas esquecidas. '
            .'Persiste em coach_budgets pra revisita futura. Retorna análise em markdown '
            .'com diff vs alvo. Use quando o usuário entrevistar com renda + gastos, '
            .'ou pedir explicitamente um plano financeiro.';
    }

    public function handle(Request $request): Stringable|string
    {
        $netIncome = (float) ($request['net_income'] ?? 0);
        if ($netIncome <= 0) {
            return 'Erro: renda líquida (net_income) inválida — precisa ser maior que zero.';
        }

        $month = (string) ($request['month'] ?? now()->format('Y-m'));
        $fixedCosts = $this->normalizeBreakdown($request['fixed_costs'] ?? []);
        $investments = $this->normalizeBreakdown($request['investments'] ?? []);
        $savings = $this->normalizeBreakdown($request['savings'] ?? []);
        $notes = trim((string) ($request['notes'] ?? '')) ?: null;

        $fixedSubtotal = array_sum($fixedCosts);
        $bufferPct = Budget::FIXED_COSTS_BUFFER_PCT;
        $fixedTotal = round($fixedSubtotal * (1 + $bufferPct / 100), 2);
        $investmentsTotal = array_sum($investments);
        $savingsTotal = array_sum($savings);
        $leisure = round($netIncome - $fixedTotal - $investmentsTotal - $savingsTotal, 2);

        $budget = Budget::create([
            'goal_id' => $this->activeGoalId,
            'month' => $month,
            'net_income' => $netIncome,
            'fixed_costs_breakdown' => $fixedCosts ?: null,
            'fixed_costs_subtotal' => $fixedSubtotal,
            'fixed_costs_total' => $fixedTotal,
            'investments_breakdown' => $investments ?: null,
            'investments_total' => $investmentsTotal,
            'savings_breakdown' => $savings ?: null,
            'savings_total' => $savingsTotal,
            'leisure_amount' => $leisure,
            'notes' => $notes,
        ]);

        return $this->renderMarkdown($budget, $netIncome, $fixedCosts, $fixedSubtotal,
            $fixedTotal, $bufferPct, $investments, $investmentsTotal,
            $savings, $savingsTotal, $leisure, $month);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'net_income' => $schema->number()->required(),
            'month' => $schema->string(),
            'fixed_costs' => $schema->object(),
            'investments' => $schema->object(),
            'savings' => $schema->object(),
            'notes' => $schema->string(),
        ];
    }

    /**
     * Normalize a breakdown to [label => float-amount] pairs.
     *
     * @param  mixed  $raw
     * @return array<string,float>
     */
    protected function normalizeBreakdown($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $result = [];
        foreach ($raw as $label => $amount) {
            if (! is_string($label) || $label === '') {
                continue;
            }
            $value = (float) $amount;
            if ($value > 0) {
                $result[$label] = $value;
            }
        }

        return $result;
    }

    protected function renderMarkdown(
        Budget $budget,
        float $netIncome,
        array $fixedCosts,
        float $fixedSubtotal,
        float $fixedTotal,
        int $bufferPct,
        array $investments,
        float $investmentsTotal,
        array $savings,
        float $savingsTotal,
        float $leisure,
        string $month,
    ): string {
        $lines = [];
        $lines[] = "🧾 **Plano de Gastos · {$month}**";
        $lines[] = '';
        $lines[] = 'Renda líquida: **R$ '.number_format($netIncome, 2, ',', '.').'**';
        $lines[] = '';

        // Fixed costs
        $fixedPct = $netIncome > 0 ? ($fixedTotal / $netIncome) * 100 : 0;
        $lines[] = '📊 **Custos Fixos: R$ '.number_format($fixedTotal, 2, ',', '.').'** ('
            .number_format($fixedPct, 0).'% — alvo 50-60% '.$this->statusIcon('fixed_costs', $fixedPct).')';
        foreach ($fixedCosts as $label => $amount) {
            $lines[] = '  - '.$label.': R$ '.number_format($amount, 2, ',', '.');
        }
        if ($fixedSubtotal > 0) {
            $lines[] = '  - _Subtotal: R$ '.number_format($fixedSubtotal, 2, ',', '.').'_';
            $lines[] = '  - _Buffer '.$bufferPct.'%: R$ '.number_format($fixedTotal - $fixedSubtotal, 2, ',', '.').'_';
        }
        $lines[] = '';

        // Investments
        $invPct = $netIncome > 0 ? ($investmentsTotal / $netIncome) * 100 : 0;
        $lines[] = '💰 **Investimentos: R$ '.number_format($investmentsTotal, 2, ',', '.').'** ('
            .number_format($invPct, 0).'% — alvo 10% '.$this->statusIcon('investments', $invPct).')';
        foreach ($investments as $label => $amount) {
            $lines[] = '  - '.$label.': R$ '.number_format($amount, 2, ',', '.');
        }
        $lines[] = '';

        // Savings
        $savPct = $netIncome > 0 ? ($savingsTotal / $netIncome) * 100 : 0;
        $lines[] = '🎯 **Reservas: R$ '.number_format($savingsTotal, 2, ',', '.').'** ('
            .number_format($savPct, 0).'% — alvo 5-10% '.$this->statusIcon('savings', $savPct).')';
        foreach ($savings as $label => $amount) {
            $lines[] = '  - '.$label.': R$ '.number_format($amount, 2, ',', '.');
        }
        $lines[] = '';

        // Leisure (the leftover)
        $leisurePct = $netIncome > 0 ? ($leisure / $netIncome) * 100 : 0;
        if ($leisure < 0) {
            $lines[] = '🍕 **Lazer: R$ '.number_format($leisure, 2, ',', '.').'** ⚠ DÉFICIT — buckets excedem a renda em '
                .'R$ '.number_format(abs($leisure), 2, ',', '.');
        } else {
            $lines[] = '🍕 **Lazer (sobra): R$ '.number_format($leisure, 2, ',', '.').'** ('
                .number_format($leisurePct, 0).'% — alvo 20-35% '.$this->statusIcon('leisure', $leisurePct).')';
        }

        $lines[] = '';
        $lines[] = '_(snapshot id: '.$budget->id.')_';

        return implode("\n", $lines);
    }

    /**
     * Returns ✓ when the bucket's pct is within target range, ⚠ otherwise.
     */
    protected function statusIcon(string $bucket, float $pct): string
    {
        $range = Budget::TARGET_RANGES[$bucket] ?? null;
        if ($range === null) {
            return '';
        }

        return ($pct >= $range['min'] && $pct <= $range['max']) ? '✓' : '⚠';
    }
}
