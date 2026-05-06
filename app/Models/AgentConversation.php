<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Read-side model on top of the laravel/ai-managed agent_conversations
 * table. We don't use this to write rows (the AI SDK owns inserts via its
 * conversation store) — only to query and relate.
 */
class AgentConversation extends Model
{
    protected $table = 'agent_conversations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'goal_id',
        'title',
    ];

    protected static function booted(): void
    {
        // Same multi-tenant guard as Action / CoachMemory: queries auto-filter
        // to the authenticated user. Console / webhook code paths login the
        // user first so this scope kicks in there too.
        static::addGlobalScope('owner', function (Builder $query) {
            if ($userId = auth()->id()) {
                $query->where("{$query->getModel()->getTable()}.user_id", $userId);
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
}
