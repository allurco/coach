<?php

use App\Domains\Finance\Models\Budget;
use App\Models\Action;
use App\Models\Goal;
use App\Models\User;
use App\Placeholders\PlaceholderHandler;
use App\Placeholders\PlaceholderRegistry;
use App\Services\PlaceholderRenderer;

/**
 * Dispatcher-level integration tests for PlaceholderRenderer.
 *
 * The per-handler rendering logic is covered by:
 *   - tests/Feature/Placeholders/BudgetPlaceholderTest.php
 *   - tests/Feature/Placeholders/PlanPlaceholderTest.php
 *
 * This file only asserts that the renderer (a) routes placeholders to
 * registered handlers, (b) leaves unknown ones untouched, and (c)
 * respects the explicit userId override.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->renderer = new PlaceholderRenderer(app(PlaceholderRegistry::class));
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

it('dispatches {{budget:N}} to the Finance BudgetPlaceholder handler', function () {
    $budget = makeBudget(['leisure_amount' => 600]);

    $result = $this->renderer->render("antes\n\n{{budget:{$budget->id}}}\n\ndepois");

    expect($result)
        ->toContain('antes')
        ->toContain('depois')
        ->toContain('Plano de Gastos')
        ->not->toContain('{{budget:');
});

it('dispatches {{plan}} to the core PlanPlaceholder handler', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Sair do vermelho']);
    Action::create([
        'goal_id' => $goal->id,
        'title' => 'Pedir extrato banco X',
        'status' => 'pending',
    ]);

    $result = $this->renderer->render("plano:\n\n{{plan}}");

    expect($result)
        ->toContain('Pedir extrato banco X')
        ->not->toContain('{{plan}}');
});

it('honours an explicit userId, ignoring auth()', function () {
    makeBudget(['leisure_amount' => 600]);

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

it('leaves placeholders untouched when no handler is registered', function () {
    $result = $this->renderer->render('hello {{unknown:thing}} world');

    expect($result)->toContain('{{unknown:thing}}')
        ->toContain('hello')
        ->toContain('world');
});

it('passes colon-separated args to the registered handler', function () {
    $registry = app(PlaceholderRegistry::class);
    $registry->register('echo', new class implements PlaceholderHandler
    {
        public function render(?int $userId, array $args): string
        {
            return 'user='.($userId ?? 'null').'|args='.implode(',', $args);
        }
    });

    $result = $this->renderer->render('a {{echo:foo:bar}} b', 42);

    expect($result)->toBe('a user=42|args=foo,bar b');
});
