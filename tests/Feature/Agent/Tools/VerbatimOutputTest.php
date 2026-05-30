<?php

use App\Agent\Agents\CoachAgent;
use App\Agent\Services\CoachInteraction;
use App\Agent\Tools\VerbatimOutput;
use App\Domains\Finance\Tools\BudgetSnapshot;
use App\Domains\Finance\Tools\ReadBudget;
use App\Models\User;

/**
 * Architecture-level guarantees for the verbatim-output capability flag
 * (candidate B of the agent-tool capability work). The Agent layer no
 * longer hard-codes a pack's tool class name — packs tag their own
 * tools with the marker interface. See ADR 0006 (packs self-register).
 */
it('flags BudgetSnapshot as a verbatim-output tool via the marker interface', function () {
    expect(new BudgetSnapshot)->toBeInstanceOf(VerbatimOutput::class);
});

it('does not flag non-verbatim tools like ReadBudget', function () {
    expect(new ReadBudget)->not->toBeInstanceOf(VerbatimOutput::class);
});

it('removes the hard-coded VERBATIM_TOOLS constant from CoachInteraction', function () {
    // Regression guard: if someone re-introduces a hard-coded list of
    // pack tool names on the Agent service, this fails. Packs are
    // expected to self-tag with the VerbatimOutput marker interface.
    $reflection = new ReflectionClass(CoachInteraction::class);

    expect($reflection->hasConstant('VERBATIM_TOOLS'))->toBeFalse();
});

it('exposes verbatim tool names from CoachAgent by scanning registered tools', function () {
    // The Agent service identifies verbatim tools by name during streaming
    // (the tool-result event only carries a name string, not the instance).
    // CoachAgent maps name -> instance once at turn start, so the marker
    // interface check stays close to the registered tools.
    $user = User::factory()->create();
    $this->actingAs($user);

    $coach = new CoachAgent;

    expect($coach->verbatimToolNames())
        ->toBeArray()
        ->toContain('BudgetSnapshot')
        ->not->toContain('ReadBudget')
        ->not->toContain('CreateAction');
});
