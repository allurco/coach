<?php

return [
    'resource' => [
        'navigation_label' => 'Usuários',
        'model_label' => 'usuário',
        'plural_model_label' => 'usuários',
    ],

    'menu' => [
        'invite' => 'Convidar usuário',
        'manage' => 'Usuários',
    ],

    'form' => [
        'name' => 'Nome',
        'email' => 'E-mail',
        'locale' => 'Idioma',
        'locale_help' => 'Em qual idioma o coach se comunica com essa pessoa (email de convite e UI).',
        'is_admin' => 'Admin',
        'is_admin_help' => 'Admins podem convidar e gerenciar outros usuários.',
    ],

    'table' => [
        'name' => 'Nome',
        'email' => 'E-mail',
        'is_admin' => 'Admin',
        'status' => 'Status',
        'created_at' => 'Criado',
        'email_copied' => 'Email copiado',
        'status_invited' => 'Convidado',
        'status_active_invited' => 'Ativo (convidado)',
        'status_active' => 'Ativo',
        'resend_invite' => 'Reenviar convite',
    ],

    'notifications' => [
        'invite_sent_title' => 'Convite enviado',
        'invite_sent_body' => 'Email com link pra definir senha enviado pra :email.',
        'invite_resent_title' => 'Convite reenviado',
        'invite_resent_body' => 'Email enviado pra :email.',
    ],
];
