<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Budget;
use Filament\Notifications\Notification;

/**
 * State + behavior do flyout de Budget (header button → drawer →
 * edição inline + recálculo + save como novo snapshot).
 *
 * Dependências do componente que o usa:
 * - currentForUser(int $userId): ?Budget (via Budget model)
 * - Notification facade (pra feedback de save)
 *
 * Pareado com HasBudgetShare (modal de "Compartilhar este orçamento"
 * que abre de dentro deste flyout). Separados por concern — cada um
 * tem seu próprio state + métodos.
 */
trait HasBudgetFlyout
{
    public bool $budgetOpen = false;

    /** Snapshot row pro flyout, hidratado por openBudget(). */
    public ?array $budgetData = null;

    /**
     * Drives whether the "Budget" header button renders. Lazy: a
     * cheap COUNT(1) query via Budget::currentForUser. Chamado uma
     * vez por render via accessor na view.
     */
    public function hasBudget(): bool
    {
        return Budget::currentForUser((int) auth()->id()) !== null;
    }

    /**
     * Hydrata $budgetData do snapshot atual do usuário e abre o
     * flyout. No-op silencioso quando não há budget — o botão nem
     * renderiza nesse estado, então chegar aqui significa cliente
     * stale ou chamada programática.
     */
    public function openBudget(): void
    {
        $userId = (int) auth()->id();
        $budget = Budget::currentForUser($userId);
        if ($budget === null) {
            return;
        }

        $this->budgetData = [
            'id' => $budget->id,
            'month' => (string) $budget->month,
            'net_income' => (float) $budget->net_income,
            'fixed_costs_lines' => $this->breakdownToLines($budget->fixed_costs_breakdown),
            'fixed_costs_subtotal' => (float) $budget->fixed_costs_subtotal,
            'fixed_costs_total' => (float) $budget->fixed_costs_total,
            'investments_lines' => $this->breakdownToLines($budget->investments_breakdown),
            'investments_total' => (float) $budget->investments_total,
            'savings_lines' => $this->breakdownToLines($budget->savings_breakdown),
            'savings_total' => (float) $budget->savings_total,
            'leisure_amount' => (float) $budget->leisure_amount,
            'notes' => (string) ($budget->notes ?? ''),
        ];
        $this->budgetOpen = true;
    }

    public function closeBudget(): void
    {
        $this->budgetOpen = false;
        $this->budgetData = null;
    }

    /**
     * Adiciona linha editável a um dos três buckets. Investimentos e
     * reservas ganham valor sugerido derivado da renda (10% / 7% — o
     * último é o midpoint do alvo 5-10%). Custos fixos vai em 0 — o
     * usuário sabe o valor exato e default errado é pior que vazio.
     */
    public function addBudgetLine(string $bucket): void
    {
        if ($this->budgetData === null) {
            return;
        }
        $key = match ($bucket) {
            'fixed_costs', 'investments', 'savings' => $bucket.'_lines',
            default => null,
        };
        if ($key === null) {
            return;
        }

        $netIncome = (float) ($this->budgetData['net_income'] ?? 0);
        $suggested = match ($bucket) {
            'investments' => round($netIncome * 0.10, 2),
            'savings' => round($netIncome * 0.07, 2),
            default => 0.0,
        };

        $this->budgetData[$key][] = ['label' => '', 'amount' => $suggested];
        $this->recalcBudget();
    }

    public function removeBudgetLine(string $bucket, int $index): void
    {
        if ($this->budgetData === null) {
            return;
        }
        $key = match ($bucket) {
            'fixed_costs', 'investments', 'savings' => $bucket.'_lines',
            default => null,
        };
        if ($key === null || ! isset($this->budgetData[$key][$index])) {
            return;
        }

        array_splice($this->budgetData[$key], $index, 1);
        $this->recalcBudget();
    }

    /**
     * Recomputa subtotais/totais/lazer a partir do state atual.
     * Aritmética em memória, dispara em todo edit via
     * updatedBudgetData() — usuário vê o delta do lazer settando
     * enquanto digita.
     */
    public function recalcBudget(): void
    {
        if ($this->budgetData === null) {
            return;
        }

        $sumLines = fn (array $lines) => array_sum(array_map(
            fn ($line) => (float) ($line['amount'] ?? 0),
            $lines,
        ));

        $netIncome = (float) ($this->budgetData['net_income'] ?? 0);
        $fixedSubtotal = $sumLines($this->budgetData['fixed_costs_lines'] ?? []);
        $bufferPct = Budget::FIXED_COSTS_BUFFER_PCT;
        $fixedTotal = round($fixedSubtotal * (1 + $bufferPct / 100), 2);
        $investmentsTotal = $sumLines($this->budgetData['investments_lines'] ?? []);
        $savingsTotal = $sumLines($this->budgetData['savings_lines'] ?? []);
        $leisure = round($netIncome - $fixedTotal - $investmentsTotal - $savingsTotal, 2);

        $this->budgetData['fixed_costs_subtotal'] = round($fixedSubtotal, 2);
        $this->budgetData['fixed_costs_total'] = $fixedTotal;
        $this->budgetData['investments_total'] = round($investmentsTotal, 2);
        $this->budgetData['savings_total'] = round($savingsTotal, 2);
        $this->budgetData['leisure_amount'] = $leisure;
    }

