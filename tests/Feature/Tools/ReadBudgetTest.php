<?php

use App\Ai\Tools\ReadBudget;
use App\Models\Budget;
use App\Models\User;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->tool = new ReadBudget;
});

/**
 * Production bug regression: ReadBudget used to return the literal string
 * '{{budget:current}}' so the chat's PlaceholderRenderer would expand it
 * before display. BUT tool results don't go through that renderer — they
 * go straight back to the LLM. The model would receive the raw placeholder
 * and report "the tool isn't working". The fix is to expand SERVER-SIDE
 * inside handle() so the LLM gets real budget data, not a template string.
 */
it('returns the fully-expanded markdown budget when the user has a budget', function () {
    $budget = Budget::create([
        'goal_id' => null,
        'month' => '2026-06',
        'net_income' => 24000,
        'fixed_costs_subtotal' => 10000,
        'fixed_costs_total' => 11500,
        'fixed_costs_breakdown' => ['Aluguel' => 6000, 'Mercado' => 4000],
        'investments_total' => 2400,
        'investments_breakdown' => ['Aposentadoria' => 2400],
        'savings_total' => 1200,
        'savings_breakdown' => ['Reservas' => 1200],
        'leisure_amount' => 8900,
    ]);

    $result = (string) $this->tool->handle(new Request([]));

    // The placeholder must not leak back to the LLM. Whatever shape the
    // renderer produces, it must contain the real numbers and bucket
    // names — not template syntax.
    expect($result)
        ->not->toContain('{{budget:current}}')
        ->not->toContain('{{')
        ->toContain('snapshot #'.$budget->id);

    // Spot-check the user's actual breakdown line items appear so the agent
    // can answer "how much for Reservas?" / "how much for Aluguel?".
    expect($result)
        ->toContain('Reservas')
        ->toContain('Aluguel');
});

it('returns a "no budget" message when the user has none', function () {
    $result = (string) $this->tool->handle(new Request([]));

    expect($result)->toMatch('/sem|none|no budget/iu');
});

it('returns "no budget" when the only budgets belong to another user', function () {
    $intruder = User::factory()->create();
    Budget::withoutGlobalScope('owner')->create([
        'user_id' => $intruder->id,
        'goal_id' => null,
        'month' => '2026-06',
        'net_income' => 99999,
        'fixed_costs_subtotal' => 0,
        'fixed_costs_total' => 0,
        'investments_total' => 0,
        'savings_total' => 0,
        'leisure_amount' => 99999,
    ]);

    $result = (string) $this->tool->handle(new Request([]));

    expect($result)->toMatch('/sem|none|no budget/iu')
        ->and($result)->not->toContain('99999')
        ->and($result)->not->toContain('99.999');
});

it('returns an unauth message when no user is logged in', function () {
    auth()->logout();

    $result = (string) $this->tool->handle(new Request([]));

    expect($result)->toMatch('/aut|usu|user|login/iu');
});

/**
 * Routing guardrail: when the user asks about a SPECIFIC bucket (investment,
 * emergency fund, leisure, fixed costs, income), the agent must route through
 * ReadBudget — not say "I don't know". The signal that tells the LLM to do
 * that lives in this tool's description(). If someone trims it, the routing
 * breaks silently and the agent regresses.
 */
it('description enumerates bucket-specific phrasings so the agent routes bucket questions here', function () {
    $description = mb_strtolower((string) $this->tool->description());

    expect($description)
        ->toContain('investment')
        ->toContain('emergency fund')
        ->toContain('leisure')
        ->toContain('net income')
        ->toContain('fixed costs');
});

/**
 * Specific line items (rent, groceries, transport) must also be in the
 * description — to prevent the case where the agent invents numbers when
 * asked "how much do I spend on groceries?" simply because the phrasing
 * didn't match a trigger. Anything in the breakdown should come from here,
 * not from imagination.
 */
it('description enumerates line-item phrasings and forbids inventing numbers', function () {
    $description = mb_strtolower((string) $this->tool->description());

    expect($description)
        ->toContain('rent')
        ->toContain('groceries')
        ->toContain('transport')
        ->toContain('breakdown')
        ->toContain('do not invent');
});
