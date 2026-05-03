<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Notification email
    |--------------------------------------------------------------------------
    |
    | Where the coach sends scheduled pings (morning, weekly, stuck checks).
    | Defaults to the first user's email if not set.
    |
    */
    'notification_email' => env('COACH_NOTIFICATION_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Inbound webhook secret
    |--------------------------------------------------------------------------
    |
    | Shared secret for verifying inbound email webhook requests.
    | Configure your email provider (Resend, Mailgun, Postmark, etc.) to
    | send the X-Coach-Secret header with this value.
    |
    | Set to null to disable auth (NOT recommended in production).
    |
    */
    'webhook_secret' => env('COACH_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Initial admin user (used by the database seeder)
    |--------------------------------------------------------------------------
    */
    'seeder' => [
        'admin_email' => env('SEEDER_ADMIN_EMAIL'),
        'admin_name' => env('SEEDER_ADMIN_NAME', 'Admin'),
    ],
];
