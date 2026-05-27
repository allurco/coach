<?php

use App\Filament\Pages\Plan;
use App\Models\Action;
use App\Models\Goal;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('renders successfully', function () {
    Livewire::test(Plan::class)
        ->assertOk();
});

it('lists the users non-archived goals in the picker', function () {
    Goal::create(['label' => 'finance', 'name' => 'Finanças']);
    Goal::create(['label' => 'health', 'name' => 'Saúde']);
    Goal::create(['label' => 'fitness', 'name' => 'Arquivado', 'is_archived' => true]);

    Livewire::test(Plan::class)
        ->assertSeeText('Finanças')
        ->assertSeeText('Saúde')
        ->assertDontSeeText('Arquivado');
});

it('starts with no goal selected (showing all goals)', function () {
    Livewire::test(Plan::class)
        ->assertSet('selectedGoalId', null);
});

it('does not leak goals from another user', function () {
    $intruder = User::factory()->create();
    Goal::withoutGlobalScope('owner')->create([
        'user_id' => $intruder->id,
        'label' => 'finance',
        'name' => 'Intruder goal',
    ]);

    Livewire::test(Plan::class)
        ->assertDontSeeText('Intruder goal');
});

it('dispatches plan-refreshed with the new goal id when the picker changes', function () {
    $finance = Goal::create(['label' => 'finance', 'name' => 'Finanças']);

    Livewire::test(Plan::class)
        ->set('selectedGoalId', $finance->id)
        ->assertDispatched('plan-refreshed', activeGoalId: $finance->id);
});

it('embeds the PlanFlyout component which then shows the users actions', function () {
    $goal = $this->user->defaultGoal();
    Action::create(['goal_id' => $goal->id, 'title' => 'Pagar fatura', 'status' => 'pending']);
    Action::create(['goal_id' => $goal->id, 'title' => 'Falar com contador', 'status' => 'pending']);

    Livewire::test(Plan::class)
        ->assertSeeText('Pagar fatura')
        ->assertSeeText('Falar com contador');
});
