<?php

use App\Agent\Filament\Pages\Coach;
use App\Models\Goal;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('returns null when no goal is active', function () {
    $page = new Coach;
    $page->goals = [];
    $page->activeGoalId = null;

    expect($page->activeGoal())->toBeNull();
});

it('returns null when activeGoalId points to a goal not in the sidebar list', function () {
    $page = new Coach;
    $page->goals = [
        ['id' => 1, 'name' => 'Saúde', 'label' => 'health', 'is_archived' => false, 'last_activity_label' => null],
    ];
    $page->activeGoalId = 999;

    expect($page->activeGoal())->toBeNull();
});

it('returns the matching goal array when activeGoalId hits one of the sidebar goals', function () {
    $page = new Coach;
    $page->goals = [
        ['id' => 1, 'name' => 'Saúde', 'label' => 'health', 'is_archived' => false, 'last_activity_label' => null],
        ['id' => 2, 'name' => 'Finanças', 'label' => 'finance', 'is_archived' => false, 'last_activity_label' => 'agora'],
    ];
    $page->activeGoalId = 2;

    $active = $page->activeGoal();

    expect($active)->not->toBeNull()
        ->and($active['id'])->toBe(2)
        ->and($active['name'])->toBe('Finanças')
        ->and($active['label'])->toBe('finance');
});

it('returns null defensively when goals is empty even with an activeGoalId set', function () {
    $page = new Coach;
    $page->goals = [];
    $page->activeGoalId = 1;

    expect($page->activeGoal())->toBeNull();
});

it('lands on the Goals start screen with no active goal, and resolves once a goal is opened', function () {
    // Per ADR 0007 the Workspace lands on the Goals start screen when there
    // is no ?goal — mount() no longer auto-activates a goal.
    $page = new Coach;
    $page->mount();

    expect($page->activeGoalId)->toBeNull();

    // Opening a goal (UserObserver auto-creates "Geral") enters its Workspace
    // and activeGoal() resolves the pairing.
    $goal = Goal::query()->first();
    $page->setActiveGoal($goal->id);

    $active = $page->activeGoal();
    expect($page->activeGoalId)->toBe($goal->id)
        ->and($active)->not->toBeNull()
        ->and($active['id'])->toBe($goal->id);
});
