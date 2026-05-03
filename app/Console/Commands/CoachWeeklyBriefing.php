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

        $this->info("Gerando recap semanal para {$user->email}…");

        $prompt = <<<'PROMPT'
            Manda um recap semanal pro Rogers, no domingo à noite.

            REGRAS:
            - Use ListActions pra ver o estado atual.
            - Estrutura em 3 blocos curtos:
              1. **Que rolou esta semana** — 2-3 wins concretos (ações concluídas ou progresso real).
                 Se não rolou nada concreto, fala isso sem maquiar.
              2. **O que tá pesando** — 1-2 ações que estão paradas ou atrasadas. Nome da ação + por que importa.
              3. **Foco da próxima semana** — 1 ação principal pra ele atacar.
            - Tom: amigo curioso, propositivo, NÃO inventa urgência.
            - Termine com uma pergunta aberta tipo "como você tá vendo essa semana?"
              ou "tem algo que te travou que vale conversar?"
            - 8-12 frases NO MÁXIMO.
            - Use markdown (negrito, listas) — vai virar HTML no email.

            Não chame RememberFact aqui — recap é leitura, não consolidação.
            PROMPT;

        try {
            $response = (new FinanceCoach)
                ->forUser($user)
                ->prompt($prompt, provider: Lab::Gemini, model: 'gemini-2.5-flash');

            $body = trim((string) $response);

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
            ));

            $this->info("Email enviado pra {$user->email}.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Erro: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
