<?php

namespace App\Ai\Tools;

use App\Models\CoachMemory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RememberFact implements Tool
{
    public function description(): Stringable|string
    {
        return 'Salva um fato importante na MEMÓRIA DE LONGO PRAZO do Rogers. '
            .'Use depois de analisar uma fatura/extrato/PDF, decisão tomada, ou evento marcante — '
            .'para que conversas FUTURAS possam recordar que aconteceu sem precisar do PDF original. '
            .'O summary deve ser CURTO (1-3 frases), factual, com valores e datas concretas. '
            .'Não use pra coisas triviais — só pra fatos que vale consultar depois. '
            .'Tipos: fatura, pagamento, decisao, evento, fato, meta, aprendizado.';
    }

    public function handle(Request $request): Stringable|string
    {
        $userId = auth()->id();
        if (! $userId) {
            return 'Erro: usuário não autenticado.';
        }

        $memory = CoachMemory::create([
            'user_id' => $userId,
            'kind' => $request['kind'] ?? 'fato',
            'label' => $request['label'],
            'summary' => $request['summary'],
            'details' => $request['details'] ?? null,
            'event_date' => $request['event_date'] ?? now()->toDateString(),
            'source_action_id' => $request['source_action_id'] ?? null,
            'is_active' => true,
        ]);

        return sprintf(
            'Fato salvo na memória de longo prazo (#%d, %s): "%s".',
            $memory->id,
            $memory->kind,
            $memory->label,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()
                ->enum(['fatura', 'pagamento', 'decisao', 'evento', 'fato', 'meta', 'aprendizado']),
            'label' => $schema->string()->required(),
            'summary' => $schema->string()->required(),
            'event_date' => $schema->string(),
            'source_action_id' => $schema->integer(),
        ];
    }
}
