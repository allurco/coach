<?php

use App\Models\User;
use App\Services\CoachReplyProcessor;

beforeEach(function () {
    config(['coach.webhook_secret' => 'test-secret-123']);

    $this->user = User::factory()->create(['email' => 'admin@example.com']);
});

it('rejects requests without the secret header', function () {
    $response = $this->postJson('/webhooks/coach-email', [
        'from' => 'admin@example.com',
        'text' => 'oi',
    ]);

    $response->assertStatus(401);
});

it('rejects requests with the wrong secret', function () {
    $response = $this->postJson('/webhooks/coach-email', [
        'from' => 'admin@example.com',
        'text' => 'oi',
    ], ['X-Coach-Secret' => 'wrong']);

    $response->assertStatus(401);
});

it('rejects payloads without a from field', function () {
    $response = $this->postJson('/webhooks/coach-email', [
        'text' => 'oi',
    ], ['X-Coach-Secret' => 'test-secret-123']);

    $response->assertStatus(422);
});

it('returns 404 when sender email is not a known user', function () {
    $response = $this->postJson('/webhooks/coach-email', [
        'from' => 'someone-else@example.com',
        'text' => 'oi',
    ], ['X-Coach-Secret' => 'test-secret-123']);

    $response->assertStatus(404);
});

it('ignores empty replies after parsing quoted text', function () {
    // The processor must NOT be called when the reply is empty after parsing.
    $this->mock(CoachReplyProcessor::class, function ($mock) {
        $mock->shouldNotReceive('process');
    });

    // Body where every line is quoted — parser strips them all and reply ends up empty.
    $response = $this->postJson('/webhooks/coach-email', [
        'from' => 'admin@example.com',
        'text' => "> linha citada antiga\n> outra linha citada",
    ], ['X-Coach-Secret' => 'test-secret-123']);

    $response->assertStatus(200)
        ->assertJson(['ok' => true, 'note' => 'empty reply, ignored']);
});

it('passes through to the processor on a valid reply', function () {
    $this->mock(CoachReplyProcessor::class, function ($mock) {
        $mock->shouldReceive('process')
            ->once()
            ->with(
                Mockery::on(fn ($u) => $u->email === 'admin@example.com'),
                Mockery::on(fn ($r) => str_contains($r, 'já paguei')),
                null,
                'Re: ☀️ Foco do dia',
            )
            ->andReturn([
                'conversation_id' => 'fake-conv-id',
                'response' => 'Beleza, marquei como concluído.',
            ]);
    });

    $response = $this->postJson('/webhooks/coach-email', [
        'from' => 'admin@example.com',
        'subject' => 'Re: ☀️ Foco do dia',
        'text' => 'já paguei a fatura, marca como concluído',
    ], ['X-Coach-Secret' => 'test-secret-123']);

    $response->assertStatus(200)
        ->assertJsonPath('ok', true)
        ->assertJsonPath('conversation_id', 'fake-conv-id');
});

it('allows requests when no secret is configured', function () {
    config(['coach.webhook_secret' => null]);

    $this->mock(CoachReplyProcessor::class, function ($mock) {
        $mock->shouldReceive('process')->andReturn([
            'conversation_id' => 'x',
            'response' => 'ok',
        ]);
    });

    $response = $this->postJson('/webhooks/coach-email', [
        'from' => 'admin@example.com',
        'text' => 'oi',
    ]);

    $response->assertStatus(200);
});
