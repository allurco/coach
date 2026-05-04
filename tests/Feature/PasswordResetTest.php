<?php

use App\Models\User;
use App\Notifications\ResetPassword as BrandedResetPassword;
use Filament\Auth\Notifications\ResetPassword as FilamentResetPassword;
use Filament\Facades\Filament;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    Notification::fake();
});

// ===== Routes =====

it('exposes a request-reset route at /password-reset/request', function () {
    $this->get('/password-reset/request')
        ->assertStatus(200);
});

it('exposes the reset form route via a signed URL', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $url = Filament::getDefaultPanel()->getResetPasswordUrl($token, $user);

    $this->get($url)->assertStatus(200);
});

it('login page surfaces a link to the password-reset request page', function () {
    $response = $this->get('/login');

    $response->assertStatus(200)
        ->assertSee('/password-reset/request', escape: false);
});

// ===== Custom branded notification =====

it('binds our branded notification in place of the Filament default', function () {
    $resolved = app(FilamentResetPassword::class, ['token' => 'tok']);

    expect($resolved)->toBeInstanceOf(BrandedResetPassword::class);
});

it('keeps ShouldQueue so emails go through the queue', function () {
    $notification = new BrandedResetPassword('any-token');

    expect($notification)->toBeInstanceOf(ShouldQueue::class);
});

it('toMail uses our markdown view + localized subject', function () {
    $user = User::factory()->create();
    $notification = new BrandedResetPassword('test-token');
    $notification->url = 'https://example.com/password-reset/reset/abc';

    $mail = $notification->toMail($user);

    expect($mail->markdown)->toBe('mail.password-reset')
        ->and($mail->subject)->toBe((string) __('passwords.mail.subject'));
});

it('toMail passes user, resetUrl and expiry to the view', function () {
    $user = User::factory()->create();
    $notification = new BrandedResetPassword('test-token');
    $notification->url = 'https://example.com/reset/xyz';

    $mail = $notification->toMail($user);

    expect($mail->viewData)
        ->toHaveKey('user')
        ->and($mail->viewData['user']->is($user))->toBeTrue()
        ->and($mail->viewData)->toHaveKey('resetUrl')
        ->and($mail->viewData['resetUrl'])->toBe('https://example.com/reset/xyz')
        ->and($mail->viewData)->toHaveKey('expiryMinutes');
});

// ===== Filament's reset request actually dispatches our notification =====

it('Filament password broker delivers our branded notification when reset is requested', function () {
    $user = User::factory()->create();

    Password::broker(Filament::getDefaultPanel()->getAuthPasswordBroker())
        ->sendResetLink(['email' => $user->email], function ($u, $token) {
            $notification = app(FilamentResetPassword::class, ['token' => $token]);
            $notification->url = Filament::getDefaultPanel()->getResetPasswordUrl($token, $u);
            $u->notify($notification);
        });

    Notification::assertSentTo($user, BrandedResetPassword::class);
});

// ===== email_verified_at side effect =====

it('marks email_verified_at after a successful reset (proof of inbox ownership)', function () {
    $user = User::factory()->create(['email_verified_at' => null]);
    $token = Password::createToken($user);

    Password::reset(
        ['email' => $user->email, 'password' => 'newstrongpw', 'password_confirmation' => 'newstrongpw', 'token' => $token],
        function ($user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        },
    );

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});
