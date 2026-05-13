<?php

namespace App\Ai\Tools;

use App\Models\Action;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateAction implements Tool
{
    public function description(): Stringable|string
    {
        return 'Updates an existing action: changes status, adds result notes, '
            .'adjusts deadline, or snoozes. Use after the user confirms something was done, '
            .'cancelled, or that the deadline needs to change. Use ListActions first to find the correct ID.';
    }

    public function handle(Request $request): Stringable|string
    {
        $action = Action::find($request['id']);

        if (! $action) {
            return "Action with ID {$request['id']} not found.";
        }

        $changes = [];

        if (! empty($request['status'])) {
            $action->status = $request['status'];
            $changes[] = "status → {$request['status']}";

            if ($request['status'] === 'completed') {
                $action->completed_at = now();
                $changes[] = 'completed_at → now';
            } elseif ($action->completed_at !== null) {
                // Re-opening or cancelling a previously completed action: clear timestamp.
                $action->completed_at = null;
                $changes[] = 'completed_at → null';
            }
        }

        if (! empty($request['result_notes'])) {
            $action->result_notes = $request['result_notes'];
            $changes[] = 'notes added';
        }

        if (! empty($request['deadline'])) {
            $action->deadline = $this->parseRelativeDate($request['deadline']) ?: null;
            $changes[] = 'deadline updated';
        }

        if (! empty($request['snooze_until'])) {
            $action->snooze_until = $this->parseRelativeDate($request['snooze_until']);
            $changes[] = "snoozed until {$action->snooze_until?->format('Y-m-d')}";
        }

        $action->save();

        return sprintf(
            'Action #%d "%s" updated: %s.',
            $action->id,
            $action->title,
            implode(', ', $changes) ?: 'no changes',
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'status' => $schema->string()
                ->enum(['pending', 'in_progress', 'completed', 'cancelled']),
            'result_notes' => $schema->string(),
            'deadline' => $schema->string(),
            'snooze_until' => $schema->string(),
        ];
    }

    protected function parseRelativeDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        $value = trim(strtolower($value));

        if (preg_match('/^(\d+)\s*([dwmy])$/', $value, $m)) {
            $n = (int) $m[1];

            return match ($m[2]) {
                'd' => now()->addDays($n)->toDateString(),
                'w' => now()->addWeeks($n)->toDateString(),
                'm' => now()->addMonths($n)->toDateString(),
                'y' => now()->addYears($n)->toDateString(),
            };
        }

        $kw = [
            'today' => 0, 'hoje' => 0, 'tomorrow' => 1, 'amanhã' => 1, 'amanha' => 1,
            'next week' => 7, 'próxima semana' => 7, 'proxima semana' => 7,
            'next month' => 30, 'próximo mês' => 30, 'proximo mes' => 30,
        ];
        if (isset($kw[$value])) {
            return now()->addDays($kw[$value])->toDateString();
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
