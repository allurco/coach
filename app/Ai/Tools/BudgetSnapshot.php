<?php

namespace App\Ai\Tools;

use App\Models\Action;
use App\Models\Budget;
use App\Services\PlaceholderRenderer;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class BudgetSnapshot implements Tool
{
    /**
     * @param  ?int  $activeGoalId  Optional provenance — records which goal
     *                              the budget was first set up under, but
     *                              has no effect on retrieval. Budgets are
     *                              user-scoped (life-context), not
     *                              goal-scoped — every goal sees them via
     *                              CoachAgent::lifeContext().
     */
    public function __construct(protected ?int $activeGoalId = null) {}

    public function description(): Stringable|string
    {
        return 'Captures a monthly snapshot of the user\'s budget in 4 buckets: '
            .'Fixed Costs (target 50-60% of income), Investments (target 10%), '
            .'Reserves (target 5-10%), Leisure (automatic remainder, target 20-35%). '
            .'Applies a 15% buffer over fixed costs to cover forgotten line items. '
            .'Persists to coach_budgets for future revisit. Returns analysis in markdown '
            .'with diff vs target. Use when the user is being interviewed about '
            .'income + expenses, or explicitly asks for a financial plan.';
    }

    public function handle(Request $request): Stringable|string
    {
        $netIncome = (float) ($request['net_income'] ?? 0);
        if ($netIncome <= 0) {
            return 'Erro: renda líquida (net_income) inválida — precisa ser maior que zero.';
        }

        $month = $this->normalizeMonth($request['month'] ?? null);
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

        $this->autoCloseSetupActions($budget);

        // Return a placeholder reference rather than the rendered markdown.
        // coach_budgets is the source of truth — the chat persists the
        // placeholder, and view-time rendering expands it from the row.
        // The agent's next turn still gets the input args via tool_calls,
        // so it can reason about the snapshot without the markdown.
        return self::placeholderFor($budget->id);
    }

    public static function placeholderFor(int $budgetId): string
    {
        return '{{budget:'.$budgetId.'}}';
    }

    /**
     * Close pending "create budget" actions on this user when a snapshot
     * is persisted — kills the action-zombie pattern where the agent
     * would see a fresh budget plus a stale "Montar o orçamento" action
     * and reset the user to step zero. Conservative match: only titles
     * that read like budget-setup verbs, not anything that mentions
     * "orçamento" in passing.
     */
    protected function autoCloseSetupActions(Budget $budget): void
    {
        $pattern = '/(montar|criar|set\s*up|build|create).{0,40}(or[çc]amento|budget|plano\s+financeiro|financial\s+plan)/iu';

        Action::query()
            ->whereIn('status', Action::OPEN_STATUSES)
            ->get()
            ->filter(fn (Action $a) => (bool) preg_match($pattern, (string) $a->title))
            ->each(fn (Action $a) => $a->update([
                'status' => 'concluido',
                'completed_at' => now(),
                'result_notes' => __('coach.budget.auto_close_note', ['snapshot_id' => $budget->id]),
            ]));
    }

    /**
     * Backward-compatible facade for the historical `{{budget:N}}` use
     * case. New code should depend on `App\Services\PlaceholderRenderer`
     * directly — that class supports the broader vocabulary (`{{plan}}`,
     * `{{budget:current}}`, etc.) and is the canonical entry point.
     */
    public static function expandPlaceholders(string $text): string
    {
        return (new PlaceholderRenderer)->render($text);
    }

    public function schema(JsonSchema $schema): array
    {
        // Gemini's function-calling rejects bare `object()` schemas without
        // declared properties. The breakdowns are dynamic (any number of
        // line items with any labels), so we accept JSON strings and parse
        // them server-side. Tests can still pass plain arrays — handle()
        // accepts both shapes.
        return [
            'net_income' => $schema->number()->required(),
            'month' => $schema->string(),
            'fixed_costs' => $schema->string()
                ->description('JSON object as string with {"label": amount} pairs, e.g. {"Aluguel": 1500, "Mercado": 800}'),
            'investments' => $schema->string()
                ->description('JSON object as string with {"label": amount} pairs'),
            'savings' => $schema->string()
                ->description('JSON object as string with {"label": amount} pairs'),
            'notes' => $schema->string(),
        ];
    }

    /**
     * Coerce whatever the agent passed for "month" into the canonical
     * YYYY-MM shape so cron lookups and unique constraints work. Accepts
     * "2026-05", "05/2026", "May 2026", or null (current month).
     */
    protected function normalizeMonth($raw): string
    {
        $raw = trim((string) ($raw ?? ''));
        if ($raw === '') {
            return now()->format('Y-m');
        }

        // Already in canonical shape.
        if (preg_match('/^(\d{4})-(\d{2})$/', $raw)) {
            return $raw;
        }

        // MM/YYYY (the most common Gemini variant in PT-BR).
        if (preg_match('/^(\d{2})\/(\d{4})$/', $raw, $m)) {
            return $m[2].'-'.$m[1];
        }

        // Fall back to Carbon parsing for natural-language months.
        try {
            return Carbon::parse($raw)->format('Y-m');
        } catch (\Throwable) {
            return now()->format('Y-m');
        }
    }

    /**
     * Normalize a breakdown to [label => float-amount] pairs. Accepts:
     *   - PHP array (test calls)
     *   - JSON-encoded string (Gemini tool calls — see schema())
     *   - null / empty / invalid → []
     *
     * @param  mixed  $raw
     * @return array<string,float>
     */
    protected function normalizeBreakdown($raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

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

    /**
     * Render a budget row to markdown — called both when the tool fires
     * (live stream) and when expanding `{{budget:N}}` placeholders later.
     * Reads everything off the Budget model so historical snapshots
     * always reflect the latest layout.
     */
    public function renderForBudget(Budget $budget): string
    {
        $netIncome = (float) $budget->net_income;
        $fixedCosts = is_array($budget->fixed_costs_breakdown) ? $budget->fixed_costs_breakdown : [];
        $fixedSubtotal = (float) $budget->fixed_costs_subtotal;
        $fixedTotal = (float) $budget->fixed_costs_total;
        $bufferPct = Budget::FIXED_COSTS_BUFFER_PCT;
        $investments = is_array($budget->investments_breakdown) ? $budget->investments_breakdown : [];
        $investmentsTotal = (float) $budget->investments_total;
        $savings = is_array($budget->savings_breakdown) ? $budget->savings_breakdown : [];
        $savingsTotal = (float) $budget->savings_total;
        $leisure = (float) $budget->leisure_amount;
        $month = (string) $budget->month;

        $brl = fn (float $v) => 'R$ '.number_format($v, 2, ',', '.');
        $pct = fn (float $v) => $netIncome > 0 ? round(($v / $netIncome) * 100) : 0;

        $fixedPct = $pct($fixedTotal);
        $invPct = $pct($investmentsTotal);
        $savPct = $pct($savingsTotal);
        $leisurePct = $pct($leisure);

        $title = '🧾 **Plano de Gastos — '.$this->prettyMonth($month).'**';
        $income = '_Renda líquida:_ **'.$brl($netIncome).'**';

        // 3-column compact layout — Atual/Alvo merged into one cell so
        // the table fits inside the chat bubble without horizontal
        // overflow. Status icon trails the Atual/Alvo text.
        $row = function (string $label, float $value, int $pct, string $alvo, string $bucket) use ($brl) {
            $icon = $this->statusIcon($bucket, $pct);
            $cell = $pct.'% / '.$alvo.($icon !== '' ? ' '.$icon : '');

            return '| '.$label.' | '.$brl($value).' | '.$cell.' |';
        };
        $leisureCell = $leisure < 0
            ? '— / 20-35% ⚠'
            : $leisurePct.'% / 20-35% '.$this->statusIcon('leisure', $leisurePct);
        $table = [
            '| Caixa | Valor | Atual / Alvo |',
            '| --- | ---: | :--- |',
            $row('📊 Custos Fixos', $fixedTotal, $fixedPct, '50-60%', 'fixed_costs'),
            $row('💰 Investimentos', $investmentsTotal, $invPct, '10%', 'investments'),
            $row('🎯 Reservas', $savingsTotal, $savPct, '5-10%', 'savings'),
            '| 🍕 Lazer (sobra) | '.$brl($leisure).' | '.$leisureCell.' |',
        ];

        $sections = [$title, '', $income, '', implode("\n", $table)];

        if ($leisure < 0) {
            $sections[] = '';
            $sections[] = '> ⚠ **Déficit de '.$brl(abs($leisure)).'** — as caixas planejadas estouram a renda. A gente precisa cortar antes de continuar.';
        }

        // Plain-markdown breakdowns. Avoid raw HTML (`<details>`, `<sub>`)
        // because the chat's Str::markdown pipeline strips it for safety —
        // bold headers + bullet lists render cleanly on any path.
        if (! empty($fixedCosts) || $fixedSubtotal > 0) {
            $sections[] = '';
            $sections[] = '**📊 Custos Fixos — detalhes**';
            $sections[] = '';
            foreach ($fixedCosts as $label => $amount) {
                $sections[] = '- '.$label.': '.$brl($amount);
            }
            if ($fixedSubtotal > 0) {
                $sections[] = '- _Subtotal: '.$brl($fixedSubtotal).'_';
                $sections[] = '- _Buffer '.$bufferPct.'%: '.$brl($fixedTotal - $fixedSubtotal).' (cobre as linhas que você esqueceu)_';
            }
        }

        if (! empty($investments)) {
            $sections[] = '';
            $sections[] = '**💰 Investimentos — detalhes**';
            $sections[] = '';
            foreach ($investments as $label => $amount) {
                $sections[] = '- '.$label.': '.$brl($amount);
            }
        }

        if (! empty($savings)) {
            $sections[] = '';
            $sections[] = '**🎯 Reservas — detalhes**';
            $sections[] = '';
            foreach ($savings as $label => $amount) {
                $sections[] = '- '.$label.': '.$brl($amount);
            }
        }

        $sections[] = '';
        $sections[] = '_snapshot #'.$budget->id.'_';

        return implode("\n", $sections);
    }

    /**
     * Convert "2026-05" → "Maio/2026" (PT-BR) for headline rendering.
     */
    protected function prettyMonth(string $iso): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $iso, $m)) {
            return $iso;
        }
        $names = [
            '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março',
            '04' => 'Abril',   '05' => 'Maio',      '06' => 'Junho',
            '07' => 'Julho',   '08' => 'Agosto',    '09' => 'Setembro',
            '10' => 'Outubro', '11' => 'Novembro',  '12' => 'Dezembro',
        ];

        return ($names[$m[2]] ?? $m[2]).'/'.$m[1];
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
