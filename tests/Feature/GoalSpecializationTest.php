<?php

use App\Ai\Agents\FinanceCoach;
use App\Models\CoachMemory;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function goalContextOf(FinanceCoach $coach): string
{
    $ref = new ReflectionMethod($coach, 'goalContext');
    $ref->setAccessible(true);

    return (string) $ref->invoke($coach);
}

it('returns a neutral message when no goal is set', function () {
    $coach = new FinanceCoach;
    $context = goalContextOf($coach);

    // Whatever the active locale, the empty case should match the translation
    // key exactly and never include any specialization marker.
    expect($context)->toBe((string) __('coach.goal_context.empty'))
        ->and($context)
        ->not->toContain('FINANCE:')
        ->not->toContain('LEGAL:')
        ->not->toContain('EMOTIONAL:');
});

it('detects finance goal and injects finance specialization', function () {
    CoachMemory::create([
        'kind' => 'goal',
        'label' => 'finance',
        'summary' => 'Sair do vermelho',
        'is_active' => true,
    ]);

    expect(goalContextOf(new FinanceCoach))
        ->toContain('finance')
        ->toContain('Sair do vermelho');
});

it('detects legal goal and includes a disclaimer about specific advice', function () {
    CoachMemory::create([
        'kind' => 'goal',
        'label' => 'legal',
        'summary' => 'Regularizar PJ',
        'is_active' => true,
    ]);

    $context = goalContextOf(new FinanceCoach);

    expect($context)->toContain('legal')
        ->and(preg_match('/advogado|lawyer/iu', $context))->toBe(1);
});

it('detects emotional goal and uses empathetic specialization', function () {
    CoachMemory::create([
        'kind' => 'goal',
        'label' => 'emotional',
        'summary' => 'Lidar com burnout',
        'is_active' => true,
    ]);

    $context = goalContextOf(new FinanceCoach);

    expect($context)->toContain('emotional')
        ->and(preg_match('/empatia|empathy|validar|validate/iu', $context))->toBe(1);
});

it('detects health goal and refers to medical professionals', function () {
    CoachMemory::create([
        'kind' => 'goal',
        'label' => 'health',
        'summary' => 'Lidar com sintomas',
        'is_active' => true,
    ]);

    $context = goalContextOf(new FinanceCoach);

    expect($context)->toContain('health')
        ->and(preg_match('/médico|doctor|profissional/iu', $context))->toBe(1);
});

it('detects fitness goal and refers to a trainer', function () {
    CoachMemory::create([
        'kind' => 'goal',
        'label' => 'fitness',
        'summary' => 'Voltar a treinar',
        'is_active' => true,
    ]);

    $context = goalContextOf(new FinanceCoach);

    expect($context)->toContain('fitness')
        ->and(preg_match('/treino|training|fisioterapeuta|trainer/iu', $context))->toBe(1);
});

it('detects learning goal', function () {
    CoachMemory::create([
        'kind' => 'goal',
        'label' => 'learning',
        'summary' => 'Aprender alemão',
        'is_active' => true,
    ]);

    expect(goalContextOf(new FinanceCoach))
        ->toContain('learning')
        ->toContain('alemão');
});

it('supports multiple active goals at once', function () {
    CoachMemory::create([
        'kind' => 'goal',
        'label' => 'finance',
        'summary' => 'Sair do vermelho',
        'is_active' => true,
    ]);
    CoachMemory::create([
        'kind' => 'goal',
        'label' => 'health',
        'summary' => 'Voltar a treinar',
        'is_active' => true,
    ]);

    $context = goalContextOf(new FinanceCoach);

    expect($context)
        ->toContain('finance')
        ->toContain('health')
        ->toContain('Sair do vermelho')
        ->toContain('Voltar a treinar');
});

it('ignores inactive goals', function () {
    CoachMemory::create([
        'kind' => 'goal',
        'label' => 'finance',
        'summary' => 'Old finance goal',
        'is_active' => false,
    ]);

    expect(goalContextOf(new FinanceCoach))
        ->toBe((string) __('coach.goal_context.empty'))
        ->not->toContain('Old finance goal');
});

it('ignores unknown goal labels gracefully (still mentions them but no specialization)', function () {
    CoachMemory::create([
        'kind' => 'goal',
        'label' => 'cooking',
        'summary' => 'Aprender a fazer pão',
        'is_active' => true,
    ]);

    $context = goalContextOf(new FinanceCoach);

    // The custom goal still shows up in user-stated form...
    expect($context)->toContain('Aprender a fazer pão');
});

it('does not include other users goals (multi-tenant isolation)', function () {
    $other = User::factory()->create();
    CoachMemory::withoutGlobalScope('owner')->create([
        'user_id' => $other->id,
        'kind' => 'goal',
        'label' => 'finance',
        'summary' => 'OTHER user finance goal',
        'is_active' => true,
    ]);

    expect(goalContextOf(new FinanceCoach))
        ->toBe((string) __('coach.goal_context.empty'))
        ->not->toContain('OTHER user');
});
