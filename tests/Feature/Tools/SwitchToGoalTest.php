<?php

use App\Ai\Tools\SwitchToGoal;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function seedConv(int $userId, ?int $goalId = null): string
{
    $id = (string) Str::uuid();
    DB::table('agent_conversations')->insert([
        'id' => $id,
        'user_id' => $userId,
        'goal_id' => $goalId,
        'title' => 'Test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

it('moves the current conversation to the target goal', function () {
    $finance = Goal::create(['label' => 'finance', 'name' => 'Vida financeira']);
    $health = Goal::create(['label' => 'health', 'name' => 'Saúde']);
    $convId = seedConv($this->user->id, $finance->id);

    $tool = new SwitchToGoal($convId);
    $result = $tool->handle(new Request(['goal_id' => $health->id]));

    $newGoalId = DB::table('agent_conversations')->where('id', $convId)->value('goal_id');
    expect($newGoalId)->toBe($health->id)
        ->and((string) $result)->toMatch('/Saúde|moved|movida/iu');
});

it('refuses when the target goal does not exist', function () {
    $finance = Goal::create(['label' => 'finance', 'name' => 'Vida financeira']);
    $convId = seedConv($this->user->id, $finance->id);

    $tool = new SwitchToGoal($convId);
    $result = $tool->handle(new Request(['goal_id' => 99999]));

    expect((string) $result)->toMatch('/erro|n[ãa]o encontr|not found|error/iu')
        ->and(DB::table('agent_conversations')->where('id', $convId)->value('goal_id'))
        ->toBe($finance->id);
});

it('refuses when the target goal is archived', function () {
    $active = Goal::create(['label' => 'finance', 'name' => 'Active']);
    $archived = Goal::create(['label' => 'fitness', 'name' => 'Archived']);
    $archived->update(['is_archived' => true]);
    $convId = seedConv($this->user->id, $active->id);

    $tool = new SwitchToGoal($convId);
    $result = $tool->handle(new Request(['goal_id' => $archived->id]));

    expect((string) $result)->toMatch('/arquivad|archived/iu')
        ->and(DB::table('agent_conversations')->where('id', $convId)->value('goal_id'))
        ->toBe($active->id);
});

it('reports a no-op cleanly when conversation is already in the target goal', function () {
    $finance = Goal::create(['label' => 'finance', 'name' => 'Vida financeira']);
    $convId = seedConv($this->user->id, $finance->id);

    $tool = new SwitchToGoal($convId);
    $result = $tool->handle(new Request(['goal_id' => $finance->id]));

    expect((string) $result)->toMatch('/já|already/iu');
});

it('refuses when there is no current conversation context', function () {
    $finance = Goal::create(['label' => 'finance', 'name' => 'Vida financeira']);

    $tool = new SwitchToGoal(null);
    $result = $tool->handle(new Request(['goal_id' => $finance->id]));

    expect((string) $result)->toMatch('/erro|conversa|conversation|error/iu');
});

it('does not allow switching the conversation to another users goal', function () {
    $myGoal = Goal::create(['label' => 'finance', 'name' => 'Mine']);
    $other = User::factory()->create();
    $otherGoal = Goal::withoutGlobalScope('owner')->create([
        'user_id' => $other->id,
        'label' => 'fitness',
        'name' => 'Theirs',
    ]);
    $convId = seedConv($this->user->id, $myGoal->id);

    $tool = new SwitchToGoal($convId);
    $result = $tool->handle(new Request(['goal_id' => $otherGoal->id]));

    expect((string) $result)->toMatch('/erro|n[ãa]o encontr|not found|error/iu')
        ->and(DB::table('agent_conversations')->where('id', $convId)->value('goal_id'))
        ->toBe($myGoal->id);
});
