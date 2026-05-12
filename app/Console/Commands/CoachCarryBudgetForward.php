<?php

namespace App\Console\Commands;

use App\Models\Budget;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('coach:carry-budget-forward')]
#[Description('Dia 28: copia o budget mais recente de cada user pro próximo mês — o user só ajusta o que mudou.')]
class CoachCarryBudgetForward extends Command
{
    public function handle(): int
    {
        $nextMonth = now()->addMonth()->format('Y-m');

        // Pega todo user que tem PELO MENOS UM budget — esses são os
        // candidatos. Pra cada um, achamos o snapshot mais recente e
        // criamos um pro próximo mês se ainda não existir.
        $userIds = Budget::withoutGlobalScope('owner')
            ->pluck('user_id')
            ->unique()
            ->values();

        $copied = 0;
        $skipped = 0;

        foreach ($userIds as $userId) {
            try {
                if ($this->carryForwardForUser((int) $userId, $nextMonth)) {
                    $copied++;
                } else {
                    $skipped++;
                }
            } catch (Throwable $e) {
                Log::error('Coach carry-budget-forward failed for user', [
                    'user_id' => $userId,
                    'message' => $e->getMessage(),
                ]);
                $this->error("  ✗ user {$userId}: {$e->getMessage()}");
            }
        }

        $this->info("Carried forward: {$copied}, skipped (already had / no source): {$skipped}");

        return self::SUCCESS;
    }

    /**
     * Retorna true se um novo snapshot foi criado, false caso skipped.
     * Skip acontece quando o user já tem snapshot pro nextMonth (manual
     * ou de uma corrida anterior — idempotente).
     */
    protected function carryForwardForUser(int $userId, string $nextMonth): bool
    {
        $alreadyHasNext = Budget::withoutGlobalScope('owner')
            ->where('user_id', $userId)
            ->where('month', $nextMonth)
            ->exists();

        if ($alreadyHasNext) {
            return false;
        }

        $latest = Budget::withoutGlobalScope('owner')
            ->where('user_id', $userId)
            ->orderByDesc('month')
            ->orderByDesc('id')
            ->first();

        if ($latest === null) {
            return false;
        }

        Budget::withoutGlobalScope('owner')->create([
            'user_id' => $userId,
            'goal_id' => $latest->goal_id,
            'month' => $nextMonth,
            'net_income' => $latest->net_income,
            'fixed_costs_subtotal' => $latest->fixed_costs_subtotal,
            'fixed_costs_total' => $latest->fixed_costs_total,
            'fixed_costs_breakdown' => $latest->fixed_costs_breakdown,
            'investments_total' => $latest->investments_total,
            'investments_breakdown' => $latest->investments_breakdown,
            'savings_total' => $latest->savings_total,
            'savings_breakdown' => $latest->savings_breakdown,
            'leisure_amount' => $latest->leisure_amount,
            // Notes ficam null — eram contexto do mês anterior; o user
            // adiciona o que for específico do novo mês.
            'notes' => null,
        ]);

        return true;
    }
}
