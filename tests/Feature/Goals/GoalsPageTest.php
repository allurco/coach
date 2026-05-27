<?php

use App\Filament\Pages\Goals;
use App\Models\Action;
use App\Models\Goal;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('renders successfully', function () {
    Livewire::test(Goals::class)->assertOk();
});

it('lists active and archived goals separately', function () {
    Goal::create(['label' => 'finance', 'name' => 'Pagar dívidas']);
    Goal::create(['label' => 'health', 'name' => 'Voltar a treinar']);
    Goal::create(['label' => 'fitness', 'name' => 'Goal antigo', 'is_archived' => true]);

    $component = Livewire::test(Goals::class);

    $active = $component->get('activeGoals');
    $archived = $component->get('archivedGoals');

    expect(collect($active)->pluck('name'))->toContain('Pagar dívidas', 'Voltar a treinar');
    expect(collect($archived)->pluck('name'))->toContain('Goal antigo');
});

it('includes the open action count per goal', function () {
    $finance = Goal::create(['label' => 'finance', 'name' => 'Finanças']);
    Action::create(['goal_id' => $finance->id, 'title' => 'a', 'status' => 'pending']);
    Action::create(['goal_id' => $finance->id, 'title' => 'b', 'status' => 'in_progress']);
    Action::create(['goal_id' => $finance->id, 'title' => 'c', 'status' => 'completed']);

    $component = Livewire::test(Goals::class);
    $row = collect($component->get('activeGoals'))->firstWhere('name', 'Finanças');

    expect($row['open_count'])->toBe(2);
});

it('does not leak goals from another user', function () {
    $intruder = User::factory()->create();
    Goal::withoutGlobalScope('owner')->create([
        'user_id' => $intruder->id,
        'label' => 'finance',
        'name' => 'Intruder goal',
    ]);

    $component = Livewire::test(Goals::class);

    $names = collect($component->get('activeGoals'))->pluck('name')
        ->merge(collect($component->get('archivedGoals'))->pluck('name'));

    expect($names)->not->toContain('Intruder goal');
});

it('archives a goal when another active goal exists', function () {
    Goal::create(['label' => 'finance', 'name' => 'Finanças']);
    $health = Goal::create(['label' => 'health', 'name' => 'Saúde']);

    Livewire::test(Goals::class)
        ->call('archive', $health->id);

    expect($health->fresh()->is_archived)->toBeTrue();
});

it('refuses to archive the last active goal (safeguard from the Goal model)', function () {
    // Default goal is created by UserObserver — that's the only active one.
    $only = $this->user->defaultGoal();

    Livewire::test(Goals::class)
        ->call('archive', $only->id);

    // Safeguard throws in the model; the page swallows the exception.
    // The goal must remain active.
    expect($only->fresh()->is_archived)->toBeFalse();
});

it('unarchives an archived goal', function () {
    $stale = Goal::create(['label' => 'fitness', 'name' => 'Stale', 'is_archived' => true]);

    Livewire::test(Goals::class)
        ->call('unarchive', $stale->id);

    expect($stale->fresh()->is_archived)->toBeFalse();
});

it('reorders by swapping sort_order with the neighbour', function () {
    Goal::query()->delete();
    $a = Goal::create(['label' => 'finance', 'name' => 'A', 'sort_order' => 1]);
    $b = Goal::create(['label' => 'health', 'name' => 'B', 'sort_order' => 2]);
    $c = Goal::create(['label' => 'learning', 'name' => 'C', 'sort_order' => 3]);

    Livewire::test(Goals::class)
        ->call('moveGoal', $b->id, 'down');

    expect($a->fresh()->sort_order)->toBe(1);
    expect($b->fresh()->sort_order)->toBe(3);
    expect($c->fresh()->sort_order)->toBe(2);
});

it('does not move past the bounds', function () {
    Goal::query()->delete();
    $a = Goal::create(['label' => 'finance', 'name' => 'A', 'sort_order' => 1]);

    Livewire::test(Goals::class)
        ->call('moveGoal', $a->id, 'up');

    // No neighbour above; sort_order unchanged.
    expect($a->fresh()->sort_order)->toBe(1);
});

it('creates a new goal via the modal', function () {
    Livewire::test(Goals::class)
        ->call('openNewGoal')
        ->set('newGoalName', 'Saúde mental')
        ->set('newGoalLabel', 'emotional')
        ->call('createGoal');

    $created = Goal::where('name', 'Saúde mental')->first();
    expect($created)->not->toBeNull();
    expect($created->label)->toBe('emotional');
});

it('refuses to create a goal with an empty name', function () {
    $before = Goal::count();

    Livewire::test(Goals::class)
        ->call('openNewGoal')
        ->set('newGoalName', '   ')
        ->call('createGoal');

    expect(Goal::count())->toBe($before);
});

it('updates an existing goals name and label', function () {
    $goal = Goal::create(['label' => 'general', 'name' => 'Old name']);

    Livewire::test(Goals::class)
        ->call('startEdit', $goal->id)
        ->set('editName', 'New name')
        ->set('editLabel', 'finance')
        ->call('confirmEdit');

    $fresh = $goal->fresh();
    expect($fresh->name)->toBe('New name');
    expect($fresh->label)->toBe('finance');
});
