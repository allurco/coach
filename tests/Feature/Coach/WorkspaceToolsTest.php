<?php

use App\Agent\Filament\Pages\Coach;
use App\Models\Goal;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function coachOnGoal(string $label): Coach
{
    $goal = Goal::create(['label' => $label, 'name' => ucfirst($label)]);
    $page = new Coach;
    $page->mount();
    $page->setActiveGoal($goal->id);

    return $page;
}

it('exposes no workspace tools on the Goals start screen', function () {
    $page = new Coach;
    $page->mount();

    expect($page->activeGoalId)->toBeNull()
        ->and($page->workspaceTools())->toBe([])
        ->and($page->primaryToolKey())->toBeNull();
});

it('exposes core tools (plan, contacts) for any goal', function () {
    $keys = collect(coachOnGoal('general')->workspaceTools())->pluck('key')->all();

    expect($keys)->toContain('plan')->toContain('contacts');
});

it('adds the Finance budget tool (and marks it primary) on a finance goal', function () {
    $page = coachOnGoal('finance');
    $keys = collect($page->workspaceTools())->pluck('key')->all();

    expect($keys)->toContain('budget')
        ->and($page->primaryToolKey())->toBe('budget');
});

it('does not expose the budget tool on a non-finance goal', function () {
    $page = coachOnGoal('fitness');

    expect(collect($page->workspaceTools())->pluck('key')->all())->not->toContain('budget')
        ->and($page->primaryToolKey())->toBeNull();
});

it('resolves tool labels through translation (not raw keys)', function () {
    $tools = collect(coachOnGoal('finance')->workspaceTools());

    expect($tools->firstWhere('key', 'plan')['label'])->toBe(__('tools.plan'))
        ->and($tools->firstWhere('key', 'budget')['label'])->toBe(__('finance::budget_flyout.toggle'));
});

it('opens an available tool and closes back to chat', function () {
    $page = coachOnGoal('finance');

    $page->openTool('budget');
    expect($page->activeTool)->toBe('budget');

    $page->closeTool();
    expect($page->activeTool)->toBeNull();
});

it('ignores opening a tool not available for the active goal', function () {
    $page = coachOnGoal('fitness'); // no budget tool here

    $page->openTool('budget');

    expect($page->activeTool)->toBeNull();
});

it('closes any open tool when switching goals', function () {
    $page = coachOnGoal('finance');
    $page->openTool('budget');
    expect($page->activeTool)->toBe('budget');

    $other = Goal::create(['label' => 'fitness', 'name' => 'Fitness']);
    $page->setActiveGoal($other->id);

    expect($page->activeTool)->toBeNull();
});

it('clears the open tool on backToGoals', function () {
    $page = coachOnGoal('finance');
    $page->openTool('plan');

    $page->backToGoals();

    expect($page->activeTool)->toBeNull()
        ->and($page->activeGoalId)->toBeNull();
});
