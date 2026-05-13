<?php

namespace App\Console\Commands;

use App\Ai\Agents\CoachAgent;
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

            $coach = (new CoachAgent)->forUser($user)->forGoal($financeGoal?->id);

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
            [System] It's the 28th. Send an email reminding the user to update the Budget Planner for month {$thisMonth}.

            Goal: {$goalName}
            Last snapshot: {$lastMonth} —
              Net income {$lastIncome},
              Fixed Costs {$lastFixed},
              Investments {$lastInvest},
              Reserves {$lastSavings},
              Leisure {$lastLeisure}
            (values formatted per the user's locale)

            Generate the email BODY (markdown), 4-6 sentences, in the user's language.

            RULES:
            - Tone: friendly and firm, no "hello" or "hope you're well". Use the locale-aware voice from the system prompt.
            - Cite 1-2 numbers from the previous snapshot for context.
            - Ask them to reply to THIS email with updated income + expenses ("just reply right here").
            - End with a short encouraging sentence.
            - DO NOT sign, DO NOT add a subject, DO NOT add a corporate greeting.
            - DO NOT call any tools — just generate text.
            PROMPT;
        }

        // intro: has a finance goal but never used BudgetSnapshot
        return <<<PROMPT
        [System] It's the 28th. The user has a finance goal ('{$goalName}') but hasn't used the Budget Planner yet. Send an invitation email to start for {$thisMonth}.

        Generate the email BODY (markdown), 5-7 sentences, in the user's language.

        RULES:
        - Tone: invitation, no pressure. Use the locale-aware voice from the system prompt.
        - Explain the framework in 2-3 sentences: 4 buckets (Fixed Costs / Investments / Reserves / Leisure), applies 15% buffer over fixed costs, calculates what's left for Leisure, shows percentages vs targets (50-60 / 10 / 5-10 / 20-35).
        - Ask them to reply to THIS email with income + a sense of monthly expenses ("just reply with your net income and main expenses").
        - End with an invitation — "want to start?".
        - DO NOT sign, DO NOT add a subject, DO NOT add a corporate greeting.
        - DO NOT call any tools — just generate text.
        PROMPT;
    }
}
