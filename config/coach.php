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
    | Reply domain
    |--------------------------------------------------------------------------
    |
    | Domain used to build the Reply-To address on outgoing pings, so that the
    | conversation id can be encoded as `reply+{conversationId}@{domain}`.
    | When the user replies, the inbound webhook extracts the id from the
    | To field and routes back into the same conversation.
    |
    | Falls back to parsing APP_URL host if not set.
    |
    */
    'reply_domain' => env('COACH_REPLY_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Tavily web search API key
    |--------------------------------------------------------------------------
    |
    | Powers the WebSearch tool. Sign up at https://tavily.com (free tier:
    | 1000 queries/month). Without this set the tool returns a "not configured"
    | message and the agent falls back to its training-data knowledge.
    |
    */
    'tavily_api_key' => env('TAVILY_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Gemini models
    |--------------------------------------------------------------------------
    |
    | Two slots so we can use a more reliable model for the interactive
    | Filament chat (where multi-tool turns can hallucinate or truncate
    | on flash) and a cheap/fast model for the cron pings + email reply
    | flows where responses are short and single-shot.
    |
    */
    'models' => [
        'interactive' => env('COACH_MODEL_INTERACTIVE', 'gemini-2.5-pro'),
        'background' => env('COACH_MODEL_BACKGROUND', 'gemini-2.5-flash'),
    ],

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
