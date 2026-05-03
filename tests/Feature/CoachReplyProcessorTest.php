<?php

use App\Models\User;
use App\Services\CoachReplyProcessor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function callProtected(object $obj, string $method, array $args = []): mixed
{
    $reflection = new ReflectionClass($obj);
    $m = $reflection->getMethod($method);
    $m->setAccessible(true);

    return $m->invokeArgs($obj, $args);
}

beforeEach(function () {
    $this->user = User::factory()->create(['email' => 'admin@example.com']);
    $this->processor = new CoachReplyProcessor;
});

function seedConversation(int $userId, string $title): string
{
    $id = (string) Str::uuid();
    DB::table('agent_conversations')->insert([
        'id' => $id,
        'user_id' => $userId,
        'title' => $title,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

it('returns null when subject is empty', function () {
    expect(callProtected($this->processor, 'guessConversationFromSubject', [$this->user, null]))
        ->toBeNull();
});

it('strips Re:, Res:, Fw:, Fwd: prefixes before matching', function () {
    $convId = seedConversation($this->user->id, 'Foco do dia 02/05');

    $matched = callProtected($this->processor, 'guessConversationFromSubject', [
        $this->user, 'Re: Foco do dia 02/05',
    ]);

    expect($matched)->toBe($convId);
});

it('strips emoji prefix from coach pings', function () {
    $convId = seedConversation($this->user->id, 'Foco do dia 02/05');

    $matched = callProtected($this->processor, 'guessConversationFromSubject', [
        $this->user, '☀️ Foco do dia 02/05',
    ]);

    expect($matched)->toBe($convId);
});

it('strips emoji and Re: combined', function () {
    $convId = seedConversation($this->user->id, 'Foco do dia 02/05');

    $matched = callProtected($this->processor, 'guessConversationFromSubject', [
        $this->user, 'Re: ☀️ Foco do dia 02/05',
    ]);

    expect($matched)->toBe($convId);
});

it('returns null when nothing matches', function () {
    seedConversation($this->user->id, 'Algo diferente');

    $matched = callProtected($this->processor, 'guessConversationFromSubject', [
        $this->user, 'Foco do dia',
    ]);

    expect($matched)->toBeNull();
});

it('only matches conversations of the authenticated user', function () {
    $other = User::factory()->create(['email' => 'outro@x.com']);
    seedConversation($other->id, 'Foco do dia 02/05');

    $matched = callProtected($this->processor, 'guessConversationFromSubject', [
        $this->user, 'Re: Foco do dia 02/05',
    ]);

    expect($matched)->toBeNull();
});

it('returns the most recently updated when multiple match', function () {
    $older = seedConversation($this->user->id, 'Foco do dia 02/05');
    DB::table('agent_conversations')->where('id', $older)->update([
        'updated_at' => now()->subDays(2),
    ]);
    $newer = seedConversation($this->user->id, 'Foco do dia 03/05');

    $matched = callProtected($this->processor, 'guessConversationFromSubject', [
        $this->user, 'Foco do dia',
    ]);

    expect($matched)->toBe($newer);
});
