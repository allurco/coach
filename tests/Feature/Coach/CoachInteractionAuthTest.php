<?php

use App\Agent\Services\CoachInteraction;
use App\Models\User;

/**
 * authenticateTurn is the turn's auth step. It must activate the right user
 * for the owner global scopes, but it must NOT migrate an already-active web
 * session — re-logging in regenerates the session id every message, and in a
 * standalone PWA the new cookie isn't applied to the streamed response, so the
 * next message lands on a destroyed session and bounces to login.
 */
function callAuthenticateTurn(User $user): void
{
    $method = new ReflectionMethod(CoachInteraction::class, 'authenticateTurn');
    $method->invoke(new CoachInteraction, $user);
}

it('authenticates the user when no one is logged in (stateless webhook/command path)', function () {
    $user = User::factory()->create();

    expect(auth()->check())->toBeFalse();

    callAuthenticateTurn($user);

    expect(auth()->id())->toBe($user->getAuthIdentifier());
});

it('does not regenerate the session for the already-authenticated web user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $sessionIdBefore = session()->getId();

    callAuthenticateTurn($user);

    expect(auth()->id())->toBe($user->getAuthIdentifier())
        ->and(session()->getId())->toBe($sessionIdBefore);
});

it('switches to a different user when the wrong one is authenticated', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $this->actingAs($first);

    callAuthenticateTurn($second);

    expect(auth()->id())->toBe($second->getAuthIdentifier());
});
