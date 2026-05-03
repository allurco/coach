<?php

return [
    // Laravel password broker statuses (override defaults).
    'reset' => 'Sua senha foi redefinida.',
    'sent' => 'Enviamos um link de redefinição de senha pra você.',
    'throttled' => 'Aguarde antes de tentar de novo.',
    'token' => 'Esse token de redefinição é inválido.',
    'user' => 'Não encontramos um usuário com esse email.',

    // Email
    'mail' => [
        'subject' => 'Redefinição de senha — Coach.',
        'heading' => 'Redefinir sua senha',
        'intro' => 'Oi :name, recebemos uma solicitação pra redefinir a senha da sua conta. Pra continuar, clica no botão abaixo:',
        'cta' => 'Redefinir senha',
        'expiry' => 'Esse link expira em :minutes minutos e só funciona uma vez.',
        'ignore' => 'Se você não fez essa solicitação, ignora esse email — nada muda.',
        'sign_off' => 'Até daqui a pouco,',
    ],
];
