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
        return 'Saves an important fact in the user\'s LONG-TERM MEMORY. '
            .'Use after analyzing an invoice/statement/PDF, a decision taken, or a notable event — '
            .'so that FUTURE conversations can recall it without needing the original PDF. '
            .'The summary should be SHORT (1-3 sentences), factual, with concrete values and dates. '
            .'Don\'t use it for trivial things — only for facts worth consulting later. '
            .'Kinds: fatura, pagamento, decisao, evento, fato, meta, aprendizado.';
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
