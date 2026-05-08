<?php

namespace App\Ai\Tools;

use App\Models\Action;
use App\Models\Goal;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class MoveAction implements Tool
{
    public function description(): Stringable|string
    {
        return 'Move uma ação existente pra outro goal (workspace) do usuário. '
            .'Use quando perceber que uma ação foi criada no workspace errado, '
            .'ou quando o usuário pedir explicitamente pra mover. '
            .'Tanto a ação quanto o goal de destino precisam pertencer ao usuário, '
            .'e o goal de destino não pode estar arquivado.';
    }

    public function handle(Request $request): Stringable|string
    {
        $actionId = (int) ($request['action_id'] ?? 0);
        $goalId = (int) ($request['goal_id'] ?? 0);

        // Both queries respect the per-user global scope, so any action or
        // goal belonging to another user is invisible here — there's no
        // way to leak data across tenants by passing a foreign id.
        $action = Action::find($actionId);
        if (! $action) {
            return sprintf('Erro: ação %d não encontrada.', $actionId);
        }

        $goal = Goal::find($goalId);
        if (! $goal) {
            return sprintf('Erro: goal %d não encontrado.', $goalId);
        }

        if ($goal->is_archived) {
            return sprintf('Erro: goal "%s" está arquivado. Reative-o antes ou escolha outro.', $goal->name);
        }

        if ($action->goal_id === $goal->id) {
            return sprintf('A ação "%s" já pertence ao goal "%s" — nada a mover.', $action->title, $goal->name);
        }

        $action->update(['goal_id' => $goal->id]);

        return sprintf('Ação "%s" movida pro goal "%s".', $action->title, $goal->name);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action_id' => $schema->integer()->required(),
            'goal_id' => $schema->integer()->required(),
        ];
    }
}
