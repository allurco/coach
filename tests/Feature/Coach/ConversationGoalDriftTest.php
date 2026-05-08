<?php

use App\Filament\Pages\Coach;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('drops a stale conversationId when its goal differs from activeGoalId', function () {
    $finance = Goal::create(['label' => 'finance', 'name' => 'Finance']);
    $health = Goal::create(['label' => 'health', 'name' => 'Health']);

    // Simulate a conversation that lives in the finance goal (the user
    // had it open earlier).
    $staleConvId = (string) Str::uuid();
    DB::table('agent_conversations')->insert([
        'id' => $staleConvId,
        'user_id' => $this->user->id,
        'goal_id' => $finance->id,
        'title' => 'Stale finance chat',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Simulate a race: the client believes activeGoalId is health's id
    // (sidebar click landed) but conversationId is still the finance one
    // because the click round-trip didn't finish before submit.
    $page = new Coach;
    $page->thinking = true;            // pretend send() set this already
    $page->pendingPrompt = 'oi';
    $page->activeGoalId = $health->id;
    $page->conversationId = $staleConvId;

    // We don't run the full LLM stream — just call the drift-detection
    // block via a tiny helper. Reflection avoids needing to mock laravel/ai.
    $ref = new ReflectionMethod($page, 'runAi');
    expect($ref)->toBeInstanceOf(ReflectionMethod::class);

    // The simplest assertion that proves the fix: directly invoke runAi.
    // The agent stream will throw (no API key) and the catch branch
    // takes over, but the drift check runs BEFORE the try body fails,
    // so conversationId should already be null by the time we re-read.
    try {
        $page->runAi();
    } catch (Throwable $e) {
        // Stream failure is expected in a test env without Gemini.
    }

    expect($page->conversationId)->toBeNull();
});

it('keeps the conversationId when the conversation already lives in the active goal', function () {
    $finance = Goal::create(['label' => 'finance', 'name' => 'Finance']);

    $convId = (string) Str::uuid();
    DB::table('agent_conversations')->insert([
        'id' => $convId,
        'user_id' => $this->user->id,
        'goal_id' => $finance->id,
        'title' => 'Right-goal chat',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $page = new Coach;
    $page->thinking = true;
    $page->pendingPrompt = 'oi';
    $page->activeGoalId = $finance->id;
    $page->conversationId = $convId;

    try {
        $page->runAi();
    } catch (Throwable $e) {
        // Stream failure is expected.
    }

    // The drift detector should NOT have nulled the id, because the
    // conversation's goal_id matches activeGoalId.
    expect($page->conversationId)->toBe($convId);
});
