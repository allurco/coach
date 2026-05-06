<?php

use App\Models\Goal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function seedConversation(int $userId, int $goalId, string $title, ?string $updatedAt = null): string
{
    $id = (string) Str::uuid();
    DB::table('agent_conversations')->insert([
        'id' => $id,
        'user_id' => $userId,
        'goal_id' => $goalId,
        'title' => $title,
        'created_at' => $updatedAt ?? now(),
        'updated_at' => $updatedAt ?? now(),
    ]);

    return $id;
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

// ===== latestConversation =====

it('returns null when the goal has no conversations yet', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);

    expect($goal->latestConversation())->toBeNull();
});

it('returns the most recently updated conversation for the goal', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);

    seedConversation($this->user->id, $goal->id, 'Old', now()->subDays(10));
    $latestId = seedConversation($this->user->id, $goal->id, 'Recent', now()->subHour());
    seedConversation($this->user->id, $goal->id, 'Older', now()->subDays(2));

    expect($goal->latestConversation()?->id)->toBe($latestId);
});

it('does not pick up other goals conversations', function () {
    $finance = Goal::create(['label' => 'finance', 'name' => 'Finance']);
    $fitness = Goal::create(['label' => 'fitness', 'name' => 'Fitness']);

    seedConversation($this->user->id, $fitness->id, 'Fitness chat', now());

    expect($finance->latestConversation())->toBeNull();
});

// ===== conversationHistory =====

it('history is empty when goal has 0 or 1 conversations', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);

    expect($goal->conversationHistory())->toBeEmpty();

    seedConversation($this->user->id, $goal->id, 'Only one', now());
    expect($goal->conversationHistory())->toBeEmpty();
});

it('history excludes the latest and orders newest-first', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);

    $oldest = seedConversation($this->user->id, $goal->id, 'Oldest', now()->subDays(10));
    $middle = seedConversation($this->user->id, $goal->id, 'Middle', now()->subDays(5));
    $latest = seedConversation($this->user->id, $goal->id, 'Latest', now()->subHour());

    $history = $goal->conversationHistory();

    expect($history)->toHaveCount(2)
        ->and($history->first()->id)->toBe($middle)   // newest of the non-latest
        ->and($history->last()->id)->toBe($oldest);
});

// ===== sidebar listing =====

it('User::goalsForSidebar returns active goals only', function () {
    $active = Goal::where('user_id', $this->user->id)->first();   // observer default
    $extra = Goal::create(['label' => 'fitness', 'name' => 'Active 2']);
    // Need at least one other active goal to legally archive a third one.
    $thirdActive = Goal::create(['label' => 'health', 'name' => 'Stays active']);
    $archived = Goal::create(['label' => 'learning', 'name' => 'Old']);
    $archived->update(['is_archived' => true]);

    $sidebar = $this->user->goalsForSidebar();

    $ids = $sidebar->pluck('id');
    expect($ids)->toContain($active->id)
        ->toContain($extra->id)
        ->toContain($thirdActive->id)
        ->not->toContain($archived->id);
});

it('User::goalsForSidebar floats most-recently-touched goals to the top', function () {
    Goal::query()->where('user_id', $this->user->id)->delete();   // clear default
    $a = Goal::create(['label' => 'finance', 'name' => 'A']);
    $b = Goal::create(['label' => 'fitness', 'name' => 'B']);
    $c = Goal::create(['label' => 'learning', 'name' => 'C']);

    seedConversation($this->user->id, $b->id, 'B chat', now()->subHour());      // most recent
    seedConversation($this->user->id, $a->id, 'A chat', now()->subDays(2));
    // C has no conversation

    $sidebar = $this->user->goalsForSidebar();

    expect($sidebar->pluck('id')->all())->toBe([$b->id, $a->id, $c->id]);
});

// ===== Goal->conversations relationship =====

it('Goal hasMany conversations relationship', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);
    seedConversation($this->user->id, $goal->id, 'A', now());
    seedConversation($this->user->id, $goal->id, 'B', now());

    expect($goal->conversations)->toHaveCount(2);
});

it('conversations relationship respects user scope (no cross-tenant leak)', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);
    $other = User::factory()->create();
    $otherGoal = Goal::withoutGlobalScope('owner')->create([
        'user_id' => $other->id,
        'label' => 'finance',
        'name' => 'Other goal',
    ]);

    seedConversation($this->user->id, $goal->id, 'Mine', now());
    seedConversation($other->id, $otherGoal->id, 'Theirs', now());

    expect($goal->conversations)->toHaveCount(1)
        ->and($goal->conversations->first()->title)->toBe('Mine');
});
