<?php

return [
    'mail' => [
        'subject' => 'Você foi convidado pro Coach.',
        'heading' => 'Bem-vindo ao Coach.',
        'invited_by' => '**:inviter** te convidou pra usar o Coach. — um coach de accountability com IA, self-hosted, que te ajuda a perseguir metas reais sem perder o fio.',
        'invited_anon' => 'Você foi convidado pra usar o Coach. — um coach de accountability com IA, self-hosted, que te ajuda a perseguir metas reais sem perder o fio.',
        'what_it_is' => 'Funciona pra dinheiro, saúde, fitness, aprendizado, projetos — qualquer área onde a peça que falta é alguém que não cansa de perguntar *"você fez isso hoje mesmo?"*. Cada área de foco vira um workspace seu. As decisões viram ações com prazo. O coach lembra do que vocês conversaram.',
        'privacy' => 'Sua conta é isolada — só você (e o admin que te convidou) tem acesso aos seus dados.',
        'cta_intro' => 'Pra começar, define sua senha clicando no botão abaixo:',
        'cta_button' => 'Definir senha e entrar',
        'tutorial_intro' => 'Como funciona em 3 passos:',
        'tutorial_steps' => [
            '**1. Define sua senha** no botão acima e entra na sua conta.',
            '**2. Conta pro coach** o que você quer trabalhar — finanças, saúde, fitness, aprendizado, ou outra coisa. Ele te entrevista e monta um plano contigo.',
            '**3. Recebe um email** todo dia útil de manhã com o foco do dia. Pode responder pelo celular — a resposta volta pra mesma conversa e atualiza seu plano.',
        ],
        'expiry' => 'Esse link é válido por 7 dias e só funciona uma vez.',
        'ignore' => 'Se você não esperava esse convite, ignora esse email.',
        'sign_off' => 'Até daqui a pouco,',
    ],

    'error' => [
        'title' => 'Convite — Coach.',
        'cta' => 'Ir pra tela de login',
        'used' => [
            'title' => 'Esse convite já foi usado',
            'body' => 'Você já definiu sua senha por aqui. É só fazer login normalmente com seu email.',
        ],
        'expired' => [
            'title' => 'Convite expirado',
            'body' => 'Esse convite tinha validade de 7 dias. Pede pro admin que te convidou enviar um novo.',
        ],
        'not_found' => [
            'title' => 'Convite inválido',
            'body' => 'Esse link de convite não existe ou foi gerado com um token diferente. Confere o email mais recente que você recebeu.',
        ],
    ],

    'page' => [
        'title' => 'Definir senha — Coach.',
        'greeting' => 'Oi :name, define uma senha pra entrar e a gente começa.',
        'email_label' => 'E-mail',
        'password_label' => 'Nova senha',
        'password_hint' => 'Mínimo 8 caracteres.',
        'password_confirmation_label' => 'Confirmar senha',
        'submit' => 'Definir senha e entrar',
    ],
];
