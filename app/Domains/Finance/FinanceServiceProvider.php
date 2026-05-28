<?php

namespace App\Domains\Finance;

use App\Domains\Finance\Console\Commands\CoachCarryBudgetForward;
use App\Domains\Finance\Console\Commands\CoachMonthlyBudgetReminder;
use App\Domains\Finance\Livewire\BudgetTool;
use App\Domains\Finance\Models\Budget;
use App\Domains\Finance\Tips\RefreshBudget;
use App\Domains\Finance\Tips\SetUpBudget;
use App\Models\Goal;
use App\Models\User;
use App\Packs\DomainPack;
use App\Services\TipResolver;
use App\Tools\Tool;
use App\Tools\ToolRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Livewire\Livewire;

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
        $this->loadViewsFrom(__DIR__.'/resources/views', 'finance');

        // Pack-owned Livewire component (not in app/Livewire, so not
        // auto-discovered) — register its alias so <livewire:budget-tool />
        // and the ToolRegistry 'budget-tool' descriptor resolve.
        Livewire::component('budget-tool', BudgetTool::class);

        // Contribute the pack's Tips to Core's catalog without Core having
        // to know they exist (ADR 0006). The resolver sorts by priority(),
        // so append order is irrelevant.
        $this->app->extend(TipResolver::class, function (TipResolver $resolver) {
            return $resolver->register(new SetUpBudget, new RefreshBudget);
        });

        // Contribute the Budget Tool to the workspace — the Finance pack's
        // primary Tool, shown only on finance Goals (ADR 0007).
        $this->app->extend(ToolRegistry::class, function (ToolRegistry $registry) {
            return $registry->register(new Tool(
                key: 'budget',
                label: 'finance::budget_flyout.toggle',
                icon: 'heroicon-o-wallet',
                component: 'budget-tool',
                isPrimary: true,
                scope: 'finance',
            ));
        });

        // The pack owns its scheduled jobs end to end — registration and
        // schedule both live here, not in Core's routes/console.php (ADR 0006).
        $this->commands([
            CoachCarryBudgetForward::class,
            CoachMonthlyBudgetReminder::class,
        ]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            // Carry-forward do orçamento: dia 28 às 06h, ANTES do reminder
            // das 19h. Idempotente: rodar 2x não duplica.
            $schedule->command('coach:carry-budget-forward')
                ->monthlyOn(28, '06:00')
                ->timezone('America/Fortaleza')
                ->withoutOverlapping()
                ->onOneServer()
                ->emailOutputOnFailure(env('COACH_NOTIFICATION_EMAIL'));

            // Lembrete mensal do Planejador Financeiro: dia 28 às 19h,
            // DEPOIS do carry-forward, então o user já vê o snapshot
            // pré-populado pra ajustar.
            $schedule->command('coach:monthly-budget-reminder')
                ->monthlyOn(28, '19:00')
                ->timezone('America/Fortaleza')
                ->withoutOverlapping()
                ->onOneServer()
                ->emailOutputOnFailure(env('COACH_NOTIFICATION_EMAIL'));
        });
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
