<?php

use App\Models\Action;
use App\Models\Budget;
use App\Models\Goal;
use App\Models\User;
use App\Services\PlaceholderRenderer;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->renderer = new PlaceholderRenderer;
});

function makeBudget(array $overrides = []): Budget
{
    return Budget::create(array_merge([
        'goal_id' => null,
        'month' => now()->format('Y-m'),
        'net_income' => 7200,
        'fixed_costs_subtotal' => 4000,
        'fixed_costs_total' => 4000,
        'investments_total' => 720,
        'savings_total' => 480,
        'leisure_amount' => 2000,
    ], $overrides));
}

it('expands {{budget:N}} into the rendered snapshot table', function () {
    $budget = makeBudget(['leisure_amount' => 600]);

    $result = $this->renderer->render("antes\n\n{{budget:{$budget->id}}}\n\ndepois");

    expect($result)
        ->toContain('antes')
        ->toContain('depois')
        ->toContain('Plano de Gastos')
        ->not->toContain('{{budget:');
});

it('expands {{budget:current}} using the user’s latest budget', function () {
    makeBudget(['month' => '2026-04', 'leisure_amount' => -100]);
    makeBudget(['month' => '2026-05', 'leisure_amount' => 600]);

    $result = $this->renderer->render('aqui: {{budget:current}}');

    expect($result)
        ->toContain('Plano de Gastos')
        ->not->toContain('{{budget:current}}');
});

it('falls back when {{budget:current}} has no data', function () {
    $result = $this->renderer->render('aqui: {{budget:current}}');

    expect($result)->toContain((string) __('coach.placeholders.budget_missing'))
        ->not->toContain('{{budget:current}}');
});

it('falls back when {{budget:N}} references a missing snapshot', function () {
    $result = $this->renderer->render('antes {{budget:99999}} depois');

    expect($result)
        ->toContain('antes')
        ->toContain('depois')
        ->toContain((string) __('coach.placeholders.budget_missing'))
        ->not->toContain('{{budget:');
});

it('expands {{plan}} into the user’s open actions list', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Sair do vermelho']);
    Action::create([
        'goal_id' => $goal->id,
        'title' => 'Pedir extrato banco X',
        'status' => 'pending',
        'priority' => 'high',
    ]);
    Action::create([
        'goal_id' => $goal->id,
        'title' => 'Ligar pro contador',
        'status' => 'in_progress',
        'priority' => 'medium',
    ]);

    $result = $this->renderer->render("plano:\n\n{{plan}}");

    expect($result)
        ->toContain('Pedir extrato banco X')
        ->toContain('Ligar pro contador')
        ->not->toContain('{{plan}}');
});

it('falls back when {{plan}} has no actions', function () {
    $result = $this->renderer->render('plano: {{plan}}');

    expect($result)->toContain((string) __('coach.placeholders.plan_empty'))
        ->not->toContain('{{plan}}');
});

it('does not expand a {{plan}} from another user', function () {
    $other = User::factory()->create();
    $otherGoal = Goal::withoutGlobalScope('owner')->create([
        'user_id' => $other->id,
        'label' => 'finance',
        'name' => 'Other goal',
    ]);
    Action::withoutGlobalScope('owner')->create([
        'user_id' => $other->id,
        'goal_id' => $otherGoal->id,
        'title' => 'Other user secret',
        'status' => 'pending',
    ]);

    $result = $this->renderer->render('plano: {{plan}}');

    expect($result)
        ->not->toContain('Other user secret')
        ->toContain((string) __('coach.placeholders.plan_empty'));
});

it('honours an explicit userId, ignoring auth()', function () {
    $budget = makeBudget(['leisure_amount' => 600]);

    auth()->logout();

    $result = $this->renderer->render('{{budget:current}}', $this->user->id);

    expect($result)
        ->toContain('Plano de Gastos')
        ->not->toContain('{{budget:current}}');
});

it('handles multiple placeholders in one body', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Finance']);
    Action::create(['goal_id' => $goal->id, 'title' => 'Action A', 'status' => 'pending']);
    makeBudget(['leisure_amount' => 600]);

    $body = "Plan:\n{{plan}}\n\nBudget:\n{{budget:current}}";

    $result = $this->renderer->render($body);

    expect($result)
        ->toContain('Action A')
        ->toContain('Plano de Gastos')
        ->not->toContain('{{plan}}')
        ->not->toContain('{{budget:current}}');
});

it('leaves unknown placeholders untouched (so they’re visible to the user)', function () {
    $result = $this->renderer->render('hello {{unknown:thing}} world');

    expect($result)->toContain('{{unknown:thing}}')
        ->toContain('hello')
        ->toContain('world');
});
