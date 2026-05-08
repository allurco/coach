<?php

use App\Mail\UserInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('persists the locale on user creation', function () {
    $user = User::create([
        'name' => 'Maria',
        'email' => 'maria@test.local',
        'locale' => 'en',
    ]);

    expect($user->fresh()->locale)->toBe('en');
});

it('keeps the user locale nullable for legacy rows', function () {
    $user = User::create([
        'name' => 'Sem locale',
        'email' => 'sem@test.local',
    ]);

    expect($user->fresh()->locale)->toBeNull();
});

it('renders the invitation email in the user’s locale, not APP_LOCALE', function () {
    Mail::fake();
    app()->setLocale('pt_BR');

    $user = User::create([
        'name' => 'Speaker EN',
        'email' => 'en-speaker@test.local',
        'locale' => 'en',
        'invitation_token' => 'tok-123',
    ]);

    Mail::to($user->email)
        ->locale($user->locale)
        ->send(new UserInvitation($user, 'https://coach.test/accept-invite/tok-123', 'Admin'));

    Mail::assertSent(UserInvitation::class, function ($mail) {
        $rendered = $mail->render();

        // English copy from invitation.mail.heading + cta_button.
        return str_contains($rendered, 'Welcome to Coach.')
            && str_contains($rendered, 'Set password and sign in')
            && ! str_contains($rendered, 'Bem-vindo ao Coach.');
    });
});

it('falls back to APP_LOCALE when the user has no stored locale', function () {
    Mail::fake();
    app()->setLocale('pt_BR');

    $user = User::create([
        'name' => 'No preference',
        'email' => 'no-pref@test.local',
        'invitation_token' => 'tok-456',
    ]);

    Mail::to($user->email)
        ->locale($user->locale ?? config('app.locale'))
        ->send(new UserInvitation($user, 'https://coach.test/accept-invite/tok-456', 'Admin'));

    Mail::assertSent(UserInvitation::class, function ($mail) {
        $rendered = $mail->render();

        return str_contains($rendered, 'Bem-vindo ao Coach.');
    });
});

it('switches the app locale to the user’s locale on web requests', function () {
    $user = User::factory()->create(['locale' => 'en']);
    app()->setLocale('pt_BR');

    $this->actingAs($user)->get('/login'); // any web route

    expect(app()->getLocale())->toBe('en');
});

it('leaves APP_LOCALE untouched for guests', function () {
    app()->setLocale('pt_BR');

    $this->get('/login');

    expect(app()->getLocale())->toBe('pt_BR');
});
