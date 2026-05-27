<?php

namespace App\Domains\Finance;

use App\Domains\Finance\Models\Budget;
use App\Models\Goal;
use App\Models\User;
use App\Packs\DomainPack;

/**
 * The Finance Domain Pack — Coach's first pack and the reference
 * implementation for the contract defined in CONTEXT.md.
 *
 * Owns:
 *  - Budget model + finance_budgets table
 *  - ReadBudget + BudgetSnapshot tools
 *  - Pack-local lang under app/Domains/Finance/lang/, accessed via
 *    __('finance::budget.*'), __('finance::budget_flyout.*'),
 *    __('finance::read_budget.*'), __('finance::signal.*')
 *  - The Finance signal: a one-line summary of the user's current
 *    budget delta, surfaced to the agent on every prompt (ADR 0002).
 */
class FinanceServiceProvider extends DomainPack
{
    public function label(): string
    {
        return 'finance';
    }

    public function boot(): void
    {
        parent::boot();

        $this->loadTranslationsFrom(__DIR__.'/lang', 'finance');
    }

    public function contributeSignal(User $user, ?Goal $activeGoal = null): ?string
    {
        $budget = Budget::currentForUser($user->id);

        if ($budget === null) {
            return (string) __('finance::signal.none');
        }

        $delta = $budget->monthly_delta;
        $args = [
            'month' => (string) $budget->month,
            'amount' => 'R$ '.number_format(abs($delta), 0, ',', '.'),
        ];

        return match (true) {
            $delta > 0 => (string) __('finance::signal.surplus', $args),
            $delta < 0 => (string) __('finance::signal.deficit', $args),
            default => (string) __('finance::signal.balanced', $args),
        };
    }
}
