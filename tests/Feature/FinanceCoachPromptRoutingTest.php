<?php

use App\Ai\Agents\FinanceCoach;
use App\Models\Action;
use App\Models\Budget;
use App\Models\Goal;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Skip onboarding branch: needs at least 1 action to land in the
    // main instructions() block that documents tool routing.
    $goal = Goal::create(['label' => 'finance', 'name' => 'Vida financeira']);
    Action::create([
        'goal_id' => $goal->id,
        'title' => 'seed action',
        'status' => 'pendente',
        'priority' => 'media',
        'category' => 'general',
    ]);
});

/**
 * Routing guardrail: the main system prompt must teach the model that
 * bucket-specific questions (investimento, reserva, lazer, custos, renda)
 * route through ReadBudget. Without this, the agent says "não sei" when
 * the user asks "quanto tenho pra investimento?" even though the answer
 * is sitting in the budget table.
 */
it('main prompt instructs bucket questions to go through ReadBudget', function () {
    $coach = new FinanceCoach;
    $prompt = mb_strtolower((string) $coach->instructions());

    // ReadBudget paragraph must be present
    expect($prompt)->toContain('readbudget');

    // And it must enumerate bucket-specific phrasings, so the LLM
    // routes those questions correctly instead of replying blank.
    expect($prompt)
        ->toContain('investiment')
        ->toContain('reserva')
        ->toContain('lazer')
        ->toContain('renda')
        ->toContain('custos');
});
