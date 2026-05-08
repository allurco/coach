<?php

namespace App\Ai\Tools;

use App\Models\Goal;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SwitchToGoal implements Tool
{
    /**
     * @param  ?string  $conversationId  Current conversation id, passed in by
     *                                   FinanceCoach so the tool knows WHICH
     *                                   conversation to relocate. Null when
     *                                   there's no active conversation yet.
     */
    public function __construct(protected ?string $conversationId = null) {}

    public function description(): Stringable|string
    {
        return 'Move a conversa atual pra um goal (workspace) diferente. '
            .'Use SOMENTE depois de perguntar ao usuário se ele quer mudar — '
            .'ex.: depois de criar um goal novo via CreateGoal e o usuário '
            ."confirmar 'sim, vamos pra lá'. A conversa inteira (toda a "
            .'history desde o início) passa a pertencer ao goal de destino, '
            .'e a sidebar reflete a mudança imediatamente.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($this->conversationId === null) {
            return 'Erro: não tem conversa ativa pra mover. SwitchToGoal só funciona depois que o usuário trocou pelo menos uma mensagem.';
        }

        $goalId = (int) ($request['goal_id'] ?? 0);

        // Goal::find respects the per-user global scope, so a foreign goal
        // id is invisible — same posture as MoveAction.
        $goal = Goal::find($goalId);
        if (! $goal) {
            return sprintf('Erro: goal %d não encontrado.', $goalId);
        }

        if ($goal->is_archived) {
            return sprintf('Erro: goal "%s" está arquivado. Reative-o antes ou escolha outro.', $goal->name);
        }

        $currentGoalId = DB::table('agent_conversations')
            ->where('id', $this->conversationId)
            ->value('goal_id');

        if ($currentGoalId === $goal->id) {
            return sprintf('A conversa já está no goal "%s" — nada a mover.', $goal->name);
        }

        DB::table('agent_conversations')
            ->where('id', $this->conversationId)
            ->update(['goal_id' => $goal->id]);

        return sprintf('Conversa movida pro goal "%s". A sidebar já reflete a mudança.', $goal->name);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'goal_id' => $schema->integer()->required(),
        ];
    }
}
