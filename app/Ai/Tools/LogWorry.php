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
        return 'Registers a worry or fear the user has verbalized. '
            .'Use when the user expresses anxiety ("what if X happens?", '
            .'"I\'m afraid of Y", "I get stuck thinking about Z"). '
            .'The idea is to give the worry a concrete place to live so it can '
            .'leave the user\'s head and be revisited later — most don\'t materialize, '
            .'and that becomes evidence over time. '
            .'Include a short "topic" (1-3 words) for easier lookup later.';
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
