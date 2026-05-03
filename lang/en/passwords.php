<?php

return [
    // Laravel password broker statuses (override defaults).
    'reset' => 'Your password has been reset.',
    'sent' => 'We have emailed your password reset link.',
    'throttled' => 'Please wait before retrying.',
    'token' => 'This password reset token is invalid.',
    'user' => 'We can\'t find a user with that email address.',

    // Email
    'mail' => [
        'subject' => 'Password reset — Coach.',
        'heading' => 'Reset your password',
        'intro' => 'Hi :name, we received a request to reset your account password. Click the button below to continue:',
        'cta' => 'Reset password',
        'expiry' => 'This link expires in :minutes minutes and works only once.',
        'ignore' => 'If you didn\'t request this, just ignore this email — nothing changes.',
        'sign_off' => 'See you soon,',
    ],
];
