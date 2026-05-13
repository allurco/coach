<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Action extends Model
{
    protected $fillable = [
        'user_id',
        'goal_id',
        'title',
        'description',
        'category',
        'priority',
        'importance',
        'difficulty',
        'deadline',
        'status',
        'completed_at',
        'result_notes',
        'snooze_until',
        'attachments',
    ];

    protected $casts = [
        'deadline' => 'date',
        'snooze_until' => 'date',
        'completed_at' => 'datetime',
        'attachments' => 'array',
    ];

    /**
     * Enum value catalogs. Keys are the canonical English identifiers
     * persisted in the database; values are the default English labels
     * used in dev tooling (Filament internals, factories, etc.).
     * User-facing UI must localize via `__('coach.action.<column>.<key>')`.
     */
    public const CATEGORIES = [
        'financial' => 'Financial',
        'tax' => 'Tax',
        'operational' => 'Operational',
        'growth' => 'Growth',
    ];

    public const PRIORITIES = [
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
    ];

    public const IMPORTANCES = [
        'critical' => 'Critical',
        'important' => 'Important',
        'routine' => 'Routine',
    ];

    public const DIFFICULTIES = [
        'quick' => 'Quick',
        'medium' => 'Medium',
        'heavy' => 'Heavy',
    ];

    public const STATUSES = [
        'pending' => 'Pending',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    /** Statuses where the action is still actionable (not closed). */
    public const OPEN_STATUSES = ['pending', 'in_progress'];

    protected static function booted(): void
    {
        // Auto-scope every query to the authenticated user.
        // Multi-tenant isolation: no user accidentally sees another's plan.
        static::addGlobalScope('owner', function (Builder $query) {
            if ($userId = auth()->id()) {
                $query->where("{$query->getModel()->getTable()}.user_id", $userId);
            }
        });

        // Auto-fill user_id + goal_id on create when one is logged in.
        //
        // - When auth() matches $action->user_id (the common case: tools, UI,
        //   webhook), reuse the in-memory authenticated user so this hook
        //   doesn't add a User::find query per insert.
        // - User::defaultGoal() is memoized per-instance, so creating N
        //   actions in one request resolves the goal once and reuses it.
        // - If no active goal exists, throw a clear DomainException instead
        //   of letting the DB raise a NOT NULL constraint violation.
        static::creating(function (Action $action) {
            if ($action->user_id === null && $userId = auth()->id()) {
                $action->user_id = $userId;
            }

            if ($action->goal_id === null && $action->user_id !== null) {
                $user = auth()->id() === $action->user_id
                    ? auth()->user()
                    : User::find($action->user_id);

                $action->goal_id = $user?->defaultGoal()?->id;
            }

            if ($action->goal_id === null) {
                throw new \DomainException(
                    'Cannot create action: user has no active goal. Create or unarchive a goal first.'
                );
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    public function isOverdue(): bool
    {
        return $this->deadline
            && $this->status === 'pending'
            && $this->deadline->isPast();
    }

    public function isDueSoon(int $days = 3): bool
    {
        return $this->deadline
            && $this->status === 'pending'
            && $this->deadline->isBetween(now(), now()->addDays($days));
    }
}
