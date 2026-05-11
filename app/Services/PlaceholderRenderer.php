<?php

namespace App\Services;

use App\Ai\Tools\BudgetSnapshot;
use App\Models\Action;
use App\Models\Budget;

/**
 * Expands `{{placeholder}}` tokens inside agent/email markdown into
 * authoritative renderings sourced from the database. The agent
 * writes prose around the tokens; data comes from here so numbers
 * and lists never depend on the model's recall.
 *
 * Supported today:
 *   - {{budget:N}}        → BudgetSnapshot rendering for snapshot N
 *   - {{budget:current}}  → user's most recent budget
 *   - {{plan}}            → user's current open actions
 *
 * Unknown placeholders pass through untouched on purpose, so a
 * mistyped token surfaces in the output instead of silently dropping.
 */
class PlaceholderRenderer
{
    /**
     * Render every supported placeholder. When $userId is null we
     * fall back to auth()->id(); pass it explicitly when running
     * outside of an authenticated request (queues, mail jobs).
     */
    public function render(string $text, ?int $userId = null): string
    {
        $userId = $userId ?? auth()->id();

        $text = $this->expandBudgetCurrent($text, $userId);
        $text = $this->expandBudgetById($text);
        $text = $this->expandPlan($text, $userId);

        return $text;
    }

    protected function expandBudgetCurrent(string $text, ?int $userId): string
    {
        return (string) preg_replace_callback(
            '/\{\{budget:current\}\}/',
            function () use ($userId): string {
                $budget = $userId ? Budget::currentForUser($userId) : null;

                return $budget
                    ? (new BudgetSnapshot)->renderForBudget($budget)
                    : (string) __('coach.placeholders.budget_missing');
            },
            $text,
        );
    }

    protected function expandBudgetById(string $text): string
    {
        return (string) preg_replace_callback(
            '/\{\{budget:(\d+)\}\}/',
            function (array $m): string {
                $budget = Budget::withoutGlobalScope('owner')->find((int) $m[1]);

                return $budget
                    ? (new BudgetSnapshot)->renderForBudget($budget)
                    : (string) __('coach.placeholders.budget_missing');
            },
            $text,
        );
    }

    protected function expandPlan(string $text, ?int $userId): string
    {
        if (! str_contains($text, '{{plan}}')) {
            return $text;
        }

        return str_replace('{{plan}}', $this->renderPlan($userId), $text);
    }

    protected function renderPlan(?int $userId): string
    {
        if ($userId === null) {
            return (string) __('coach.placeholders.plan_empty');
        }

        $statusRank = ['em_andamento' => 0, 'pendente' => 1];
        $priorityRank = ['alta' => 0, 'media' => 1, 'baixa' => 2];

        $actions = Action::query()
            ->withoutGlobalScope('owner')
            ->where('user_id', $userId)
            ->whereIn('status', ['pendente', 'em_andamento'])
            ->orderBy('deadline')
            ->limit(20)
            ->get()
            ->sortBy(fn (Action $a) => sprintf(
                '%d-%d',
                $statusRank[$a->status] ?? 9,
                $priorityRank[$a->priority] ?? 9,
            ))
            ->values();

        if ($actions->isEmpty()) {
            return (string) __('coach.placeholders.plan_empty');
        }

        $lines = [(string) __('coach.placeholders.plan_header'), ''];

        foreach ($actions as $action) {
            $statusLabel = (string) __('coach.plan.filters.'.$action->status);
            $priority = $action->priority ? " ({$action->priority})" : '';
            $deadline = $action->deadline ? ' — '.$action->deadline->format('d/m') : '';

            $lines[] = "- [{$statusLabel}] {$action->title}{$priority}{$deadline}";
        }

        return implode("\n", $lines);
    }
}
