<?php

namespace App\Agent\Services;

/**
 * The outcome of one CoachInteraction::run() call.
 *
 * Captures everything callers might need after agent orchestration:
 * the decorated response text, the persisted conversation id, the
 * effective goal id (which may differ from the input if SwitchToGoal
 * fired mid-turn), the per-tool activity summary, and whether the
 * auto-retry was triggered.
 *
 * Readonly so callers can't accidentally mutate the captured state
 * and expect side-effects on the service.
 */
final readonly class InteractionResult
{
    /**
     * @param  list<array{name: string, count: int, ok: int}>  $toolActivity
     */
    public function __construct(
        public string $text,
        public ?string $conversationId,
        public ?int $effectiveGoalId,
        public array $toolActivity,
        public bool $retried,
    ) {}
}
