<?php

namespace App\Console\Commands;

use App\Ai\Agents\FinanceCoach;
use App\Mail\BudgetReminder;
use App\Models\Budget;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Ai\Enums\Lab;
use Throwable;

#[Signature('coach:monthly-budget-reminder {--user= : Send only for this user id} {--dry : Print result instead of sending email}')]
#[Description('Lembrete mensal de Planejador Financeiro — dia 28, 19h. Só pra users com goal de finance.')]
class CoachMonthlyBudgetReminder extends Command
{
    public function handle(): int
    {
        $users = $this->option('user')
            ? User::where('id', $this->option('user'))->get()
            : $this->eligibleUsers();

        if ($users->isEmpty()) {
            $this->info('Nenhum usuário elegível.');

            return self::SUCCESS;
        }

        $this->info("Disparando pra {$users->count()} usuário(s)…");

        foreach ($users as $user) {
            try {
                $this->sendForUser($user);
            } catch (Throwable $e) {
                Log::error('Coach monthly budget reminder failed', [
                    'user_id' => $user->id,
                    'message' => $e->getMessage(),
                ]);
                $this->error("  ✗ {$user->email}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Users that get the monthly budget reminder: any user with at least
     * one active finance goal AND who has accepted their invitation
     * (has a password). Returned deduplicated even when the user owns
     * multiple finance goals.
     */
    protected function eligibleUsers(): Collection
    {
        return User::query()
            ->whereNotNull('email')
            ->whereNotNull('password')
            ->whereExists(function ($query) {
                $query->from('goals')
                    ->whereColumn('goals.user_id', 'users.id')
                    ->where('goals.label', 'finance')
                    ->where('goals.is_archived', false);
            })
            ->get();
    }

    /**
     * Generate + send the reminder for a single user. Uses the agent to
     * draft a localized body so wording adapts to user.locale; captures
     * the resulting conversation id for Reply-To so the user can reply
     * by email and the reply threads back into the same finance
     * conversation.
     */
    protected function sendForUser(User $user): void
    {
        // Authenticate so global scopes (Goal, Budget) filter to this user.
        auth()->login($user);

        try {
            $financeGoal = Goal::where('label', 'finance')
                ->where('is_archived', false)
                ->orderByRaw('updated_at desc')
                ->first();

            $latestBudget = $financeGoal
                ? Budget::where('goal_id', $financeGoal->id)
                    ->orderBy('created_at', 'desc')
                    ->first()
                : null;

            $kind = $latestBudget !== null ? 'recurring' : 'intro';

            $prompt = $this->buildPrompt($kind, $financeGoal, $latestBudget);

            $coach = (new FinanceCoach)->forUser($user)->forGoal($financeGoal?->id);

            // Localize the rendering: the agent's prompt uses the user's
            // own language so the email body matches user.locale.
            $body = trim((string) $coach->prompt(
                $prompt,
                provider: Lab::Gemini,
                model: config('coach.models.background'),
            ));

            if ($body === '') {
                $this->error("  ✗ {$user->email}: agente devolveu corpo vazio");

                return;
            }

            $conversationId = $coach->currentConversation();

            if ($this->option('dry')) {
                $this->newLine();
                $this->line("--- {$user->email} ({$kind}) ---");
                $this->line($body);

                return;
            }

            Mail::to($user->email)
                ->locale($user->locale ?? config('app.locale'))
                ->send(new BudgetReminder(
                    kind: $kind,
                    body: $body,
                    conversationId: $conversationId,
                ));

            $this->info("  ✓ {$user->email} ({$kind})");
        } finally {
            auth()->logout();
        }
    }

    protected function buildPrompt(string $kind, ?Goal $goal, ?Budget $latest): string
    {
        $goalName = $goal?->name ?? 'finance';
        $thisMonth = now()->translatedFormat('F/Y');

        if ($kind === 'recurring' && $latest) {
            $lastMonth = $latest->month;
            $lastIncome = number_format((float) $latest->net_income, 2, ',', '.');
            $lastFixed = number_format((float) $latest->fixed_costs_total, 2, ',', '.');
            $lastInvest = number_format((float) $latest->investments_total, 2, ',', '.');
            $lastSavings = number_format((float) $latest->savings_total, 2, ',', '.');
            $lastLeisure = number_format((float) $latest->leisure_amount, 2, ',', '.');

            return <<<PROMPT
            [Sistema] Hoje é dia 28. Quero mandar um email pro usuário lembrando de atualizar o Planejador Financeiro do mês {$thisMonth}.

            Goal: {$goalName}
            Último snapshot: {$lastMonth} —
              Renda líquida R$ {$lastIncome},
              Custos Fixos R$ {$lastFixed},
              Investimentos R$ {$lastInvest},
              Reservas R$ {$lastSavings},
              Lazer R$ {$lastLeisure}

            Gere o CORPO do email (markdown), 4-6 frases, no idioma do usuário.

            REGRAS:
            - Tom: amigo firme, sem "olá" nem "espero que esteja bem".
            - Cite 1-2 números do snapshot anterior pra contexto.
            - Peça pra ele responder ESSE email com a renda + gastos atualizados ("é só responder aqui mesmo").
            - Termine com uma frase curta de incentivo.
            - NÃO assine, NÃO adicione subject, NÃO adicione saudação corporativa.
            - NÃO chame nenhuma tool — só gere texto.
            PROMPT;
        }

        // intro: tem finance goal mas nunca usou BudgetSnapshot
        return <<<PROMPT
        [Sistema] Hoje é dia 28. O usuário tem um goal de finance ('{$goalName}') mas ainda não usou o Planejador Financeiro. Quero mandar um email convidando ele a começar pra {$thisMonth}.

        Gere o CORPO do email (markdown), 5-7 frases, no idioma do usuário.

        REGRAS:
        - Tom: convite, sem pressão.
        - Explique em 2-3 frases o framework: 4 caixas (Custos Fixos / Investimentos / Reservas / Lazer), aplica buffer de 15% nos fixos, calcula quanto sobra pra Lazer, mostra os % vs alvos (50-60 / 10 / 5-10 / 20-35).
        - Peça pra ele responder ESSE email com renda + uma noção dos gastos do mês ("é só responder com a sua renda líquida e os principais gastos").
        - Termine convidando — "topa começar?".
        - NÃO assine, NÃO adicione subject, NÃO adicione saudação corporativa.
        - NÃO chame nenhuma tool — só gere texto.
        PROMPT;
    }
}
