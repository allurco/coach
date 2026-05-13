<?php

namespace App\Console\Commands;

use App\Ai\Agents\CoachAgent;
use App\Mail\CoachPing;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Laravel\Ai\Enums\Lab;

#[Signature('coach:weekly-briefing {--user= : User ID (defaults to first user)} {--dry : Print result instead of sending email}')]
#[Description('Recap semanal: o que foi feito, o que falta, foco da próxima semana')]
class CoachWeeklyBriefing extends Command
{
    public function handle(): int
    {
        $user = $this->option('user')
            ? User::find($this->option('user'))
            : User::first();

        if (! $user) {
            $this->error('Nenhum usuário encontrado.');

            return self::FAILURE;
        }

        // Authenticate so the Action global scope filters to this user's plan.
        auth()->login($user);

        $this->info("Generating weekly recap for {$user->email}…");

        $prompt = <<<'PROMPT'
            Send a weekly recap on Sunday night.

            RULES:
            - Use ListActions to see the current state.
            - Structure in 3 short blocks:
              1. **What happened this week** — 2-3 concrete wins (completed actions or real progress).
                 If nothing concrete happened, say so without sugar-coating.
              2. **What's weighing** — 1-2 actions that are stuck or overdue. Action name + why it matters.
              3. **Focus for next week** — 1 main action to tackle.
            - Tone: curious friend, propositive, DOES NOT invent urgency. Use the locale-aware voice from the system prompt.
            - End with an open question like "how do you see this week?"
              or "anything that blocked you that's worth talking through?"
            - 8-12 sentences MAX.
            - Use markdown (bold, lists) — it'll become HTML in the email.

            Don't call RememberFact here — a recap is reading, not consolidation.
            PROMPT;

        try {
            $coach = (new CoachAgent)->forUser($user);
            $response = $coach->prompt($prompt, provider: Lab::Gemini, model: 'gemini-2.5-flash');

            $body = trim((string) $response);
            $conversationId = $coach->currentConversation();

            if ($body === '') {
                $this->error('Coach retornou vazio.');

                return self::FAILURE;
            }

            $heading = 'Recap da semana';

            if ($this->option('dry')) {
                $this->newLine();
                $this->line('--- Subject: 📊 '.$heading.' ---');
                $this->line($body);

                return self::SUCCESS;
            }

            Mail::to($user->email)->send(new CoachPing(
                kind: 'weekly',
                body: $body,
                heading: $heading,
                conversationId: $conversationId,
            ));

            $this->info("Email enviado pra {$user->email}.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Erro: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
