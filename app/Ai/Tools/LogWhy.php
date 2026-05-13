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
     *                              CoachAgent so the motivation lands
     *                              in the workspace it was uttered in.
     */
    public function __construct(protected ?int $activeGoalId = null) {}

    public function description(): Stringable|string
    {
        return 'Saves the user\'s "why" — the deep reason they want to reach this goal. '
            .'Use when the user expresses genuine motivation ("I want X because Y", '
            .'"if I succeed, I\'ll finally be able to Z"). '
            .'These whys live in long-term memory and the coach cites them back '
            .'when the user is wavering or wanting to quit. '
            .'DO NOT use for trivial facts — only real motivation.';
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
