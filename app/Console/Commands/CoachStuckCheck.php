<?php

namespace App\Console\Commands;

use App\Ai\Agents\FinanceCoach;
use App\Mail\CoachPing;
use App\Models\Action;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Laravel\Ai\Enums\Lab;

#[Signature('coach:stuck-check {--user= : User ID (defaults to first user)} {--days=3 : Days an action must be untouched to count as stuck} {--dry : Print result instead of sending email}')]
#[Description('Detecta ações paradas há N dias e pinga proativamente, perguntando o que está travando')]
class CoachStuckCheck extends Command
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

        // Authenticate so the Action global scope filters to this user's plan,
        // and so any tools the agent calls also see only this user's data.
        auth()->login($user);

        $days = (int) $this->option('days');

        $stuckActions = Action::query()
            ->where('status', 'pendente')
            ->where(function ($q) {
                $q->whereNull('snooze_until')
                    ->orWhereDate('snooze_until', '<=', now());
            })
            ->where('updated_at', '<=', now()->subDays($days))
            ->where('importance', '!=', 'rotineiro')
            ->orderBy('priority', 'desc')
            ->orderBy('updated_at', 'asc')
            ->limit(3)
            ->get();

        if ($stuckActions->isEmpty()) {
            $this->info("Nada parado há mais de {$days} dias. Sem ping.");

            return self::SUCCESS;
        }

        $this->info("Encontradas {$stuckActions->count()} ações paradas há {$days}+ dias. Gerando ping…");

        $list = $stuckActions->map(fn (Action $a) => sprintf(
            '#%d "%s" (categoria: %s, prazo: %s, parada há %d dias)',
            $a->id,
            $a->title,
            $a->category,
            $a->deadline?->format('d/m/Y') ?? 'sem prazo',
            (int) $a->updated_at->diffInDays(now()),
        ))->implode("\n");

        $prompt = <<<PROMPT
            O Rogers tem ações paradas há mais de {$days} dias. Manda um ping curto pra ele.

            Ações paradas (top 3 mais importantes):
            {$list}

            REGRAS:
            - Pegue A ÚNICA ação mais crítica dessa lista (não tente cobrir todas).
            - Pergunta CURTA: o que está travando? Não cobre, não dá lição.
            - Sem "olá", sem rodeio. Tipo: "X tá há N dias parada — o que tá pegando?".
            - Tom amigo, sem julgar, brasileiro coloquial.
            - 2-4 frases NO MÁXIMO.
            - Pode oferecer ajuda concreta se fizer sentido (ex: "quer que eu ajude a destrinchar?").

            Não chame nenhuma tool — você já tem todas as informações necessárias acima.
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

            $heading = "Tem coisa parada há {$days}+ dias";

            if ($this->option('dry')) {
                $this->newLine();
                $this->line('--- Subject: ⏳ '.$heading.' ---');
                $this->line($body);

                return self::SUCCESS;
            }

            Mail::to($user->email)->send(new CoachPing(
                kind: 'stuck',
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
