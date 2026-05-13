<?php

namespace App\Console\Commands;

use App\Ai\Agents\CoachAgent;
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
            $a->deadline?->format('Y-m-d') ?? 'no deadline',
            (int) $a->updated_at->diffInDays(now()),
        ))->implode("\n");

        $prompt = <<<PROMPT
            The user has actions stuck for more than {$days} days. Send a short ping.

            Stuck actions (top 3 most important):
            {$list}

            RULES:
            - Pick THE ONE most critical action from this list (don't try to cover all).
            - SHORT question: what's blocking? Don't lecture, don't moralize.
            - No "hello", no preamble. E.g.: "X has been stuck N days — what's getting in the way?".
            - Friendly tone, no judgment. Use the locale-aware voice from the system prompt.
            - 2-4 sentences MAX.
            - You can offer concrete help if it makes sense (e.g. "want me to help break it down?").

            Don't call any tools — you already have all the info you need above.
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
