<?php

use App\Agent\Filament\Pages\Coach;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

/**
 * Insert a conversation + N messages for the user/goal and return the
 * conversation id. Mirrors what laravel/ai's SDK persists during a turn —
 * just enough to drive loadLatestConversation.
 */
function seedDeferredConversation(int $userId, int $goalId, array $messages): string
{
    $convId = (string) Str::ulid();
    DB::table('agent_conversations')->insert([
        'id' => $convId,
        'user_id' => $userId,
        'goal_id' => $goalId,
        'title' => 'seeded',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach ($messages as $i => $m) {
        DB::table('agent_conversation_messages')->insert([
            'id' => (string) Str::ulid(),
            'conversation_id' => $convId,
            'user_id' => $userId,
            'agent' => 'coach',
            'role' => $m['role'],
            'content' => $m['content'],
            'content_html' => $m['content_html'] ?? null,
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '{}',
            'meta' => '{}',
            'created_at' => now()->addSeconds($i),
            'updated_at' => now()->addSeconds($i),
        ]);
    }

    return $convId;
}

it('renders the workspace shell synchronously and defers the chat history', function () {
    $goal = Goal::create(['label' => 'general', 'name' => 'Geral']);
    seedDeferredConversation($this->user->id, $goal->id, [
        ['role' => 'user', 'content' => 'oi'],
        ['role' => 'assistant', 'content' => 'olá'],
    ]);

    // After setActiveGoal the workspace shell is rendered, but the chat
    // history hasn't been loaded yet — that happens on the follow-up
    // loadLatestConversation request (queued via $wire.js).
    $comp = Livewire::test(Coach::class)->call('setActiveGoal', $goal->id);

    expect($comp->get('activeGoalId'))->toBe($goal->id)
        ->and($comp->get('messages'))->toBe([])
        ->and($comp->get('conversationId'))->toBeNull()
        ->and($comp->get('conversationLoading'))->toBeTrue();

    $comp->assertSeeHtml('coach-shell')
        ->assertSeeHtml('msg-skeleton');
});

it('loadLatestConversation hydrates the messages and clears the loading flag', function () {
    $goal = Goal::create(['label' => 'general', 'name' => 'Geral']);
    seedDeferredConversation($this->user->id, $goal->id, [
        ['role' => 'user', 'content' => 'oi'],
        ['role' => 'assistant', 'content' => 'olá'],
    ]);

    $comp = Livewire::test(Coach::class)
        ->call('setActiveGoal', $goal->id)
        ->call('loadLatestConversation');

    expect($comp->get('messages'))->toHaveCount(2)
        ->and($comp->get('messages')[0]['role'])->toBe('user')
        ->and($comp->get('messages')[1]['role'])->toBe('assistant')
        ->and($comp->get('conversationLoading'))->toBeFalse()
        ->and($comp->get('conversationId'))->not->toBeNull();
});

it('persists content_html as a write-through cache on first load', function () {
    $goal = Goal::create(['label' => 'general', 'name' => 'Geral']);
    seedDeferredConversation($this->user->id, $goal->id, [
        ['role' => 'assistant', 'content' => '**hello** world'],
    ]);

    // Row starts with null content_html (legacy / SDK-inserted shape).
    $rowBefore = DB::table('agent_conversation_messages')
        ->where('conversation_id', '!=', '')
        ->first();
    expect($rowBefore->content_html)->toBeNull();

    Livewire::test(Coach::class)
        ->call('setActiveGoal', $goal->id)
        ->call('loadLatestConversation');

    // After the load the rendered HTML has been persisted, so the next read
    // skips Str::markdown entirely.
    $rowAfter = DB::table('agent_conversation_messages')
        ->where('id', $rowBefore->id)
        ->first();
    expect($rowAfter->content_html)->not->toBeNull()
        ->and($rowAfter->content_html)->toContain('<strong>hello</strong>');
});

it('serves cached content_html without re-rendering when present', function () {
    $goal = Goal::create(['label' => 'general', 'name' => 'Geral']);
    $cached = '<p>cached <em>html</em></p>';
    seedDeferredConversation($this->user->id, $goal->id, [
        ['role' => 'assistant', 'content' => 'raw markdown that should NOT be re-rendered', 'content_html' => $cached],
    ]);

    $messages = Livewire::test(Coach::class)
        ->call('setActiveGoal', $goal->id)
        ->call('loadLatestConversation')
        ->get('messages');

    // Verbatim from the column — no Str::markdown pass.
    expect($messages[0]['content_html'])->toBe($cached);
});

it('clears the loading flag on backToGoals', function () {
    $goal = Goal::create(['label' => 'general', 'name' => 'Geral']);

    $comp = Livewire::test(Coach::class)
        ->call('setActiveGoal', $goal->id)
        ->call('backToGoals');

    expect($comp->get('activeGoalId'))->toBeNull()
        ->and($comp->get('conversationLoading'))->toBeFalse();
});
