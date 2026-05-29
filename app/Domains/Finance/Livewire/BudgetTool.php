<?php

namespace App\Domains\Finance\Livewire;

use App\Domains\Finance\Models\Budget;
use App\Exceptions\ShareFailedException;
use App\Services\Sharer;
use Filament\Notifications\Notification;
use Livewire\Component;

/**
 * The Budget Tool — the Finance pack's primary workspace Tool (ADR 0007).
 * A header button opens a drawer to edit the current month's budget
 * snapshot inline (recalc as you type, save as a new snapshot) and share
 * it by email.
 *
 * Self-contained Livewire component, pack-owned (app/Domains/Finance/
 * Livewire/), registered as the 'budget-tool' alias in
 * FinanceServiceProvider. Replaces the old HasBudgetFlyout/HasBudgetShare
 * traits that were mixed into the Agent's Coach page — extracting them
 * here removes the Agent→Finance trait coupling.
 *
 * Open/close is local Alpine state (`budgetOpen`); the drawer + share
 * modal teleport to <body> so they stack above the app regardless of
 * where the component is embedded.
 */
class BudgetTool extends Component
{
    public bool $budgetOpen = false;

    /** Snapshot row for the flyout, hydrated by openBudget(). */
    public ?array $budgetData = null;

    /**
     * Embedded in the Workspace tool rail (ADR 0007): auto-hydrate the
     * current budget so the editor renders inline, no toggle button.
     */
    public function mount(): void
    {
        $this->openBudget();
    }

    // Share modal state.
    public bool $budgetShareOpen = false;

    public string $budgetShareRecipient = '';

    public string $budgetShareSubject = '';

    public string $budgetShareBody = '';

    public ?string $budgetShareError = null;

    /**
     * Drives whether the "Budget" toggle button renders. Lazy COUNT via
     * Budget::currentForUser, called once per render from the view.
     */
    public function hasBudget(): bool
    {
        return Budget::currentForUser((int) auth()->id()) !== null;
    }

    /**
     * Hydrate $budgetData from the user's current snapshot. Re-runs on
     * every open so the drawer always reflects the latest snapshot.
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
     * Add an editable line to one of the three buckets. Investments and
     * savings get a suggested value derived from income (10% / 7%); fixed
     * costs start at 0 — a wrong default is worse than empty there.
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
     * Recompute subtotals/totals/leisure from current state. In-memory
     * arithmetic, fires on every edit via updatedBudgetData() so the user
     * sees the leisure delta settle as they type.
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
     * Persist the current state as a NEW Budget row — snapshots are
     * immutable, each save creates a row and "current" is the most recent.
     * Same pattern as the BudgetSnapshot tool; preserves history.
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
            ->title((string) __('finance::budget_flyout.saved'))
            ->success()
            ->send();
    }

    /**
     * Livewire lifecycle hook — fires on every budgetData mutation from
     * the client (wire:model.live on the cells). Keeps derived fields in
     * sync while the user types.
     */
    public function updatedBudgetData(): void
    {
        $this->recalcBudget();
    }

    /**
     * Bucket status pill: percent of net income, in-range flag, human
     * target label. Mirrors the BudgetSnapshot tool's targets so the
     * flyout and the agent's output read the same ranges.
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
     * "2026-12" → "dez/2026". Falls back to the raw ISO if it can't parse,
     * so we never produce a date-like value that lies.
     */
    public function prettyMonth(string $iso): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $iso, $m)) {
            return $iso;
        }
        $names = ['01' => 'jan', '02' => 'fev', '03' => 'mar', '04' => 'abr', '05' => 'mai', '06' => 'jun', '07' => 'jul', '08' => 'ago', '09' => 'set', '10' => 'out', '11' => 'nov', '12' => 'dez'];

        return ($names[$m[2]] ?? $m[2]).'/'.$m[1];
    }

    // Share modal ---------------------------------------------------------

    /**
     * Open the share modal. Pre-fills the body with the {{budget:current}}
     * placeholder — PlaceholderRenderer expands it at send, so even if the
     * user saves a fresh snapshot between opening and sending, the
     * recipient gets the latest. Subject includes the month for context.
     */
    public function openBudgetShare(): void
    {
        if ($this->budgetData === null) {
            return;
        }

        $this->budgetShareOpen = true;
        $this->budgetShareRecipient = '';
        $this->budgetShareSubject = (string) __('finance::budget_flyout.share_subject_default', [
            'month' => (string) ($this->budgetData['month'] ?? ''),
        ]);
        $this->budgetShareBody = (string) __('finance::budget_flyout.share_body_default');
        $this->budgetShareError = null;
    }

    public function cancelBudgetShare(): void
    {
        $this->budgetShareOpen = false;
        $this->budgetShareRecipient = '';
        $this->budgetShareSubject = '';
        $this->budgetShareBody = '';
        $this->budgetShareError = null;
    }

    public function confirmBudgetShare(): void
    {
        if (! $this->budgetShareOpen) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            $this->budgetShareError = (string) __('coach.share.errors.unauthenticated');

            return;
        }

        try {
            $message = app(Sharer::class)->send(
                user: $user,
                to: $this->budgetShareRecipient,
                subject: $this->budgetShareSubject,
                body: $this->budgetShareBody,
            );

            Notification::make()
                ->title($message)
                ->success()
                ->send();

            $this->cancelBudgetShare();
        } catch (ShareFailedException $e) {
            $this->budgetShareError = $e->getMessage();
        }
    }

    /**
     * Convert a label-keyed breakdown (persisted shape) → indexed line
     * shape (edit shape in the UI). Amounts as float so the view's type
     * coercion doesn't surprise.
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
     * Inverse — for persisting. Drops lines with an empty label or
     * zero/negative amount, so draft lines the user didn't fill don't
     * pollute the snapshot.
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

    public function render()
    {
        return view('finance::budget-tool');
    }
}
