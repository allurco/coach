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

#[Signature('coach:morning-ping {--user= : User ID (defaults to first user)} {--dry : Print result instead of sending email}')]
#[Description('Briefing matinal do coach: foco do dia + reconhecimento do que foi feito')]
class CoachMorningPing extends Command
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

        $this->info("Generating morning briefing for {$user->email}…");

        $prompt = <<<'PROMPT'
            Send a morning briefing in the style of a personal coach.

            RULES:
            - Use ListActions to see the actual current state of the plan.
            - Identify THE SINGLE most important thing today (not 3, not 5 — ONE).
            - If something is overdue, acknowledge it without judging.
            - Acknowledge a recent win if there is one (action completed yesterday/day before).
            - 4-6 sentences MAX.
            - Tone: direct, friendly. Use the locale-aware voice from the system prompt.
            - Don't use "hello", "I hope", "how are you?".
            - Start with today's focus, no preamble.
            - End with a short question or call-to-action.

            Don't call RememberFact here — this is a briefing, not an analysis.
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

            $heading = 'Foco do dia';

            if ($this->option('dry')) {
                $this->newLine();
                $this->line('--- Subject: ☀️ '.$heading.' ---');
                $this->line($body);

                return self::SUCCESS;
            }

            Mail::to($user->email)->send(new CoachPing(
                kind: 'morning',
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
