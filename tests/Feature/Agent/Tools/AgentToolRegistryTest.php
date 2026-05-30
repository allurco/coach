<?php

use App\Agent\Agents\CoachAgent;
use App\Agent\Tools\AgentToolRegistry;
use App\Domains\Finance\Tools\BudgetSnapshot;
use App\Domains\Finance\Tools\ReadBudget;
use App\Models\User;

/**
 * AgentToolRegistry — the seam that closes the last Agent ⇄ Pack leak.
 * CoachAgent::tools() used to hard-code `new BudgetSnapshot` / `new ReadBudget`
 * (pack imports in the Agent layer). Now packs push factory closures into
 * this registry from their ServiceProvider — same shape as ToolRegistry /
 * PlaceholderRegistry / TipResolver (ADR 0006).
 */
it('builds an empty list when no factories are registered', function () {
    $registry = new AgentToolRegistry;
    $agent = new CoachAgent;

    expect($registry->build($agent))->toBe([]);
});

it('register/build returns the instances the factory produced', function () {
    $registry = (new AgentToolRegistry)
        ->register(fn () => new stdClass)
        ->register(fn () => new ArrayObject);

    $built = $registry->build(new CoachAgent);

    expect($built)->toHaveCount(2)
        ->and($built[0])->toBeInstanceOf(stdClass::class)
        ->and($built[1])->toBeInstanceOf(ArrayObject::class);
});

it('passes the CoachAgent into each factory so stateful tools can read agent state', function () {
    $captured = null;
    $registry = (new AgentToolRegistry)
        ->register(function (CoachAgent $a) use (&$captured) {
            $captured = $a;

            return new stdClass;
        });

    $agent = new CoachAgent;
    $registry->build($agent);

    expect($captured)->toBe($agent);
});

it('register is chainable (same shape as ToolRegistry / PlaceholderRegistry)', function () {
    $registry = new AgentToolRegistry;

    $returned = $registry->register(fn () => new stdClass);

    expect($returned)->toBe($registry);
});

it('CoachAgent::tools() includes the Finance pack tools via the registry', function () {
    // The full app has booted, so FinanceServiceProvider has already
    // extended the AgentToolRegistry with BudgetSnapshot + ReadBudget.
    // CoachAgent::tools() must merge them into its returned list — no
    // direct pack imports in CoachAgent itself.
    $user = User::factory()->create();
    $this->actingAs($user);

    $agent = new CoachAgent;
    $toolClasses = array_map(fn ($t) => $t::class, [...$agent->tools()]);

    expect($toolClasses)->toContain(BudgetSnapshot::class)
        ->toContain(ReadBudget::class);
});
