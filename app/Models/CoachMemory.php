<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachMemory extends Model
{
    protected $fillable = [
        'user_id',
        'goal_id',
        'kind',
        'label',
        'summary',
        'details',
        'event_date',
        'source_action_id',
        'source_conversation_id',
        'is_active',
    ];

    protected $casts = [
        'details' => 'array',
        'event_date' => 'date',
        'is_active' => 'boolean',
    ];

    public const KINDS = [
        'fatura' => 'Fatura',
        'pagamento' => 'Pagamento',
        'decisao' => 'Decisão',
        'evento' => 'Evento',
        'fato' => 'Fato',
        'perfil' => 'Perfil',
        'goal' => 'Foco/Goal',
        'meta' => 'Meta/Objetivo',
        'aprendizado' => 'Aprendizado',
        'why' => 'Por que',          // user's deeper motivation per goal
        'worry' => 'Preocupação',    // logged anxiety to revisit later
    ];

    /**
     * Goals that have a built-in specialization in the system prompt.
     * Other labels are accepted but won't add specialized guidance.
     */
    public const GOAL_LABELS = [
        'finance' => 'Finanças',
        'legal' => 'Jurídico/Fiscal',
        'emotional' => 'Emocional',
        'health' => 'Saúde',
        'fitness' => 'Atividade física',
        'learning' => 'Aprendizado',
    ];

    protected static function booted(): void
    {
        // Auto-scope every query to the authenticated user.
        // Memories are private — defense in depth on top of explicit filters.
        static::addGlobalScope('owner', function (Builder $query) {
            if ($userId = auth()->id()) {
                $query->where("{$query->getModel()->getTable()}.user_id", $userId);
            }
        });

        // Auto-fill user_id on create when one is logged in.
        static::creating(function (CoachMemory $memory) {
            if ($memory->user_id === null && $userId = auth()->id()) {
                $memory->user_id = $userId;
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

    public function sourceAction(): BelongsTo
    {
        return $this->belongsTo(Action::class, 'source_action_id');
    }
}
