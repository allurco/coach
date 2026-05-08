<?php

return [
    'nav' => [
        'coach' => 'Coach',
    ],

    'greeting_first' => 'Opa, :name! Pra que você tá buscando um coach hoje?',
    'greeting_first_anon' => 'Opa! Pra que você tá buscando um coach hoje?',
    'greeting_second' => 'Você fala, eu cobro, eu lembro. Funciono pra dinheiro, saúde, fitness, aprendizado, projetos — ou só pra pensar em voz alta. Seus dados ficam só na sua conta.',

    'welcome' => [
        'how_label' => 'Como funciona',
        'concepts' => [
            ['icon' => '🎯', 'title' => 'Goals', 'body' => 'Cada área de foco vira um workspace na barra lateral. O agente se especializa pra ele.'],
            ['icon' => '📋', 'title' => 'Plano', 'body' => 'As ações concretas que a gente decide juntos — com prazo, prioridade, e cobrança.'],
            ['icon' => '🧠', 'title' => 'Memória', 'body' => 'Eu lembro fatos que você consolidou em conversas passadas, sem você reanexar nada.'],
        ],
    ],

    'suggestions' => [
        ['label' => 'preciso de clareza', 'prompt' => 'Tô meio perdido(a) e preciso de clareza sobre o que tá acontecendo na minha vida.'],
        ['label' => 'quero virar a chave', 'prompt' => 'Quero mudar algo importante na minha vida e não sei por onde começar.'],
        ['label' => 'tenho um objetivo', 'prompt' => 'Tenho um objetivo grande pra alcançar e quero traçar um plano.'],
        ['label' => 'tô estagnado', 'prompt' => 'Tô estagnado(a) há um tempo e quero destravar.'],
    ],

    'suggestions_first' => [
        ['label' => '🏦 organizar minha vida financeira', 'prompt' => 'Quero organizar minha vida financeira. Pode me entrevistar pra entender minha situação e a gente montar um plano?'],
        ['label' => '🏃 começar uma rotina de saúde', 'prompt' => 'Quero começar uma rotina de saúde/fitness. Me ajuda a estruturar por onde começar.'],
        ['label' => '📚 estruturar um aprendizado', 'prompt' => 'Quero aprender algo novo de forma estruturada. Me ajuda a montar um caminho.'],
        ['label' => '🧠 pensar em voz alta', 'prompt' => 'Tô precisando pensar em voz alta sobre algo difícil. Me escuta e me ajuda a organizar a cabeça.'],
    ],

    'suggestions_active' => [
        ['label' => 'qual minha situação?', 'prompt' => 'Qual minha situação hoje? Resume o que tá rolando no meu plano.'],
        ['label' => 'tô atrasado em algo?', 'prompt' => 'Tem alguma ação atrasada ou perto de vencer? Foco no que mais importa.'],
        ['label' => 'o que é mais urgente?', 'prompt' => 'Qual a coisa mais urgente que preciso atacar agora?'],
        ['label' => 'como tá o progresso?', 'prompt' => 'Faz um recap rápido: o que avancei, o que travou, o que vem.'],
    ],

    'composer' => [
        'placeholder' => 'fala aí',
        'attach' => 'Anexar arquivo',
    ],

    'conversations' => [
        'untitled' => 'sem título',
    ],

    'sidebar' => [
        'title' => 'Goals',
        'new' => 'novo',
        'new_goal' => 'Criar novo goal',
        'empty' => 'Nenhum goal por enquanto. Cria um pra começar.',
        'no_activity' => 'sem conversa ainda',
    ],

    'header' => [
        'new_thread' => 'nova conversa',
        'history' => 'histórico',
    ],

    'new_goal_modal' => [
        'title' => 'Novo goal',
        'name_label' => 'Nome',
        'name_placeholder' => 'Ex: Sair do vermelho, Meia-maratona, Aprender alemão',
        'label_label' => 'Categoria',
        'cancel' => 'Cancelar',
        'create' => 'Criar goal',
    ],

    'history_panel' => [
        'title' => 'Histórico de conversas',
        'empty' => 'Sem conversas anteriores neste goal.',
    ],

    'plan' => [
        'filters' => [
            'pendente' => 'Pendente',
            'em_andamento' => 'Em andamento',
            'concluido' => 'Concluído',
            'todas' => 'Todas',
        ],
        'empty' => 'Nenhuma ação :status.',
        'empty_pendente' => 'Nada pendente por aqui.',
        'empty_em_andamento' => 'Sem ações em andamento.',
        'empty_concluido' => 'Nada concluído ainda — bora começar.',
        'empty_todas' => 'Sem ações nesse goal. Pede pro coach criar a primeira.',
        'view_all' => 'Ver todas →',
        'mark_done' => 'Concluir',
        'snooze' => 'Adiar',
        'snooze_options' => [
            'tomorrow' => 'Amanhã',
            '3days' => '3 dias',
            'week' => 'Próx. semana',
            'month' => '1 mês',
        ],
        'count' => '{0}sem ações|{1}1 ação|[2,*]:count ações',
        'details' => [
            'expand' => 'Ver detalhes',
            'collapse' => 'Recolher',
            'description' => 'Descrição',
            'importance' => 'Importância',
            'difficulty' => 'Dificuldade',
            'snoozed_until' => 'Adiado até :date',
            'result_notes' => 'Notas de conclusão',
            'completed_at' => 'Concluído em :date',
            'attachments' => 'Anexos',
            'no_attachments' => 'Nenhum anexo',
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
        'CreateGoal' => 'criando goal',
        'RememberFact' => 'salvando na memória',
        'RecallFacts' => 'consultando memória',
        'WebSearch' => 'pesquisando na web',
        'WebFetch' => 'lendo página',
    ],

    'errors' => [
        'no_text_returned' => '_(o coach processou mas não retornou texto — pergunta de novo)_',
        'prefix' => 'Erro: ',
        'truncated_warning' => '_(resposta interrompida no meio — tenta de novo)_',
        'narrated_no_tool' => '_(o coach narrou mas não executou de fato — manda "faz isso de novo" pra ele insistir)_',
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

    'goal_context' => [
        'empty' => 'O usuário ainda não tem foco definido (sem foco definido). Antes de criar ações ou dar conselho específico, pergunte qual área ele quer trabalhar primeiro — pode ser finance, legal, emotional, health, fitness, learning, ou outro tema. Salve a resposta com RememberFact(kind="goal", label="<area>", summary="<o que ele quer trabalhar>").',
        'header' => 'Foco(s) ativo(s) do usuário (especializações que devem guiar suas respostas):',
    ],

    'specializations' => [
        'finance' => 'FINANCE: Foco em fluxo de caixa, dívidas vs reservas, separação PJ/PF quando aplicável, e metas de patrimônio. Faça matemática concreta com os valores reais. NUNCA dê conselho fiscal específico — sempre referencie um contador pra dúvidas regulatórias.',

        'legal' => 'LEGAL: Quando o assunto é contratual/fiscal/regulatório, lembre o usuário de consultar um advogado pra recomendações específicas. Você pode discutir conceitos gerais e ajudar a organizar perguntas pro profissional, mas não substitui assessoria.',

        'emotional' => 'EMOTIONAL: Use empatia genuína, valide sentimentos antes de propor soluções práticas. Evite minimizar ("vai ficar tudo bem") ou apressar. Pra crises (autolesão, ideação suicida), sempre redirecione pra serviços profissionais — no Brasil, CVV 188.',

        'health' => 'HEALTH: Discussões de saúde geral são organizacionais (consultas, exames, hábitos). Pra qualquer dor, sintoma novo ou diagnóstico, refira o usuário a um médico ou profissional de saúde — nunca interprete sintomas nem sugira medicamento.',

        'fitness' => 'FITNESS: Estruture treinos por consistência primeiro, intensidade depois. Pequenos ganhos sustentáveis valem mais que picos. Pra dor articular, lesão, ou início de programa de treino mais pesado, sempre refira a um profissional (educador físico, fisioterapeuta).',

        'learning' => 'LEARNING: Aprendizado funciona com prática progressiva e revisão espaçada. Ajude a estruturar metas pequenas, mensuráveis, com feedback frequente. Reconheça wins concretos. Use a regra dos 80/20: o que dá mais resultado por hora investida.',
    ],
];
