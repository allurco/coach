<?php

namespace App\Models;

use App\Observers\UserObserver;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'is_admin', 'invitation_token', 'invited_at', 'accepted_invitation_at'])]
#[Hidden(['password', 'remember_token', 'invitation_token'])]
#[ObservedBy([UserObserver::class])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'invited_at' => 'datetime',
            'accepted_invitation_at' => 'datetime',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Invited users that haven't accepted yet can't log in (they have no
        // password). Once they set a password the invitation_token is cleared
        // and they're free to access.
        return $this->password !== null;
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function isInvitationPending(): bool
    {
        return $this->invitation_token !== null && $this->accepted_invitation_at === null;
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    /**
     * Per-instance cache for defaultGoal() so creating many actions in one
     * request doesn't issue N extra queries.
     */
    private ?Goal $cachedDefaultGoal = null;

    private bool $defaultGoalResolved = false;

    /**
     * Returns the user's primary active goal, used as the fallback when no
     * goal is explicitly selected. Skips archived goals. Memoized on the
     * model instance — flush by calling refreshDefaultGoal() if you change
     * goals mid-request.
     */
    public function defaultGoal(): ?Goal
    {
        if (! $this->defaultGoalResolved) {
            $this->cachedDefaultGoal = Goal::withoutGlobalScope('owner')
                ->where('user_id', $this->id)
                ->where('is_archived', false)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();
            $this->defaultGoalResolved = true;
        }

        return $this->cachedDefaultGoal;
    }

    public function refreshDefaultGoal(): void
    {
        $this->cachedDefaultGoal = null;
        $this->defaultGoalResolved = false;
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }
}
