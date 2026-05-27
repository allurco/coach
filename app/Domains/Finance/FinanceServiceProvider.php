<?php

namespace App\Domains\Finance;

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
 *    __('finance::read_budget.*')
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
}
