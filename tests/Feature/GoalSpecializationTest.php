<?php

use App\Ai\Agents\FinanceCoach;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

function setActive(Goal $goal): FinanceCoach
{
    return (new FinanceCoach)->forGoal($goal->id);
}

it('returns the empty message when the user has no goals at all', function () {
    Goal::query()->where('user_id', $this->user->id)->delete();

    $context = goalContextOf(new FinanceCoach);

    expect($context)->toBe((string) __('coach.goal_context.empty'))
        ->not->toContain('FINANCE:')
        ->not->toContain('LEGAL:');
});

it('uses the active goal explicitly set via forGoal()', function () {
    $goal = Goal::create(['label' => 'finance', 'name' => 'Sair do vermelho']);

    $context = goalContextOf(setActive($goal));

    expect($context)
        ->toContain('finance')
        ->toContain('Sair do vermelho');
});

it('falls back to the user’s default goal when no goal is explicitly set', function () {
    Goal::query()->where('user_id', $this->user->id)->delete();
    $goal = Goal::create(['label' => 'fitness', 'name' => 'Voltar a treinar']);

    $context = goalContextOf(new FinanceCoach);

    expect($context)
        ->toContain('fitness')
        ->toContain('Voltar a treinar');
});

it('detects legal goal and includes a disclaimer about specific advice', function () {
    $goal = Goal::create(['label' => 'legal', 'name' => 'Regularizar PJ']);

    $context = goalContextOf(setActive($goal));

    expect($context)->toContain('legal')
        ->and(preg_match('/advogado|lawyer/iu', $context))->toBe(1);
});

it('detects emotional goal and uses empathetic specialization', function () {
    $goal = Goal::create(['label' => 'emotional', 'name' => 'Lidar com burnout']);

    $context = goalContextOf(setActive($goal));

    expect($context)->toContain('emotional')
        ->and(preg_match('/empatia|empathy|validar|validate/iu', $context))->toBe(1);
});

it('detects health goal and refers to medical professionals', function () {
    $goal = Goal::create(['label' => 'health', 'name' => 'Lidar com sintomas']);

    $context = goalContextOf(setActive($goal));

    expect($context)->toContain('health')
        ->and(preg_match('/médico|doctor|profissional/iu', $context))->toBe(1);
});

it('detects fitness goal and refers to a trainer', function () {
    $goal = Goal::create(['label' => 'fitness', 'name' => 'Voltar a treinar']);

    $context = goalContextOf(setActive($goal));

    expect($context)->toContain('fitness')
        ->and(preg_match('/treino|training|fisioterapeuta|trainer/iu', $context))->toBe(1);
});

it('detects learning goal', function () {
    $goal = Goal::create(['label' => 'learning', 'name' => 'Aprender alemão']);

    expect(goalContextOf(setActive($goal)))
        ->toContain('learning')
        ->toContain('Aprender alemão');
});

it('treats the general label as no specialization (neutral message)', function () {
    $goal = Goal::create(['label' => 'general', 'name' => 'Geral']);

    $context = goalContextOf(setActive($goal));

    expect($context)->toBe((string) __('coach.goal_context.empty'))
        ->not->toContain('FINANCE:')
        ->not->toContain('LEGAL:');
});

it('shows custom goal name without injecting a specialization for unknown labels', function () {
    $goal = Goal::create(['label' => 'cooking', 'name' => 'Aprender a fazer pão']);

    $context = goalContextOf(setActive($goal));

    expect($context)->toContain('Aprender a fazer pão')
        ->not->toContain('FINANCE:')
        ->not->toContain('EMOTIONAL:');
});

it('ignores archived goals when falling back to the user default', function () {
    Goal::query()->where('user_id', $this->user->id)->delete();
    $active = Goal::create(['label' => 'finance', 'name' => 'Active']);
    $archived = Goal::create(['label' => 'fitness', 'name' => 'Archived']);
    $archived->update(['is_archived' => true]);

    $context = goalContextOf(new FinanceCoach);

    expect($context)
        ->toContain('Active')
        ->not->toContain('Archived');
});

it('does not include other users goals (multi-tenant isolation)', function () {
    Goal::query()->where('user_id', $this->user->id)->delete();
    $other = User::factory()->create();
    Goal::withoutGlobalScope('owner')->create([
        'user_id' => $other->id,
        'label' => 'finance',
        'name' => 'OTHER user goal',
    ]);

    expect(goalContextOf(new FinanceCoach))
        ->toBe((string) __('coach.goal_context.empty'))
        ->not->toContain('OTHER user');
});

it('auto-resolves the active goal from a continued conversation’s goal_id', function () {
    Goal::query()->where('user_id', $this->user->id)->delete();
    $finance = Goal::create(['label' => 'finance', 'name' => 'Sair do vermelho']);
    $other = Goal::create(['label' => 'fitness', 'name' => 'Voltar a treinar']);

    $convId = (string) Str::uuid();
    DB::table('agent_conversations')->insert([
        'id' => $convId,
        'user_id' => $this->user->id,
        'goal_id' => $finance->id,
        'title' => 'Past finance chat',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $coach = (new FinanceCoach)->continue($convId, as: $this->user);

    $context = goalContextOf($coach);

    expect($context)
        ->toContain('finance')
        ->toContain('Sair do vermelho')
        ->not->toContain('Voltar a treinar');
});

it('does not allow forGoal() to load another users goal (global scope blocks it)', function () {
    Goal::query()->where('user_id', $this->user->id)->delete();
    $other = User::factory()->create();
    $otherGoal = Goal::withoutGlobalScope('owner')->create([
        'user_id' => $other->id,
        'label' => 'finance',
        'name' => 'OTHER user goal',
    ]);

    $coach = (new FinanceCoach)->forGoal($otherGoal->id);

    expect(goalContextOf($coach))
        ->toBe((string) __('coach.goal_context.empty'))
        ->not->toContain('OTHER user');
});
