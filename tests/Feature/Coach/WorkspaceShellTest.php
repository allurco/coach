<?php

use App\Agent\Filament\Pages\Coach;
use App\Models\Goal;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('lands on the Goals start screen when there is no ?goal', function () {
    Livewire::test(Coach::class)
        ->assertSet('activeGoalId', null)
        ->assertSeeHtml('goals-screen')
        ->assertSeeHtml('wire:click="setActiveGoal');
});

it('opens a Workspace directly when ?goal= deep-links to one of the user\'s goals', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Sair do vermelho']);

    Livewire::withQueryParams(['goal' => $goal->id])
        ->test(Coach::class)
        ->assertSet('activeGoalId', $goal->id)
        ->assertSeeHtml('coach-shell');
});

it('falls back to the Goals screen when ?goal= points at another user\'s goal (multi-tenant)', function () {
    $other = User::factory()->create();
    $foreign = Goal::withoutGlobalScope('owner')->create([
        'user_id' => $other->id,
        'label' => 'finance',
        'name' => 'Not yours',
    ]);

    Livewire::withQueryParams(['goal' => $foreign->id])
        ->test(Coach::class)
        ->assertSet('activeGoalId', null);
});

it('enters the Workspace when a goal is selected from the start screen', function () {
    $goal = Goal::query()->first(); // auto-created "Geral"

    Livewire::test(Coach::class)
        ->call('setActiveGoal', $goal->id)
        ->assertSet('activeGoalId', $goal->id)
        ->assertSeeHtml('coach-shell');
});

it('returns to the Goals start screen and clears the thread via backToGoals', function () {
    $goal = Goal::query()->first();

    Livewire::test(Coach::class)
        ->call('setActiveGoal', $goal->id)
        ->assertSet('activeGoalId', $goal->id)
        ->call('backToGoals')
        ->assertSet('activeGoalId', null)
        ->assertSet('messages', [])
        ->assertSet('conversationId', null)
        ->assertSeeHtml('goals-screen');
});

it('lists the user\'s goals as cards on the start screen', function () {
    Goal::create(['label' => 'finance', 'name' => 'Sair do vermelho']);
    Goal::create(['label' => 'fitness', 'name' => 'Voltar a treinar']);

    $html = (string) Livewire::test(Coach::class)->html();

    expect($html)
        ->toContain('Sair do vermelho')
        ->toContain('Voltar a treinar');
});
