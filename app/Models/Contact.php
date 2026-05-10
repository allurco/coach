<?php

namespace App\Models;

use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'label',
        'name',
        'email',
        'notes',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('owner', function (Builder $query) {
            if ($userId = auth()->id()) {
                $query->where("{$query->getModel()->getTable()}.user_id", $userId);
            }
        });

        static::creating(function (Contact $contact) {
            if ($contact->user_id === null && $userId = auth()->id()) {
                $contact->user_id = $userId;
            }

            if (is_string($contact->label)) {
                $contact->label = Str::slug($contact->label, '-');
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Resolve a user's contact by label slug. Returns null when the
     * user has no matching contact. Bypasses the global scope so this
     * works in unauthenticated contexts (e.g. inside an agent tool
     * when `auth()->loginUsingId` was just called).
     */
    public static function forUserAndLabel(int $userId, string $label): ?self
    {
        return static::query()
            ->withoutGlobalScope('owner')
            ->where('user_id', $userId)
            ->where('label', Str::slug($label, '-'))
            ->first();
    }
}
