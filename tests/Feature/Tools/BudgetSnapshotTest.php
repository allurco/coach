<?php

use App\Ai\Tools\BudgetSnapshot;
use App\Models\Budget;
use App\Models\Goal;
use App\Models\User;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('persists a snapshot with all four buckets and computed leisure', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Vida financeira']);

    $tool = new BudgetSnapshot($goal->id);
    $result = $tool->handle(new Request([
        'net_income' => 7200,
        'month' => '2026-05',
        'fixed_costs' => [
            'Aluguel' => 1500,
            'Contas' => 300,
            'Mercado' => 800,
        ],
        'investments' => [
            'Aposentadoria' => 500,
        ],
        'savings' => [
            'Reserva' => 200,
        ],
    ]));

    $budget = Budget::first();
    expect($budget)->not->toBeNull()
        ->and((float) $budget->net_income)->toBe(7200.0)
        ->and($budget->month)->toBe('2026-05')
        ->and($budget->goal_id)->toBe($goal->id)
        ->and((float) $budget->fixed_costs_subtotal)->toBe(2600.0)
        ->and((float) $budget->fixed_costs_total)->toBe(2990.0) // 2600 + 15% = 2990
        ->and((float) $budget->investments_total)->toBe(500.0)
        ->and((float) $budget->savings_total)->toBe(200.0)
        ->and((float) $budget->leisure_amount)->toBe(3510.0); // 7200 - 2990 - 500 - 200

    expect((string) $result)
        ->toContain('Custos Fixos')
        ->toContain('Investimentos')
        ->toContain('Reservas')
        ->toContain('Lazer');
});

it('flags buckets that fall outside target range', function () {
    $tool = new BudgetSnapshot(null);

    // Fixed costs WAY too high (90% of income), investments at 0 — both
    // should flag as ⚠ in the output.
    $result = (string) $tool->handle(new Request([
        'net_income' => 5000,
        'fixed_costs' => ['Aluguel' => 4000],     // 4600 with buffer = 92% (alvo 50-60% ⚠)
        'investments' => [],                       // 0% (alvo 10% ⚠)
        'savings' => [],                           // 0% (alvo 5-10% ⚠)
    ]));

    expect($result)
        ->toContain('⚠');
});

it('marks buckets as ✓ when within target range', function () {
    $tool = new BudgetSnapshot(null);

    // Fixed: 50% of 7200 = 3600 (with buffer ~3105 if subtotal is 2700)
    // Let's compute: subtotal X, X * 1.15 should be ~50%-60% of 7200
    // 7200 * 0.55 = 3960. So subtotal ~3443 → buffer 516 → total 3959.
    $result = (string) $tool->handle(new Request([
        'net_income' => 7200,
        'fixed_costs' => ['Tudo' => 3443],   // total ≈ 3959 ≈ 55%
        'investments' => ['401k' => 720],    // 10%
        'savings' => ['Reserva' => 540],     // 7.5%
    ]));

    // Should have at least one ✓ for in-range buckets
    expect($result)->toContain('✓');
});

it('refuses zero or negative income', function () {
    $tool = new BudgetSnapshot(null);

    $result = (string) $tool->handle(new Request([
        'net_income' => 0,
        'fixed_costs' => [],
        'investments' => [],
        'savings' => [],
    ]));

    expect($result)->toMatch('/erro|renda|income|invalid|invalida/iu')
        ->and(Budget::count())->toBe(0);
});

it('defaults month to the current YYYY-MM when none given', function () {
    $tool = new BudgetSnapshot(null);

    $tool->handle(new Request([
        'net_income' => 5000,
        'fixed_costs' => ['x' => 1000],
        'investments' => [],
        'savings' => [],
    ]));

    expect(Budget::first()->month)->toBe(now()->format('Y-m'));
});

it('is scoped to the authenticated user (multi-tenant)', function () {
    $other = User::factory()->create();

    (new BudgetSnapshot(null))->handle(new Request([
        'net_income' => 5000,
        'fixed_costs' => ['x' => 1000],
        'investments' => [],
        'savings' => [],
    ]));

    expect(Budget::first()->user_id)->toBe($this->user->id);

    auth()->logout();
    auth()->login($other);

    expect(Budget::count())->toBe(0); // global scope hides it
});

it('warns when income does not cover the planned buckets (negative leisure)', function () {
    $tool = new BudgetSnapshot(null);

    $result = (string) $tool->handle(new Request([
        'net_income' => 3000,
        'fixed_costs' => ['Aluguel' => 2000, 'Contas' => 500], // total = 2500 + 15% = 2875
        'investments' => ['Aposentadoria' => 300],
        'savings' => ['Reserva' => 200],                       // sum: 2875+300+200 = 3375 > 3000
    ]));

    expect($result)->toMatch('/déficit|deficit|negativ|estouro|exceeds|excede/iu')
        ->and((float) Budget::first()->leisure_amount)->toBeLessThan(0);
});