    /**
     * Persiste o state atual como NOVO Budget row — snapshots são
     * imutáveis, cada save cria uma row e "current" é o mais recente.
     * Mesmo padrão do BudgetSnapshot tool, preserva histórico.
     */
    public function saveBudget(): void
    {
        if ($this->budgetData === null) {
            return;
        }

        $fixedBreakdown = $this->linesToBreakdown($this->budgetData['fixed_costs_lines'] ?? []);
        $investmentsBreakdown = $this->linesToBreakdown($this->budgetData['investments_lines'] ?? []);
        $savingsBreakdown = $this->linesToBreakdown($this->budgetData['savings_lines'] ?? []);

        $fixedSubtotal = array_sum($fixedBreakdown);
        $bufferPct = Budget::FIXED_COSTS_BUFFER_PCT;
        $fixedTotal = round($fixedSubtotal * (1 + $bufferPct / 100), 2);
        $investmentsTotal = array_sum($investmentsBreakdown);
        $savingsTotal = array_sum($savingsBreakdown);
        $netIncome = (float) ($this->budgetData['net_income'] ?? 0);
        $leisure = round($netIncome - $fixedTotal - $investmentsTotal - $savingsTotal, 2);

        $new = Budget::create([
            'goal_id' => null,
            'month' => (string) $this->budgetData['month'],
            'net_income' => $netIncome,
            'fixed_costs_breakdown' => $fixedBreakdown ?: null,
            'fixed_costs_subtotal' => $fixedSubtotal,
            'fixed_costs_total' => $fixedTotal,
            'investments_breakdown' => $investmentsBreakdown ?: null,
            'investments_total' => $investmentsTotal,
            'savings_breakdown' => $savingsBreakdown ?: null,
            'savings_total' => $savingsTotal,
            'leisure_amount' => $leisure,
        ]);

        $this->budgetData['id'] = $new->id;

        Notification::make()
            ->title((string) __('coach.budget_flyout.saved'))
            ->success()
            ->send();
    }

    /**
     * Livewire lifecycle hook — dispara em toda mutação de budgetData
     * vinda do cliente (wire:model.live nos cells). Mantém derived
     * fields em sync enquanto o user digita.
     */
    public function updatedBudgetData(): void
    {
        $this->recalcBudget();
    }

    /**
     * Bucket status pill: percent of net income, in-range icon,
     * human target label. Mirra o semantic ✓/⚠ da BudgetSnapshot
     * tool — flyout e output do agente lêem os mesmos alvos.
     *
     * @return array{pct:int, target:string, in_range:bool}
     */
    public function bucketStatus(string $bucket, float $total): array
    {
        $netIncome = (float) ($this->budgetData['net_income'] ?? 0);
        $pct = $netIncome > 0 ? (int) round(($total / $netIncome) * 100) : 0;
        $range = Budget::TARGET_RANGES[$bucket] ?? null;
        $target = $range === null
            ? ''
            : ($range['min'] === $range['max'] ? $range['min'].'%' : $range['min'].'-'.$range['max'].'%');
        $inRange = $range !== null && $pct >= $range['min'] && $pct <= $range['max'];

        return ['pct' => $pct, 'target' => $target, 'in_range' => $inRange];
    }

    /**
     * "2026-12" → "dez/2026". Fallback no ISO raw se não conseguir
     * parsear, pra não produzir valor date-like que mente.
     */
    public function prettyMonth(string $iso): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $iso, $m)) {
            return $iso;
        }
        $names = ['01' => 'jan', '02' => 'fev', '03' => 'mar', '04' => 'abr', '05' => 'mai', '06' => 'jun', '07' => 'jul', '08' => 'ago', '09' => 'set', '10' => 'out', '11' => 'nov', '12' => 'dez'];

        return ($names[$m[2]] ?? $m[2]).'/'.$m[1];
    }

    /**
     * Converte breakdown label-keyed (shape persistido) → indexed
     * line shape (shape de edit na UI). Amounts em float pra que o
     * type-coerce da view não surpreenda.
     *
     * @param  mixed  $breakdown
     * @return list<array{label:string,amount:float}>
     */
    protected function breakdownToLines($breakdown): array
    {
        if (! is_array($breakdown)) {
            return [];
        }

        $lines = [];
        foreach ($breakdown as $label => $amount) {
            $lines[] = ['label' => (string) $label, 'amount' => (float) $amount];
        }

        return $lines;
    }

    /**
     * Inverso — pra hora de persistir. Dropa linhas com label vazio
     * ou amount zero/negative, pra que linhas rascunho que o user
     * não preencheu não poluam o snapshot.
     *
     * @param  array<int,array<string,mixed>>  $lines
     * @return array<string,float>
     */
    protected function linesToBreakdown(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            $label = trim((string) ($line['label'] ?? ''));
            $amount = (float) ($line['amount'] ?? 0);
            if ($label !== '' && $amount > 0) {
                $out[$label] = $amount;
            }
        }

        return $out;
    }
}
