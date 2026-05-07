<?php

use App\Filament\Pages\Coach;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('refuses to enqueue another prompt while one is already in flight', function () {
    $page = new Coach;
    $page->form->fill(['message' => 'first message', 'attachments' => []]);

    // Simulate: stream is already running (set by an earlier send() call).
    $page->thinking = true;
    $page->pendingPrompt = 'first message';

    $page->send();

    // The earlier prompt is still pending; the second send() should
    // not have replaced it or appended a duplicate user message.
    expect($page->pendingPrompt)->toBe('first message')
        ->and($page->messages)->toBeEmpty();
});
