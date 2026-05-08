<?php

return [
    'mail' => [
        'subject' => 'You\'ve been invited to Coach.',
        'heading' => 'Welcome to Coach.',
        'invited_by' => '**:inviter** invited you to use Coach. — a self-hosted AI accountability coach that helps you pursue real goals without losing the thread.',
        'invited_anon' => 'You\'ve been invited to use Coach. — a self-hosted AI accountability coach that helps you pursue real goals without losing the thread.',
        'what_it_is' => 'Works for money, health, fitness, learning, side projects — anywhere the missing piece is someone who doesn\'t get tired of asking *"did you actually do that today?"*. Each focus area becomes a workspace of yours. Decisions become actions with deadlines. The coach remembers what you\'ve talked about.',
        'privacy' => 'Your account is isolated — only you (and the admin who invited you) have access to your data.',
        'cta_intro' => 'To get started, set your password by clicking the button below:',
        'cta_button' => 'Set password and sign in',
        'tutorial_intro' => 'How it works, in 3 steps:',
        'tutorial_steps' => [
            '**1. Set your password** with the button above and sign in to your account.',
            '**2. Tell the coach** what you want to work on — money, health, fitness, learning, or anything else. It interviews you and builds a plan with you.',
            '**3. Get an email** every weekday morning with the day\'s focus. Reply from your phone — the reply threads back into the same conversation and updates your plan.',
        ],
        'expiry' => 'This link is valid for 7 days and works only once.',
        'ignore' => 'If you weren\'t expecting this invitation, just ignore this email.',
        'sign_off' => 'See you soon,',
    ],

    'error' => [
        'title' => 'Invitation — Coach.',
        'cta' => 'Go to the login page',
        'used' => [
            'title' => 'This invitation was already used',
            'body' => 'You already set your password here. Just sign in normally with your email.',
        ],
        'expired' => [
            'title' => 'Invitation expired',
            'body' => 'This invitation was valid for 7 days. Ask the admin who invited you to send a new one.',
        ],
        'not_found' => [
            'title' => 'Invalid invitation',
            'body' => 'This invitation link doesn\'t exist or was generated with a different token. Check the most recent email you received.',
        ],
    ],

    'page' => [
        'title' => 'Set password — Coach.',
        'greeting' => 'Hi :name, set a password to sign in and we\'ll get started.',
        'email_label' => 'Email',
        'password_label' => 'New password',
        'password_hint' => 'Minimum 8 characters.',
        'password_confirmation_label' => 'Confirm password',
        'submit' => 'Set password and sign in',
    ],
];
