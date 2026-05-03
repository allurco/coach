<?php

use App\Mail\CoachPing;
use App\Models\User;
use App\Services\CoachReplyProcessor;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config([
        'coach.webhook_secret' => 'test-secret-123',
        'coach.reply_domain' => 'coach.allur.co',
    ]);
    $this->user = User::factory()->create(['email' => 'admin@example.com']);
});

it('CoachPing without conversationId has no Reply-To', function () {
    $mail = new CoachPing(kind: 'morning', body: 'hi', heading: 'Foco');
    $envelope = $mail->envelope();

    expect($envelope->replyTo)->toBeEmpty();
});

it('CoachPing with conversationId encodes it into Reply-To', function () {
    $convId = '019dee27-cbd7-7196-9ec8-0ddb4f585bec';
    $mail = new CoachPing(
        kind: 'morning',
        body: 'hi',
        heading: 'Foco',
        conversationId: $convId,
    );

    $envelope = $mail->envelope();

    expect($envelope->replyTo)->toHaveCount(1)
        ->and($envelope->replyTo[0]->address)->toBe("reply+{$convId}@coach.allur.co")
        ->and($envelope->replyTo[0]->name)->toBe('Coach');
});

it('webhook routes by To-address subaddressing into the same conversation', function () {
    $convId = '019dee27-cbd7-7196-9ec8-0ddb4f585bec';

    $this->mock(CoachReplyProcessor::class, function ($mock) use ($convId) {
        $mock->shouldReceive('process')
            ->once()
            ->withArgs(function ($user, $reply, $passedConvId, $subject) use ($convId) {
                return $passedConvId === $convId;
            })
            ->andReturn([
                'conversation_id' => $convId,
                'response' => 'ok',
            ]);
    });

    $this->postJson('/webhooks/coach-email', [
        'from' => 'admin@example.com',
        'to' => "reply+{$convId}@coach.allur.co",
        'subject' => 'Re: ☀️ Foco',
        'text' => 'já paguei',
    ], ['X-Coach-Secret' => 'test-secret-123'])
        ->assertStatus(200);
});

it('webhook accepts To with name format like "Coach <reply+...@domain>"', function () {
    $convId = '019dee27-cbd7-7196-9ec8-0ddb4f585bec';

    $this->mock(CoachReplyProcessor::class, function ($mock) use ($convId) {
        $mock->shouldReceive('process')
            ->once()
            ->withArgs(function ($user, $reply, $passedConvId, $subject) use ($convId) {
                return $passedConvId === $convId;
            })
            ->andReturn(['conversation_id' => $convId, 'response' => 'ok']);
    });

    $this->postJson('/webhooks/coach-email', [
        'from' => 'admin@example.com',
        'to' => "Coach <reply+{$convId}@coach.allur.co>",
        'text' => 'oi',
    ], ['X-Coach-Secret' => 'test-secret-123'])
        ->assertStatus(200);
});

it('webhook explicit conversation_id field overrides the To address', function () {
    $explicit = '019dee99-cbd7-7196-9ec8-0ddb4f585bec';
    $fromTo = '019dee27-cbd7-7196-9ec8-0ddb4f585bec';

    $this->mock(CoachReplyProcessor::class, function ($mock) use ($explicit) {
        $mock->shouldReceive('process')
            ->once()
            ->withArgs(function ($user, $reply, $passedConvId, $subject) use ($explicit) {
                return $passedConvId === $explicit;
            })
            ->andReturn(['conversation_id' => $explicit, 'response' => 'ok']);
    });

    $this->postJson('/webhooks/coach-email', [
        'from' => 'admin@example.com',
        'to' => "reply+{$fromTo}@coach.allur.co",
        'conversation_id' => $explicit,
        'text' => 'oi',
    ], ['X-Coach-Secret' => 'test-secret-123'])
        ->assertStatus(200);
});

it('webhook falls back to subject matching when no To subaddressing and no explicit id', function () {
    $this->mock(CoachReplyProcessor::class, function ($mock) {
        $mock->shouldReceive('process')
            ->once()
            ->withArgs(function ($user, $reply, $passedConvId, $subject) {
                return $passedConvId === null && $subject === 'Re: ☀️ Foco do dia';
            })
            ->andReturn(['conversation_id' => 'whatever', 'response' => 'ok']);
    });

    $this->postJson('/webhooks/coach-email', [
        'from' => 'admin@example.com',
        'to' => 'coach@coach.allur.co',
        'subject' => 'Re: ☀️ Foco do dia',
        'text' => 'oi',
    ], ['X-Coach-Secret' => 'test-secret-123'])
        ->assertStatus(200);
});

it('CoachPing actually carries Reply-To when sent', function () {
    Mail::fake();

    $convId = '019dee27-cbd7-7196-9ec8-0ddb4f585bec';

    Mail::to($this->user->email)->send(new CoachPing(
        kind: 'morning',
        body: 'hi',
        heading: 'Foco',
        conversationId: $convId,
    ));

    Mail::assertSent(CoachPing::class, function (CoachPing $m) use ($convId) {
        $envelope = $m->envelope();

        return ! empty($envelope->replyTo)
            && $envelope->replyTo[0]->address === "reply+{$convId}@coach.allur.co";
    });
});
