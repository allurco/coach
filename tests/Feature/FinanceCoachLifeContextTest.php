<?php

use App\Ai\Agents\FinanceCoach;
use App\Models\Action;
use App\Models\Budget;
use App\Models\Goal;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function lifeContextOf(FinanceCoach $coach): string
{
    $ref = new ReflectionMethod($coach, 'lifeContext');
    $ref->setAccessible(true);

    return (string) $ref->invoke($coach);
}

function makeLifeContextBudget(array $overrides = []): Budget
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

function seedActiveAction(int $goalId): void
{
    Action::create([
        'goal_id' => $goalId,
        'title' => 'seed action',
        'status' => 'pendente',
    ]);
}

it('reports monthly slack when leisure_amount is positive', function () {
    $finance = Goal::create(['label' => 'finance', 'name' => 'Vida financeira']);
    seedActiveAction($finance->id);
    makeLifeContextBudget(['goal_id' => $finance->id, 'leisure_amount' => 850]);

    $expected = __('coach.life_context.budget.surplus', ['amount' => 'R$ 850']);

    expect(lifeContextOf((new FinanceCoach)->forGoal($finance->id)))
        ->toContain((string) $expected);
});

it('reports a deficit when leisure_amount is negative', function () {
    $finance = Goal::create(['label' => 'finance', 'name' => 'Vida financeira']);
    seedActiveAction($finance->id);
    makeLifeContextBudget(['goal_id' => $finance->id, 'leisure_amount' => -280]);

    $expected = __('coach.life_context.budget.deficit', ['amount' => 'R$ 280']);

    expect(lifeContextOf((new FinanceCoach)->forGoal($finance->id)))
        ->toContain((string) $expected);
});

it('reports balanced when leisure_amount is zero', function () {
    $finance = Goal::create(['label' => 'finance', 'name' => 'Vida financeira']);
    seedActiveAction($finance->id);
    makeLifeContextBudget(['goal_id' => $finance->id, 'leisure_amount' => 0]);

    expect(lifeContextOf((new FinanceCoach)->forGoal($finance->id)))
        ->toContain((string) __('coach.life_context.budget.balanced'));
});

it('reports "no budget" when the user has none', function () {
    $finance = Goal::create(['label' => 'finance', 'name' => 'Vida financeira']);
    seedActiveAction($finance->id);

    expect(lifeContextOf((new FinanceCoach)->forGoal($finance->id)))
        ->toContain((string) __('coach.life_context.budget.none'));
});

it('surfaces the budget when the active goal is fitness (cross-goal visibility)', function () {
    $finance = Goal::create(['label' => 'finance', 'name' => 'Vida financeira']);
    $fitness = Goal::create(['label' => 'fitness', 'name' => 'Voltar a treinar']);
    seedActiveAction($fitness->id);
    makeLifeContextBudget(['goal_id' => $finance->id, 'leisure_amount' => -300]);

    $expected = __('coach.life_context.budget.deficit', ['amount' => 'R$ 300']);

    expect(lifeContextOf((new FinanceCoach)->forGoal($fitness->id)))
        ->toContain((string) $expected);
});

it('surfaces the budget when the active goal is general (no-specialization)', function () {
    $finance = Goal::create(['label' => 'finance', 'name' => 'Vida financeira']);
    $general = Goal::create(['label' => 'general', 'name' => 'Geral']);
    seedActiveAction($general->id);
    makeLifeContextBudget(['goal_id' => $finance->id, 'leisure_amount' => 1200]);

    $expected = __('coach.life_context.budget.surplus', ['amount' => 'R$ 1.200']);

    expect(lifeContextOf((new FinanceCoach)->forGoal($general->id)))
        ->toContain((string) $expected);
});

it('is included in instructions() when not onboarding', function () {
    $finance = Goal::create(['label' => 'finance', 'name' => 'Vida financeira']);
    seedActiveAction($finance->id);
    makeLifeContextBudget(['goal_id' => $finance->id, 'leisure_amount' => 750]);

    $instructions = (string) (new FinanceCoach)->forGoal($finance->id)->instructions();

    expect($instructions)
        ->toContain((string) __('coach.life_context.header'))
        ->toContain((string) __('coach.life_context.budget.surplus', ['amount' => 'R$ 750']));
});

it('is NOT included during onboarding (Action::count() === 0)', function () {
    $finance = Goal::create(['label' => 'finance', 'name' => 'Vida financeira']);
    // No seedActiveAction — onboarding state.
    makeLifeContextBudget(['goal_id' => $finance->id, 'leisure_amount' => 750]);

    $instructions = (string) (new FinanceCoach)->forGoal($finance->id)->instructions();

    expect($instructions)
        ->not->toContain((string) __('coach.life_context.header'))
        ->not->toContain((string) __('coach.life_context.budget.surplus', ['amount' => 'R$ 750']));
});

it('does not leak another user’s budget (multi-tenant isolation)', function () {
    $finance = Goal::create(['label' => 'finance', 'name' => 'Mine']);
    seedActiveAction($finance->id);

    $other = User::factory()->create();
    Budget::withoutGlobalScope('owner')->create([
        'user_id' => $other->id,
        'goal_id' => null,
        'month' => now()->format('Y-m'),
        'net_income' => 9999,
        'fixed_costs_subtotal' => 1000,
        'fixed_costs_total' => 1000,
        'investments_total' => 0,
        'savings_total' => 0,
        'leisure_amount' => 8999,
    ]);

    $context = lifeContextOf((new FinanceCoach)->forGoal($finance->id));

    expect($context)
        ->toContain((string) __('coach.life_context.budget.none'))
        ->not->toContain('8.999')
        ->not->toContain('8999');
});

it('uses the most recent budget when the user has several', function () {
    $finance = Goal::create(['label' => 'finance', 'name' => 'Vida financeira']);
    seedActiveAction($finance->id);

    makeLifeContextBudget([
        'goal_id' => $finance->id,
        'month' => '2026-04',
        'leisure_amount' => -100,
    ]);
    makeLifeContextBudget([
        'goal_id' => $finance->id,
        'month' => '2026-05',
        'leisure_amount' => 600,
    ]);

    expect(lifeContextOf((new FinanceCoach)->forGoal($finance->id)))
        ->toContain((string) __('coach.life_context.budget.surplus', ['amount' => 'R$ 600']));
});
