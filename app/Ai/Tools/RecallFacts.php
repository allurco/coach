<?php

namespace App\Ai\Tools;

use App\Models\CoachMemory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RecallFacts implements Tool
{
    public function description(): Stringable|string
    {
        return 'Consulta a MEMÓRIA DE LONGO PRAZO do Rogers — fatos guardados em conversas anteriores '
            .'(faturas analisadas, pagamentos feitos, decisões tomadas, etc.). '
            .'Use quando o Rogers fizer referência a algo que aconteceu antes, ou quando precisar '
            .'de contexto histórico pra responder. Filtra por tipo (fatura, pagamento, decisao, etc.) ou '
            .'busca por palavra-chave.';
    }

    public function handle(Request $request): Stringable|string
    {
        $userId = auth()->id();
        if (! $userId) {
            return 'Erro: usuário não autenticado.';
        }

        $query = CoachMemory::where('user_id', $userId)->where('is_active', true);

        if (! empty($request['kind'])) {
            $query->where('kind', $request['kind']);
        }

        if (! empty($request['search'])) {
            $term = $request['search'];
            $query->where(function ($q) use ($term) {
                $q->where('label', 'like', "%{$term}%")
                    ->orWhere('summary', 'like', "%{$term}%");
            });
        }

        $memories = $query
            ->orderBy('event_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get();

        if ($memories->isEmpty()) {
            return 'Nenhum fato encontrado na memória de longo prazo com esses filtros.';
        }

        $lines = $memories->map(function (CoachMemory $m) {
            $date = $m->event_date?->format('d/m/Y') ?? $m->created_at->format('d/m/Y');

            return sprintf('[%s|%s] %s — %s', $date, $m->kind, $m->label, $m->summary);
        })->implode("\n");

        return $lines;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()
                ->enum(['fatura', 'pagamento', 'decisao', 'evento', 'fato', 'meta', 'aprendizado']),
            'search' => $schema->string(),
        ];
    }
}
