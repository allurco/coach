<?php

namespace App\Ai\Tools;

use App\Models\Action;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateAction implements Tool
{
    public function description(): Stringable|string
    {
        return 'Atualiza uma ação existente: muda status, adiciona notas de resultado, '
            .'ajusta prazo ou adia (snooze). Use após o Rogers confirmar que algo foi feito, '
            .'cancelado, ou que o prazo precisa mudar. Use ListActions primeiro para descobrir o ID correto.';
    }

    public function handle(Request $request): Stringable|string
    {
        $action = Action::find($request['id']);

        if (! $action) {
            return "Ação com ID {$request['id']} não encontrada.";
        }

        $changes = [];

        if (! empty($request['status'])) {
            $action->status = $request['status'];
            $changes[] = "status → {$request['status']}";

            if ($request['status'] === 'concluido') {
                $action->completed_at = now();
                $changes[] = 'completed_at → agora';
            } elseif ($action->completed_at !== null) {
                // Re-opening or cancelling a previously concluded action: clear timestamp.
                $action->completed_at = null;
                $changes[] = 'completed_at → null';
            }
        }

        if (! empty($request['result_notes'])) {
            $action->result_notes = $request['result_notes'];
            $changes[] = 'notas adicionadas';
        }

        if (array_key_exists('deadline', (array) $request) && $request['deadline'] !== null) {
            $action->deadline = $this->parseRelativeDate($request['deadline']) ?: null;
            $changes[] = 'deadline atualizado';
        }

        if (! empty($request['snooze_until'])) {
            $action->snooze_until = $this->parseRelativeDate($request['snooze_until']);
            $changes[] = "adiada até {$action->snooze_until?->format('d/m/Y')}";
        }

        $action->save();

        return sprintf(
            'Ação #%d "%s" atualizada: %s.',
            $action->id,
            $action->title,
            implode(', ', $changes) ?: 'sem mudanças',
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'status' => $schema->string()
                ->enum(['pendente', 'em_andamento', 'concluido', 'cancelado']),
            'result_notes' => $schema->string(),
            'deadline' => $schema->string(),
            'snooze_until' => $schema->string(),
        ];
    }

    protected function parseRelativeDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        $value = trim(strtolower($value));

        if (preg_match('/^(\d+)\s*([dwmy])$/', $value, $m)) {
            $n = (int) $m[1];

            return match ($m[2]) {
                'd' => now()->addDays($n)->toDateString(),
                'w' => now()->addWeeks($n)->toDateString(),
                'm' => now()->addMonths($n)->toDateString(),
                'y' => now()->addYears($n)->toDateString(),
            };
        }

        $kw = [
            'today' => 0, 'hoje' => 0, 'tomorrow' => 1, 'amanhã' => 1, 'amanha' => 1,
            'next week' => 7, 'próxima semana' => 7, 'proxima semana' => 7,
            'next month' => 30, 'próximo mês' => 30, 'proximo mes' => 30,
        ];
        if (isset($kw[$value])) {
            return now()->addDays($kw[$value])->toDateString();
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
