<?php

namespace App\Placeholders;

use App\Models\Action;

/**
 * Core placeholder for `{{plan}}` — renders the user's open actions
 * (pending + in_progress) as a markdown checklist. Lives in Core (not
 * a pack) because the Action/Plan model is a core concept that every
 * pack composes against.
 */
class PlanPlaceholder implements PlaceholderHandler
{
    public function render(?int $userId, array $args): string
    {
        if ($userId === null) {
            return (string) __('coach.placeholders.plan_empty');
        }

        $statusRank = ['in_progress' => 0, 'pending' => 1];
        $priorityRank = ['high' => 0, 'medium' => 1, 'low' => 2];

        $actions = Action::query()
            ->withoutGlobalScope('owner')
            ->where('user_id', $userId)
            ->whereIn('status', ['pending', 'in_progress'])
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
