<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachMemory extends Model
{
    protected $fillable = [
        'user_id',
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
        'meta' => 'Meta/Objetivo',
        'aprendizado' => 'Aprendizado',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceAction(): BelongsTo
    {
        return $this->belongsTo(Action::class, 'source_action_id');
    }
}
