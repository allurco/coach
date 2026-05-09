<?php

namespace App\Ai\Tools;

use App\Models\CoachMemory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class LogWorry implements Tool
{
    /**
     * @param  ?int  $activeGoalId  Goal the worry is associated with (the
     *                              area where the anxiety surfaced).
     *                              Optional — some worries are cross-goal.
     */
    public function __construct(protected ?int $activeGoalId = null) {}

    public function description(): Stringable|string
    {
        return 'Registra uma preocupação ou medo que o usuário verbalizou. '
            .'Use quando o usuário expressar ansiedade ("e se X acontecer?", '
            .'"tô com medo de Y", "fico travado pensando em Z"). '
            .'A ideia é dar um lugar concreto pra preocupação sair da cabeça '
            .'e poder ser revisitada depois — a maioria não materializa, e '
            .'isso vira evidência ao longo do tempo. '
            .'Inclua um "topic" curto (1-3 palavras) pra facilitar busca depois.';
    }

    public function handle(Request $request): Stringable|string
    {
        $worry = trim((string) ($request['worry'] ?? ''));

        if ($worry === '') {
            return 'Erro: a preocupação não pode estar vazia.';
        }

        $topic = trim((string) ($request['topic'] ?? '')) ?: 'geral';

        $memory = CoachMemory::create([
            'kind' => 'worry',
            'label' => $topic,
            'summary' => $worry,
            'goal_id' => $this->activeGoalId,
            'event_date' => now()->toDateString(),
            'is_active' => true,
        ]);

        return sprintf('Registrei essa preocupação — daqui umas semanas a gente revisita pra ver se materializou (id: %d).', $memory->id);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'worry' => $schema->string()->required(),
            'topic' => $schema->string(),
        ];
    }
}
