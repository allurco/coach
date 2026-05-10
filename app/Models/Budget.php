<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    protected $table = 'coach_budgets';

    protected $fillable = [
        'user_id',
        'goal_id',
        'month',
        'net_income',
        'fixed_costs_subtotal',
        'fixed_costs_total',
        'fixed_costs_breakdown',
        'investments_total',
        'investments_breakdown',
        'savings_total',
        'savings_breakdown',
        'leisure_amount',
        'notes',
    ];

    protected $casts = [
        'net_income' => 'decimal:2',
        'fixed_costs_subtotal' => 'decimal:2',
        'fixed_costs_total' => 'decimal:2',
        'fixed_costs_breakdown' => 'array',
        'investments_total' => 'decimal:2',
        'investments_breakdown' => 'array',
        'savings_total' => 'decimal:2',
        'savings_breakdown' => 'array',
        'leisure_amount' => 'decimal:2',
    ];

    /**
     * Default fixed-costs miscellaneous buffer, applied on top of the
     * sum of listed fixed-cost line items. Captures the "things you
     * forgot" reality of real-world budgeting.
     */
    public const FIXED_COSTS_BUFFER_PCT = 15;

    /**
     * Target ranges per bucket, expressed as percentage of net income.
     * Used by the BudgetSnapshot tool to flag buckets that fall out of
     * range with ⚠ vs ✓.
     */
    public const TARGET_RANGES = [
        'fixed_costs' => ['min' => 50, 'max' => 60],
        'investments' => ['min' => 10, 'max' => 10],
        'savings' => ['min' => 5, 'max' => 10],
        'leisure' => ['min' => 20, 'max' => 35],
    ];

    protected static function booted(): void
    {
        // Per-user global scope — same defense-in-depth pattern used by
        // Action / CoachMemory / Goal. Cross-tenant queries are
        // physically impossible from authenticated contexts.
        static::addGlobalScope('owner', function (Builder $query) {
            if ($userId = auth()->id()) {
                $query->where("{$query->getModel()->getTable()}.user_id", $userId);
            }
        });

        static::creating(function (Budget $budget) {
            if ($budget->user_id === null && $userId = auth()->id()) {
                $budget->user_id = $userId;
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

    /**
     * The user's most recent budget. Bypasses the owner global scope
     * so this works regardless of who's authenticated when called.
     */
    public static function currentForUser(int $userId): ?self
    {
        return static::query()
            ->withoutGlobalScope('owner')
            ->where('user_id', $userId)
            ->orderByDesc('month')
            ->orderByDesc('id')
            ->first();
    }
}
