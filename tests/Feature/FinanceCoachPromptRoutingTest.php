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

/**
 * Lock guardrail: o prompt principal deve carregar a REGRA DURA que proíbe
 * inventar números monetários. Sem isso o agente alucina valores ("R$ 822,72
 * em Food/restaurantes" quando a categoria nem existe no breakdown). Travamos
 * a redação dessa regra em teste — se alguém apagar acidentalmente, a suíte
 * quebra antes de regredir em produção.
 */
it('main prompt carries the hard rule that forbids inventing budget numbers', function () {
    $coach = new FinanceCoach;
    $prompt = mb_strtolower((string) $coach->instructions());

    expect($prompt)
        ->toContain('regra dura')
        ->toContain('não invente')
        ->toContain('única fonte de verdade');
});
