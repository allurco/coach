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

        return 'Cria um novo goal (workspace) na barra lateral do usuário. '
            .'Use quando o usuário expressar uma nova área de foco distinta dos goals existentes. '
            .'Confirme verbalmente antes de criar. '
            ."'name' é o título curto que aparece na sidebar (ex: 'Sair do vermelho'). "
            ."'label' classifica o tipo e define a especialização do agente. Aceitos: {$labels}. "
            ."Use 'general' se nenhum se encaixar.";
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
