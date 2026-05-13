<?php

namespace App\Ai\Tools;

use App\Models\Action;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateAction implements Tool
{
    /**
     * @param  ?int  $activeGoalId  Goal the new action belongs to. Passed
     *                              in by CoachAgent so the action lands
     *                              in the workspace the user is currently
     *                              looking at. If null, the Action model's
     *                              creating hook falls back to the user's
     *                              default goal (covers cron/email flows
     *                              where there's no active sidebar goal).
     */
    public function __construct(protected ?int $activeGoalId = null) {}

    public function description(): Stringable|string
    {
        return 'Creates a new action in the user\'s plan. '
            .'Use only after the user verbally confirms they want to add the action. '
            .'Categories: financeiro, fiscal, operacional, crescimento. '
            .'Priorities: alta, media, baixa. '
            .'Importance: critico, importante, rotineiro. '
            .'Difficulty: rapido, medio, pesado. '
            .'Status always starts as "pendente".';
    }

    public function handle(Request $request): Stringable|string
    {
        $payload = [
            'title' => $request['title'],
            'description' => $request['description'] ?? null,
            'category' => $request['category'] ?? 'financeiro',
            'priority' => $request['priority'] ?? 'media',
            'importance' => $request['importance'] ?? 'importante',
            'difficulty' => $request['difficulty'] ?? 'medio',
            'deadline' => $this->parseDeadline($request['deadline'] ?? null),
            'status' => 'pendente',
        ];

        if ($this->activeGoalId !== null) {
            $payload['goal_id'] = $this->activeGoalId;
        }

        $action = Action::create($payload);

        return sprintf(
            'Ação criada com ID %d: "%s" (categoria: %s, prioridade: %s, prazo: %s).',
            $action->id,
            $action->title,
            $action->category,
            $action->priority,
            $action->deadline?->format('d/m/Y') ?? 'sem prazo',
        );
    }

    /**
     * Accept either an absolute date (YYYY-MM-DD or DD/MM/YYYY) or a relative
     * shorthand from Gemini: "1d"/"3d"/"1w"/"2w"/"1m"/"3m" or "tomorrow"/"next week".
     * Returns a Y-m-d string or null.
     */
    protected function parseDeadline(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $value = trim(strtolower($value));

        // Relative shortcuts: "Nd", "Nw", "Nm"
        if (preg_match('/^(\d+)\s*([dwmy])$/', $value, $m)) {
            $n = (int) $m[1];
            $unit = $m[2];
            $date = match ($unit) {
                'd' => now()->addDays($n),
                'w' => now()->addWeeks($n),
                'm' => now()->addMonths($n),
                'y' => now()->addYears($n),
            };

            return $date->toDateString();
        }

        // Common english/portuguese keywords
        $keywordMap = [
            'today' => 0,
            'hoje' => 0,
            'tomorrow' => 1,
            'amanhã' => 1,
            'amanha' => 1,
            'next week' => 7,
            'próxima semana' => 7,
            'proxima semana' => 7,
            'next month' => 30,
            'próximo mês' => 30,
            'proximo mes' => 30,
        ];

        if (isset($keywordMap[$value])) {
            return now()->addDays($keywordMap[$value])->toDateString();
        }

        // Try common date formats
        try {
            // YYYY-MM-DD passes through
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return Carbon::parse($value)->toDateString();
            }
            // DD/MM/YYYY → swap to ISO
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m)) {
                return "{$m[3]}-{$m[2]}-{$m[1]}";
            }

            // Generic Carbon parse fallback
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()
                ->required(),
            'description' => $schema->string(),
            'category' => $schema->string()
                ->enum(['financeiro', 'fiscal', 'operacional', 'crescimento']),
            'priority' => $schema->string()
                ->enum(['alta', 'media', 'baixa']),
            'importance' => $schema->string()
                ->enum(['critico', 'importante', 'rotineiro']),
            'difficulty' => $schema->string()
                ->enum(['rapido', 'medio', 'pesado']),
            'deadline' => $schema->string(),
        ];
    }
}
