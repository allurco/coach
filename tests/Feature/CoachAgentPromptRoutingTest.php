<?php

use App\Ai\Agents\CoachAgent;
use App\Models\Action;
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
 * bucket-specific questions (investment, reserve, leisure, fixed costs,
 * income) route through ReadBudget. Without this, the agent says "I don't
 * know" when the user asks "how much for investment?" even though the
 * answer is sitting in the budget table.
 */
it('main prompt instructs bucket questions to go through ReadBudget', function () {
    $coach = new CoachAgent;
    $prompt = mb_strtolower((string) $coach->instructions());

    // ReadBudget paragraph must be present
    expect($prompt)->toContain('readbudget');

    // And it must enumerate bucket-specific phrasings, so the LLM routes
    // those questions correctly instead of replying blank.
    expect($prompt)
        ->toContain('investment')
        ->toContain('emergency fund')
        ->toContain('leisure')
        ->toContain('net income')
        ->toContain('fixed costs');
});

/**
 * Lock guardrail: the main prompt must carry the HARD RULE that forbids
 * inventing monetary numbers. Without it the agent hallucinates values
 * ("$822.72 in Food/restaurants" when the category doesn't even exist in
 * the breakdown). We lock the phrasing in tests — if someone removes it
 * accidentally, the suite breaks before the regression hits production.
 */
it('main prompt carries the hard rule that forbids inventing budget numbers', function () {
    $coach = new CoachAgent;
    $prompt = mb_strtolower((string) $coach->instructions());

    expect($prompt)
        ->toContain('hard rule')
        ->toContain("don't invent")
        ->toContain('only source of truth');
});

/**
 * Locale-aware tone: the prompt must inject a voice block that matches the
 * user's locale. pt_BR users get PT voice examples, en users get EN voice
 * examples. This is what makes the agent feel native instead of translated.
 */
it('injects Brazilian Portuguese voice examples when user locale is pt_BR', function () {
    $this->user->update(['locale' => 'pt_BR']);

    $coach = new CoachAgent;
    $prompt = (string) $coach->instructions();

    expect($prompt)
        ->toContain('Brazilian Portuguese')
        ->toContain('Eai')
        ->toContain('Bora');
});

it('injects casual American English voice examples when user locale is en', function () {
    $this->user->update(['locale' => 'en']);

    $coach = new CoachAgent;
    $prompt = (string) $coach->instructions();

    expect($prompt)
        ->toContain('American English')
        ->toContain("Let's tackle")
        ->not->toContain('Eai, beleza?');
});

/**
 * Locale knowledge: the plug-and-play file under resources/prompts/locale/
 * must be injected into the prompt under "Local fiscal/cultural context" so
 * the model has access to country-specific terms (PJ, INSS, DARF for pt_BR;
 * LLC, IRS, 1099 for en_US). Contributors add a locale by dropping a
 * new markdown file — this test guards the loader.
 */
it('loads pt_BR locale knowledge when user locale is pt_BR', function () {
    $this->user->update(['locale' => 'pt_BR']);

    $coach = new CoachAgent;
    $prompt = (string) $coach->instructions();

    expect($prompt)
        ->toContain('Local fiscal/cultural context')
        ->toContain('PJ')   // Brazilian business entity
        ->toContain('INSS') // Brazilian social security
        ->toContain('DARF'); // Brazilian federal tax slip
});

it('loads en_US locale knowledge when user locale is en', function () {
    $this->user->update(['locale' => 'en']);

    $coach = new CoachAgent;
    $prompt = (string) $coach->instructions();

    expect($prompt)
        ->toContain('Local fiscal/cultural context')
        ->toContain('LLC')   // US business entity
        ->toContain('IRS')   // US tax authority
        ->toContain('1099'); // US contractor tax form
});

/**
 * Security guardrail: the user's `locale` column flows into a resource_path()
 * concatenation in localeKnowledge(). Without validation, a malicious value
 * like "../../../etc/passwd" would let the loader escape the locale directory
 * and read arbitrary files. resolveLocale() must filter against an allowlist
 * regex BEFORE the path is built.
 */
it('rejects locale values that could escape the prompts/locale directory', function () {
    $coach = new CoachAgent;
    $resolveLocale = new ReflectionMethod($coach, 'resolveLocale');
    $resolveLocale->setAccessible(true);

    $malicious = [
        '../../../etc/passwd',
        '..\\..\\windows\\system32',
        '/etc/passwd',
        'pt_BR/../en_US',
        'en_US.md', // file extension shouldn't slip through
        '',
        '   ',
        'a',                  // too short
        'toolong_XX',         // wrong shape
        'PT_br',              // wrong case on country code
    ];

    foreach ($malicious as $input) {
        $this->user->update(['locale' => $input]);
        expect($resolveLocale->invoke($coach))
            ->toBe('en_US', "locale '{$input}' should fall back to en_US, got: ".$resolveLocale->invoke($coach));
    }
});

/**
 * Fallback chain: when the user's locale has no markdown file yet (e.g. a
 * Spanish user before es_ES.md exists), localeKnowledge() must return the
 * en_US content rather than empty — that way the agent still has SOME
 * fiscal/cultural baseline instead of nothing.
 */
it('falls back to en_US knowledge when the user locale file does not exist', function () {
    $this->user->update(['locale' => 'fr_FR']);

    $coach = new CoachAgent;
    $prompt = (string) $coach->instructions();

    // fr_FR.md doesn't exist yet — should pull en_US content
    expect($prompt)
        ->toContain('LLC')
        ->toContain('IRS');
});

/**
 * Normalization: bare `en` should map to `en_US` (not silently fall through
 * the candidate chain to the fallback). Same for dash-form `en-US` →
 * `en_US`. Makes the resolved value honest and the cache key consistent.
 */
it('normalizes bare en to en_US and en-US to en_US', function () {
    $coach = new CoachAgent;
    $resolveLocale = new ReflectionMethod($coach, 'resolveLocale');
    $resolveLocale->setAccessible(true);

    $this->user->update(['locale' => 'en']);
    expect($resolveLocale->invoke($coach))->toBe('en_US');

    $this->user->update(['locale' => 'en-US']);
    expect($resolveLocale->invoke($coach))->toBe('en_US');

    $this->user->update(['locale' => 'pt-BR']);
    expect($resolveLocale->invoke($coach))->toBe('pt_BR');
});
