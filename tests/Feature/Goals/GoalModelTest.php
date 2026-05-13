<?php

use App\Models\Action;
use App\Models\CoachMemory;
use App\Models\Goal;
use App\Models\User;

// ===== Schema and basics =====

it('has a goals table with the expected columns', function () {
    expect(Schema::hasTable('goals'))->toBeTrue();

    $cols = ['id', 'user_id', 'label', 'name', 'color', 'sort_order', 'is_archived', 'created_at', 'updated_at'];

    foreach ($cols as $col) {
        expect(Schema::hasColumn('goals', $col))->toBeTrue("missing column: {$col}");
    }
});

it('actions table has a goal_id column', function () {
    expect(Schema::hasColumn('actions', 'goal_id'))->toBeTrue();
});

it('coach_memories table has a goal_id column (nullable for shared memories)', function () {
    expect(Schema::hasColumn('coach_memories', 'goal_id'))->toBeTrue();
});

it('agent_conversations table has a goal_id column', function () {
    expect(Schema::hasColumn('agent_conversations', 'goal_id'))->toBeTrue();
});

// ===== Goal model =====

it('creates a goal with required fields', function () {
    $user = User::factory()->create();

    $goal = Goal::create([
        'user_id' => $user->id,
        'label' => 'finance',
        'name' => 'Saúde financeira',
    ])->fresh();

    expect($goal->id)->not->toBeNull()
        ->and($goal->label)->toBe('finance')
        ->and($goal->is_archived)->toBeFalse();
});

it('belongs to a user', function () {
    $user = User::factory()->create();
    $goal = Goal::create(['user_id' => $user->id, 'label' => 'finance', 'name' => 'F']);

    expect($goal->user)->not->toBeNull()
        ->and($goal->user->is($user))->toBeTrue();
});

it('user has many goals', function () {
    $user = User::factory()->create();
    // Observer auto-creates the default goal, plus we add 2 more.
    Goal::create(['user_id' => $user->id, 'label' => 'finance', 'name' => 'F']);
    Goal::create(['user_id' => $user->id, 'label' => 'fitness', 'name' => 'Fit']);

    expect($user->goals)->toHaveCount(3);
});

// ===== Multi-tenant isolation (mirrors Action and CoachMemory) =====

it('global scope hides other users goals', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    // Alice has only the default observer-created goal; she should not see
    // any of Bob's goals.
    $this->actingAs($alice);
    expect(Goal::count())->toBe(1)
        ->and(Goal::where('user_id', $bob->id)->count())->toBe(0);
});

it('auto-fills user_id on create from auth', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $goal = Goal::create(['label' => 'finance', 'name' => 'My finance']);

    expect($goal->user_id)->toBe($user->id);
});

// ===== Action ↔ Goal =====

it('action belongs to a goal', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $goal = Goal::create(['label' => 'finance', 'name' => 'F']);
    $action = Action::create([
        'goal_id' => $goal->id,
        'title' => 'Pay invoice',
        'status' => 'pending',
    ]);

    expect($action->goal)->not->toBeNull()
        ->and($action->goal->is($goal))->toBeTrue();
});

it('goal has many actions', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $goal = Goal::create(['label' => 'finance', 'name' => 'F']);
    Action::create(['goal_id' => $goal->id, 'title' => 'A', 'status' => 'pending']);
    Action::create(['goal_id' => $goal->id, 'title' => 'B', 'status' => 'pending']);

    expect($goal->actions)->toHaveCount(2);
});

// ===== CoachMemory ↔ Goal (nullable for shared) =====

it('memory can belong to a specific goal', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $goal = Goal::create(['label' => 'fitness', 'name' => 'Fit']);
    $memory = CoachMemory::create([
        'goal_id' => $goal->id,
        'kind' => 'fato',
        'label' => 'Treino terça',
        'summary' => 'Vou na academia hoje',
    ]);

    expect($memory->goal)->not->toBeNull()
        ->and($memory->goal->is($goal))->toBeTrue();
});

it('memory with null goal_id is shared across all goals (e.g. perfil facts)', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $shared = CoachMemory::create([
        'goal_id' => null,
        'kind' => 'perfil',
        'label' => 'Renda',
        'summary' => '25K/mês',
    ]);

    expect($shared->goal_id)->toBeNull()
        ->and($shared->goal)->toBeNull();
});

// ===== Default goal for new users (observer / auto-create) =====

it('creates a default goal automatically when a user is created', function () {
    $user = User::factory()->create();

    $goal = Goal::withoutGlobalScope('owner')
        ->where('user_id', $user->id)
        ->first();

    expect($goal)->not->toBeNull()
        ->and($goal->label)->toBe('general')
        ->and($goal->is_archived)->toBeFalse();
});

it('default goal is the first non-archived goal for a user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $first = Goal::where('user_id', $user->id)->first();
    expect($user->defaultGoal()?->is($first))->toBeTrue();
});

it('defaultGoal skips archived goals', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Force-archive the observer-created default (bypasses last-active guard).
    Goal::query()->where('user_id', $user->id)->update(['is_archived' => true]);
    $active = Goal::create(['label' => 'finance', 'name' => 'Active goal']);

    expect($user->defaultGoal()?->is($active))->toBeTrue();
});

// ===== Last-active goal guard =====

it('prevents archiving the only remaining active goal', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $only = Goal::where('user_id', $user->id)->first();

    expect(fn () => $only->update(['is_archived' => true]))
        ->toThrow(DomainException::class);

    expect($only->fresh()->is_archived)->toBeFalse();
});

it('allows archiving when another active goal exists', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Goal::create(['label' => 'fitness', 'name' => 'Fitness']);
    $first = Goal::where('user_id', $user->id)->orderBy('id')->first();

    $first->update(['is_archived' => true]);

    expect($first->fresh()->is_archived)->toBeTrue();
});

// ===== Action creation requires an active goal =====

it('Action::create throws a clear exception if the user has no active goal', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Force-archive every goal — bypasses the saving guard via raw query.
    Goal::query()->where('user_id', $user->id)->update(['is_archived' => true]);

    expect(fn () => Action::create(['title' => 'orphan', 'status' => 'pending']))
        ->toThrow(DomainException::class, 'no active goal');
});

it('Action::create still works when an explicit goal_id is given even if defaultGoal is null', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $explicit = Goal::create(['label' => 'fitness', 'name' => 'Explicit']);

    // Archive the observer goal so defaultGoal would return $explicit.
    Goal::query()->where('user_id', $user->id)
        ->where('id', '!=', $explicit->id)
        ->update(['is_archived' => true]);

    $action = Action::create([
        'goal_id' => $explicit->id,
        'title' => 'works',
        'status' => 'pending',
    ]);

    expect($action->goal_id)->toBe($explicit->id);
});
