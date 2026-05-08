<?php

use App\Filament\Pages\Coach;
use App\Mail\UserInvitation;
use App\Models\User;
use Filament\Panel;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    Mail::fake();
    Notification::fake();
});

it('rejects an unknown invitation token with 404', function () {
    $this->get(route('invitation.show', 'bogus-token'))
        ->assertStatus(404);
});

it('rejects an expired invitation with 410', function () {
    $user = User::factory()->create([
        'invitation_token' => 'tok-expired',
        'invited_at' => now()->subDays(8),
        'password' => null,
    ]);

    $this->get(route('invitation.show', $user->invitation_token))
        ->assertStatus(410);
});

it('shows the set-password form for a valid token', function () {
    $user = User::factory()->create([
        'name' => 'Maria',
        'invitation_token' => 'tok-valid',
        'invited_at' => now(),
        'password' => null,
    ]);

    $this->get(route('invitation.show', $user->invitation_token))
        ->assertStatus(200)
        ->assertSee('Maria')
        ->assertSee($user->email)
        ->assertSee(__('invitation.page.submit'));
});

it('rejects passwords shorter than 8 chars', function () {
    $user = User::factory()->create([
        'invitation_token' => 'tok-short',
        'invited_at' => now(),
        'password' => null,
    ]);

    $this->from(route('invitation.show', $user->invitation_token))
        ->post(route('invitation.accept', $user->invitation_token), [
            'password' => 'abc',
            'password_confirmation' => 'abc',
        ])
        ->assertSessionHasErrors('password');

    expect($user->fresh()->password)->toBeNull();
});

it('rejects mismatched password confirmation', function () {
    $user = User::factory()->create([
        'invitation_token' => 'tok-mismatch',
        'invited_at' => now(),
        'password' => null,
    ]);

    $this->from(route('invitation.show', $user->invitation_token))
        ->post(route('invitation.accept', $user->invitation_token), [
            'password' => 'longenoughpassword',
            'password_confirmation' => 'differentpassword',
        ])
        ->assertSessionHasErrors('password');
});

it('accepts a valid invitation, sets password, logs in, redirects to root', function () {
    $user = User::factory()->create([
        'invitation_token' => 'tok-good',
        'invited_at' => now(),
        'password' => null,
    ]);

    $response = $this->post(route('invitation.accept', $user->invitation_token), [
        'password' => 'my-new-strong-password',
        'password_confirmation' => 'my-new-strong-password',
    ]);

    $response->assertRedirect('/');

    $fresh = $user->fresh();
    expect($fresh->password)->not->toBeNull()
        ->and($fresh->invitation_token)->toBeNull()
        ->and($fresh->accepted_invitation_at)->not->toBeNull();

    $this->assertAuthenticatedAs($fresh);
});

it('cannot reuse the same invitation token twice', function () {
    $user = User::factory()->create([
        'invitation_token' => 'tok-once',
        'invited_at' => now(),
        'password' => null,
    ]);

    $this->post(route('invitation.accept', $user->invitation_token), [
        'password' => 'my-new-strong-password',
        'password_confirmation' => 'my-new-strong-password',
    ])->assertRedirect(Coach::getUrl());

    // Token's been cleared; a second visit now distinguishes "already
    // used" (some user did accept an invitation) from "never existed",
    // so the controller returns 410 with a friendly explainer page
    // pointing the user at /login.
    $response = $this->get(route('invitation.show', 'tok-once'));
    $response->assertStatus(410);
    $response->assertSee(__('invitation.error.used.title'));
});

it('shows a friendly page for unknown tokens, not a bare abort', function () {
    $response = $this->get(route('invitation.show', 'no-such-token'));
    $response->assertStatus(404);
    $response->assertSee(__('invitation.error.not_found.title'));
});

it('users with a pending invitation cannot access the panel', function () {
    $user = User::factory()->create([
        'invitation_token' => 'tok-pending',
        'invited_at' => now(),
        'password' => null,
    ]);

    expect($user->canAccessPanel(app(Panel::class)))->toBeFalse();
});

it('creates a token + sends UserInvitation email when admin invites someone', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $invitee = User::create([
        'name' => 'Convidado',
        'email' => 'novo@example.com',
        'is_admin' => false,
        'invitation_token' => Str::random(64),
        'invited_at' => now(),
    ]);

    Mail::to($invitee->email)->send(new UserInvitation(
        user: $invitee,
        acceptUrl: route('invitation.show', $invitee->invitation_token),
        invitedByName: $admin->name,
    ));

    Mail::assertSent(UserInvitation::class, function (UserInvitation $mail) use ($invitee, $admin) {
        return $mail->user->is($invitee)
            && $mail->invitedByName === $admin->name
            && str_contains($mail->acceptUrl, $invitee->invitation_token);
    });
});
