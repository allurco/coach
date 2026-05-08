<?php

return [
    'resource' => [
        'navigation_label' => 'Users',
        'model_label' => 'user',
        'plural_model_label' => 'users',
    ],

    'menu' => [
        'invite' => 'Invite user',
        'manage' => 'Users',
    ],

    'form' => [
        'name' => 'Name',
        'email' => 'Email',
        'locale' => 'Language',
        'locale_help' => 'The language the coach uses with this person (invitation email and UI).',
        'is_admin' => 'Admin',
        'is_admin_help' => 'Admins can invite and manage other users.',
    ],

    'table' => [
        'name' => 'Name',
        'email' => 'Email',
        'is_admin' => 'Admin',
        'status' => 'Status',
        'created_at' => 'Created',
        'email_copied' => 'Email copied',
        'status_invited' => 'Invited',
        'status_active_invited' => 'Active (was invited)',
        'status_active' => 'Active',
        'resend_invite' => 'Resend invite',
    ],

    'notifications' => [
        'invite_sent_title' => 'Invitation sent',
        'invite_sent_body' => 'Email with set-password link sent to :email.',
        'invite_resent_title' => 'Invitation resent',
        'invite_resent_body' => 'Email sent to :email.',
    ],
];
