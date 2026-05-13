<?php

namespace App\Ai\Tools;

use App\Models\Action;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListActions implements Tool
{
    public function description(): Stringable|string
    {
        return 'Lists the actions in the user\'s plan. '
            .'Use to understand the current state before nudging, suggesting, creating a new action, or answering any question about the plan. '
            .'Can filter by status (pendente, em_andamento, concluido, cancelado) or by category.';
    }

    public function handle(Request $request): Stringable|string
    {
        $query = Action::query();

        if (! empty($request['status'])) {
            $query->where('status', $request['status']);
        }

        if (! empty($request['category'])) {
            $query->where('category', $request['category']);
        }

        if (! empty($request['only_overdue'])) {
            $query->where('status', 'pendente')
                ->whereNotNull('deadline')
                ->whereDate('deadline', '<', now());
        }

        $actions = $query
            ->orderByRaw('deadline IS NULL, deadline ASC')
            ->orderBy('priority', 'desc')
            ->limit(50)
            ->get();

        if ($actions->isEmpty()) {
            return 'Nenhuma ação encontrada com esses filtros.';
        }

        $lines = $actions->map(function (Action $a) {
            $deadline = $a->deadline?->format('d/m/Y') ?? 's/prazo';
            $overdue = $a->isOverdue() ? ' [ATRASADA]' : '';

            return sprintf(
                '#%d [%s|%s|%s] %s — prazo: %s%s',
                $a->id,
                $a->status,
                $a->category,
                $a->priority,
                $a->title,
                $deadline,
                $overdue,
            );
        })->implode("\n");

        return $lines;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(['pendente', 'em_andamento', 'concluido', 'cancelado']),
            'category' => $schema->string()
                ->enum(['financeiro', 'fiscal', 'operacional', 'crescimento']),
            'only_overdue' => $schema->boolean(),
        ];
    }
}
