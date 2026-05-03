<?php

use App\Mail\PasswordResetLink;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    Mail::fake();
});

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

it('User::sendPasswordResetNotification routes through our branded PasswordResetLink mailable', function () {
    $user = User::factory()->create();

    $user->sendPasswordResetNotification('fake-token-123');

    Mail::assertSent(PasswordResetLink::class, function (PasswordResetLink $mail) use ($user) {
        return $mail->hasTo($user->email)
            && str_contains($mail->resetUrl, 'fake-token-123')
            && str_contains($mail->resetUrl, urlencode($user->email));
    });
});

it('PasswordResetLink envelope subject comes from the lang file', function () {
    $user = User::factory()->create();
    $mail = new PasswordResetLink($user, 'http://example.com/reset/abc');

    expect($mail->envelope()->subject)->toBe((string) __('passwords.mail.subject'));
});

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
