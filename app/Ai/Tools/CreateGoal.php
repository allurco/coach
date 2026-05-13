<?php

namespace App\Ai\Tools;

use App\Models\Goal;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateGoal implements Tool
{
    public function description(): Stringable|string
    {
        $labels = implode(', ', array_keys(Goal::LABELS));

        return 'Creates a new goal (workspace) in the user\'s sidebar. '
            .'Use when the user expresses a new focus area distinct from existing goals. '
            .'Confirm verbally before creating. '
            ."'name' is the short title shown in the sidebar (e.g. 'Get out of the red'). "
            ."'label' classifies the type and drives the agent\'s specialization. Accepted: {$labels}. "
            ."Use 'general' if none fits.";
    }

    public function handle(Request $request): Stringable|string
    {
        $name = trim((string) ($request['name'] ?? ''));

        if ($name === '') {
            return 'Erro: o nome do goal não pode estar vazio.';
        }

        $label = $request['label'] ?? 'general';
        if (! array_key_exists($label, Goal::LABELS)) {
            $label = 'general';
        }

        $goal = Goal::create([
            'name' => $name,
            'label' => $label,
        ]);

        return sprintf('Goal criado com ID %d: "%s" (categoria: %s).', $goal->id, $goal->name, $goal->label);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'label' => $schema->string()->enum(array_keys(Goal::LABELS)),
        ];
    }
}
