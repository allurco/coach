<?php

namespace App\Console\Commands;

use App\Ai\Agents\FinanceCoach;
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

        $this->info("Gerando briefing matinal para {$user->email}…");

        $prompt = <<<'PROMPT'
            Manda um briefing matinal pro Rogers, no estilo de um coach pessoal.

            REGRAS:
            - Use ListActions pra ver o estado real do plano agora.
            - Identifique A ÚNICA coisa mais importante hoje (não 3, não 5 — UMA).
            - Se tem algo atrasado, reconhece sem julgar.
            - Reconhece win recente se houver (ação concluída ontem/anteontem).
            - 4-6 frases NO MÁXIMO.
            - Tom: direto, brasileiro coloquial, amigo firme. Não use "olá", "espero que", "tudo bem?".
            - Comece com o foco do dia, sem rodeio.
            - Termine com uma pergunta curta ou call-to-action.

            Não chame RememberFact aqui — isso é um briefing, não uma análise.
            PROMPT;

        try {
            $coach = (new FinanceCoach)->forUser($user);
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
