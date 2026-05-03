<?php

return [
    'nav' => [
        'coach' => 'Coach',
    ],

    'greeting_first' => 'Opa! Pra que você tá buscando um coach hoje?',
    'greeting_second' => 'Como posso te ajudar a ser a melhor versão de você?',

    'suggestions' => [
        ['label' => 'preciso de clareza', 'prompt' => 'Tô meio perdido(a) e preciso de clareza sobre o que tá acontecendo na minha vida.'],
        ['label' => 'quero virar a chave', 'prompt' => 'Quero mudar algo importante na minha vida e não sei por onde começar.'],
        ['label' => 'tenho um objetivo', 'prompt' => 'Tenho um objetivo grande pra alcançar e quero traçar um plano.'],
        ['label' => 'tô estagnado', 'prompt' => 'Tô estagnado(a) há um tempo e quero destravar.'],
    ],

    'suggestions_active' => [
        ['label' => 'qual minha situação?', 'prompt' => 'Qual minha situação hoje? Resume o que tá rolando no meu plano.'],
        ['label' => 'tô atrasado em algo?', 'prompt' => 'Tem alguma ação atrasada ou perto de vencer? Foco no que mais importa.'],
        ['label' => 'o que é mais urgente?', 'prompt' => 'Qual a coisa mais urgente que preciso atacar agora?'],
        ['label' => 'como tá o progresso?', 'prompt' => 'Faz um recap rápido: o que avancei, o que travou, o que vem.'],
    ],

    'composer' => [
        'placeholder' => 'fala aí',
    ],

    'conversations' => [
        'untitled' => 'sem título',
    ],

    'plan' => [
        'filters' => [
            'pendente' => 'Pendente',
            'em_andamento' => 'Em andamento',
            'concluido' => 'Concluído',
            'todas' => 'Todas',
        ],
        'empty' => 'Nenhuma ação :status.',
        'view_all' => 'Ver todas →',
        'mark_done' => 'Concluir',
        'snooze' => 'Adiar',
        'snooze_options' => [
            'tomorrow' => 'Amanhã',
            '3days' => '3 dias',
            'week' => 'Próx. semana',
            'month' => '1 mês',
        ],
    ],

    'complete_modal' => [
        'title' => 'Concluir ação',
        'label' => 'Como você concluiu?',
        'optional' => '(opcional)',
        'placeholder' => 'Ex: Paguei via Pix com a reserva. Quitou o saldo todo.',
        'cancel' => 'Cancelar',
        'confirm' => 'Concluir',
    ],

    'tool_labels' => [
        'ListActions' => 'consultando plano',
        'CreateAction' => 'criando ação',
        'UpdateAction' => 'atualizando ação',
        'RememberFact' => 'salvando na memória',
        'RecallFacts' => 'consultando memória',
    ],

    'errors' => [
        'no_text_returned' => '_(o coach processou mas não retornou texto — pergunta de novo)_',
        'prefix' => 'Erro: ',
    ],

    'recap' => [
        'done' => 'Pronto.',
        'with_results' => 'Pronto — :parts. Olha o plano no flyout pra conferir.',
        'created_one' => 'criei 1 ação',
        'created_many' => 'criei :count ações',
        'updated_one' => 'atualizei 1 ação',
        'updated_many' => 'atualizei :count ações',
        'remembered_one' => 'salvei 1 fato',
        'remembered_many' => 'salvei :count fatos',
    ],

    'attachments' => [
        'analyze_default' => 'Analisa o(s) arquivo(s) anexado(s).',
        'sent_indicator' => '(anexo enviado)',
    ],
];
