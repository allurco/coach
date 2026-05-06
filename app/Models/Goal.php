<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Goal extends Model
{
    protected $fillable = [
        'user_id',
        'label',
        'name',
        'color',
        'sort_order',
        'is_archived',
    ];

    protected $casts = [
        'is_archived' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Built-in specialization labels. 'general' is the safe fallback used by
     * the migration backfill when the user has no clear focus.
     */
    public const LABELS = [
        'general' => 'Geral',
        'finance' => 'Finanças',
        'legal' => 'Jurídico/Fiscal',
        'emotional' => 'Emocional',
        'health' => 'Saúde',
        'fitness' => 'Atividade física',
        'learning' => 'Aprendizado',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('owner', function (Builder $query) {
            if ($userId = auth()->id()) {
                $query->where("{$query->getModel()->getTable()}.user_id", $userId);
            }
        });

        static::creating(function (Goal $goal) {
            if ($goal->user_id === null && $userId = auth()->id()) {
                $goal->user_id = $userId;
            }
        });

        // Refuse to archive the user's last active goal — every action and
        // conversation needs at least one workspace to attach to. Use raw
        // queries to bypass when you really need to (migrations, tests).
        static::saving(function (Goal $goal) {
            if (! $goal->is_archived || ! $goal->isDirty('is_archived')) {
                return;
            }

            $hasOtherActive = static::withoutGlobalScope('owner')
                ->where('user_id', $goal->user_id)
                ->where('id', '!=', $goal->id)
                ->where('is_archived', false)
                ->exists();

            if (! $hasOtherActive) {
                throw new \DomainException(
                    'Cannot archive the last active goal — create another goal first.'
                );
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(Action::class);
    }

    public function memories(): HasMany
    {
        return $this->hasMany(CoachMemory::class);
    }
}
