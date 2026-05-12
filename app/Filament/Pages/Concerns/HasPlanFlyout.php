<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Action;

/**
 * State + behavior do flyout do Plano: lista de actions com filtros,
 * concluir (com modal de notes), adiar (snooze).
 *
 * Dependências:
 * - $activeGoalId (do componente principal) — usado em loadPlan pra
 *   filtrar actions do goal corrente.
 * - $memoPendingPlanCount (memoization private no componente).
 */
trait HasPlanFlyout
{
    public array $planActions = [];

    public string $planFilter = 'pendente';

    public ?int $completingActionId = null;

    public ?string $completingActionTitle = null;

    public string $completingNotes = '';

    /**
     * Carrega o plano do goal ativo (ou todas as actions do user se
     * sem goal). Renderiza em array shape pro view consumir — inclui
     * is_overdue/is_due_soon/has_details pré-computados pra evitar
     * lógica no Blade.
     */
    public function loadPlan(): void
    {
        $query = Action::query();

        if ($this->activeGoalId !== null) {
            $query->where('goal_id', $this->activeGoalId);
        }

        if ($this->planFilter !== 'todas') {
            $query->where('status', $this->planFilter);
        }

        $this->planActions = $query
            ->orderByRaw("CASE status WHEN 'em_andamento' THEN 0 WHEN 'pendente' THEN 1 WHEN 'concluido' THEN 2 ELSE 3 END")
            ->orderByRaw('deadline IS NULL, deadline ASC')
            ->orderByRaw("CASE priority WHEN 'alta' THEN 0 WHEN 'media' THEN 1 ELSE 2 END")
            ->limit(100)
            ->get()
            ->map(function (Action $a) {
                $attachments = collect($a->attachments ?? [])
                    ->filter(fn ($p) => is_string($p) && $p !== '')
                    ->map(fn (string $path) => [
                        'path' => $path,
                        'name' => basename($path),
                    ])
                    ->values()
                    ->all();

                $hasDetails = filled($a->description)
                    || filled($a->importance)
                    || filled($a->difficulty)
                    || filled($a->snooze_until)
                    || filled($a->result_notes)
                    || filled($a->completed_at)
                    || filled($attachments);

                return [
                    'id' => $a->id,
                    'title' => $a->title,
                    'category' => $a->category,
                    'priority' => $a->priority,
                    'status' => $a->status,
                    'deadline' => $a->deadline?->format('d/m/Y'),
                    'is_overdue' => $a->isOverdue(),
                    'is_due_soon' => $a->isDueSoon(),
                    'description' => $a->description,
                    'importance' => $a->importance,
                    'difficulty' => $a->difficulty,
                    'snooze_until' => $a->snooze_until?->format('d/m/Y'),
                    'result_notes' => $a->result_notes,
                    'completed_at' => $a->completed_at?->format('d/m/Y'),
                    'attachments' => $attachments,
                    'has_details' => $hasDetails,
                ];
            })
            ->toArray();
    }

    public function setPlanFilter(string $filter): void
    {
        $this->planFilter = $filter;
        $this->loadPlan();
    }

    public function startCompleteAction(int $id): void
    {
        $action = Action::find($id);
        if (! $action) {
            return;
        }
        $this->completingActionId = $id;
        $this->completingActionTitle = $action->title;
        $this->completingNotes = '';
    }

    public function cancelCompleteAction(): void
    {
        $this->completingActionId = null;
        $this->completingActionTitle = null;
        $this->completingNotes = '';
    }

    public function confirmCompleteAction(): void
    {
        if ($this->completingActionId === null) {
            return;
        }

        $payload = [
            'status' => 'concluido',
            'completed_at' => now(),
            'snooze_until' => null,
        ];

        $notes = trim($this->completingNotes);
        if ($notes !== '') {
            $payload['result_notes'] = $notes;
        }

        Action::where('id', $this->completingActionId)->update($payload);

        $this->cancelCompleteAction();
        $this->loadPlan();
    }

    public function snoozeAction(int $id, string $duration): void
    {
        $until = match ($duration) {
            'tomorrow' => now()->addDay(),
            '3days' => now()->addDays(3),
            'week' => now()->addWeek(),
            'month' => now()->addMonth(),
            default => null,
        };

        Action::where('id', $id)->update(['snooze_until' => $until?->toDateString()]);
        $this->loadPlan();
    }

    /**
     * Open + in-progress actions in the current plan view — drives o
     * badge do botão "Plano" no header. Memoizado (cache vive em
     * $memoPendingPlanCount no componente).
     */
    public function pendingPlanCount(): int
    {
        return $this->memoPendingPlanCount ??= collect($this->planActions)
            ->whereIn('status', Action::OPEN_STATUSES)
            ->count();
    }
}
