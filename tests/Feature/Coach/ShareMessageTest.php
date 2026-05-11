<?php

use App\Filament\Pages\Coach;
use App\Mail\Share;
use App\Models\Contact;
use App\Models\User;
use App\Services\Sharer;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'me@example.com',
        'name' => 'Rogers',
    ]);
    $this->actingAs($this->user);
    Mail::fake();
    RateLimiter::clear('share-via-email:'.$this->user->id);
});

function makeCoachWithAssistantMessage(string $content = 'Resumo da sessão.'): Coach
{
    $page = new Coach;
    $page->messages = [
        ['role' => 'user', 'content' => 'olá', 'time' => '08:00', 'attachments' => []],
        ['role' => 'assistant', 'content' => $content, 'time' => '08:01'],
    ];

    return $page;
}

// openShareModal -------------------------------------------------------------

it('opens the modal pre-filled with the assistant message content', function () {
    $page = makeCoachWithAssistantMessage('Plano detalhado pra Ana.');

    $page->openShareModal(1);

    expect($page->sharingMessageIndex)->toBe(1)
        ->and($page->shareBody)->toBe('Plano detalhado pra Ana.')
        ->and($page->shareRecipient)->toBe('')
        ->and($page->shareSubject)->not->toBe('')
        ->and($page->shareError)->toBeNull();
});

it('ignores user messages — only assistant messages are shareable', function () {
    $page = makeCoachWithAssistantMessage();

    $page->openShareModal(0); // user message

    expect($page->sharingMessageIndex)->toBeNull();
});

it('ignores out-of-range indices without crashing', function () {
    $page = makeCoachWithAssistantMessage();

    $page->openShareModal(99);

    expect($page->sharingMessageIndex)->toBeNull();
});

// confirmShare — happy path --------------------------------------------------

it('sends the Share mailable when recipient is a literal email', function () {
    $page = makeCoachWithAssistantMessage('Segue o plano.');
    $page->openShareModal(1);
    $page->shareRecipient = 'ana@example.com';
    $page->shareSubject = 'Plano financeiro';

    $page->confirmShare();

    Mail::assertSent(Share::class, function (Share $mail) {
        return $mail->hasTo('ana@example.com')
            && $mail->emailSubject === 'Plano financeiro'
            && str_contains($mail->body, 'Segue o plano.')
            && $mail->senderName === 'Rogers';
    });
});

it('resolves a saved Contact label to its email', function () {
    Contact::create([
        'label' => 'contador',
        'name' => 'João',
        'email' => 'joao@example.com',
    ]);

    $page = makeCoachWithAssistantMessage();
    $page->openShareModal(1);
    $page->shareRecipient = 'contador';

    $page->confirmShare();

    Mail::assertSent(Share::class, fn (Share $mail) => $mail->hasTo('joao@example.com'));
});

it('auto-bccs the authenticated user so they keep a copy', function () {
    $page = makeCoachWithAssistantMessage();
    $page->openShareModal(1);
    $page->shareRecipient = 'ana@example.com';

    $page->confirmShare();

    Mail::assertSent(Share::class, fn (Share $mail) => $mail->hasBcc('me@example.com'));
});

it('closes the modal and clears state after a successful send', function () {
    $page = makeCoachWithAssistantMessage();
    $page->openShareModal(1);
    $page->shareRecipient = 'ana@example.com';

    $page->confirmShare();

    expect($page->sharingMessageIndex)->toBeNull()
        ->and($page->shareRecipient)->toBe('')
        ->and($page->shareBody)->toBe('')
        ->and($page->shareError)->toBeNull();
});

// confirmShare — error paths -------------------------------------------------

it('keeps the modal open and surfaces an error when recipient is invalid', function () {
    $page = makeCoachWithAssistantMessage();
    $page->openShareModal(1);
    $page->shareRecipient = 'not-an-email-nor-a-label';

    $page->confirmShare();

    Mail::assertNothingSent();
    expect($page->sharingMessageIndex)->toBe(1)
        ->and($page->shareError)->not->toBeNull();
});

it('keeps the modal open and surfaces an error when body is empty', function () {
    $page = makeCoachWithAssistantMessage();
    $page->openShareModal(1);
    $page->shareRecipient = 'ana@example.com';
    $page->shareBody = '   '; // whitespace only

    $page->confirmShare();

    Mail::assertNothingSent();
    expect($page->sharingMessageIndex)->toBe(1)
        ->and($page->shareError)->not->toBeNull();
});

it('respects the per-user hourly rate limit', function () {
    $page = makeCoachWithAssistantMessage();

    // Trip the limit by hitting it MAX_PER_HOUR times.
    for ($i = 0; $i < Sharer::MAX_PER_HOUR; $i++) {
        RateLimiter::hit('share-via-email:'.$this->user->id, 3600);
    }

    $page->openShareModal(1);
    $page->shareRecipient = 'ana@example.com';

    $page->confirmShare();

    Mail::assertNothingSent();
    expect($page->shareError)->not->toBeNull();
});

it('does not resolve another user\'s contact label', function () {
    $intruder = User::factory()->create();
    Contact::withoutGlobalScope('owner')->create([
        'user_id' => $intruder->id,
        'label' => 'contador',
        'name' => 'Outro',
        'email' => 'outro@example.com',
    ]);

    $page = makeCoachWithAssistantMessage();
    $page->openShareModal(1);
    $page->shareRecipient = 'contador';

    $page->confirmShare();

    Mail::assertNothingSent();
    expect($page->shareError)->not->toBeNull();
});

// cancelShare ----------------------------------------------------------------

it('cancelShare wipes all share state', function () {
    $page = makeCoachWithAssistantMessage();
    $page->openShareModal(1);
    $page->shareRecipient = 'ana@example.com';
    $page->shareSubject = 'X';
    $page->shareBody = 'Y';
    $page->shareError = 'something';

    $page->cancelShare();

    expect($page->sharingMessageIndex)->toBeNull()
        ->and($page->shareRecipient)->toBe('')
        ->and($page->shareSubject)->toBe('')
        ->and($page->shareBody)->toBe('')
        ->and($page->shareError)->toBeNull();
});
