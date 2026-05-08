<?php

namespace App\Ai\Tools;

use App\Models\CoachMemory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class LogWhy implements Tool
{
    /**
     * @param  ?int  $activeGoalId  Goal the why belongs to. Passed in by
     *                              FinanceCoach so the motivation lands
     *                              in the workspace it was uttered in.
     */
    public function __construct(protected ?int $activeGoalId = null) {}

    public function description(): Stringable|string
    {
        return 'Salva o "porquê" do usuário — a razão profunda dele querer alcançar este goal. '
            .'Use quando o usuário expressar motivação genuína ("quero X porque Y", '
            .'"se eu conseguir, finalmente vou poder Z"). '
            .'Esses porquês ficam em memória de longo prazo e o coach cita de volta '
            .'quando o usuário estiver vacilando ou com vontade de desistir. '
            .'NÃO use pra registrar fatos triviais — só motivação real.';
    }

    public function handle(Request $request): Stringable|string
    {
        $why = trim((string) ($request['why'] ?? ''));

        if ($why === '') {
            return 'Erro: o "porquê" não pode estar vazio.';
        }

        $memory = CoachMemory::create([
            'kind' => 'why',
            'label' => 'why',
            'summary' => $why,
            'goal_id' => $this->activeGoalId,
            'is_active' => true,
        ]);

        return sprintf('Salvei o seu porquê — vou citar de volta quando você precisar (id: %d).', $memory->id);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'why' => $schema->string()->required(),
        ];
    }
}
