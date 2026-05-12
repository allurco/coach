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
        'MoveAction' => 'movendo ação de goal',
        'CreateGoal' => 'criando goal',
        'SwitchToGoal' => 'mudando de workspace',
        'BudgetSnapshot' => 'montando plano financeiro',
        'ReadBudget' => 'lendo orçamento atual',
        'LogWhy' => 'guardando o porquê',
        'LogWorry' => 'registrando preocupação',
        'RememberFact' => 'salvando na memória',
        'RecallFacts' => 'consultando memória',
        'ShareViaEmail' => 'enviando email',
        'WebSearch' => 'pesquisando na web',
        'WebFetch' => 'lendo página',
    ],

    'budget_reminder' => [
        'subject_recurring' => 'Hora do Plano — vamos atualizar?',
        'subject_intro' => 'Já usou o Planejador Financeiro?',
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

    'tips' => [
        'dismiss_label' => 'Dispensar dica',
        'pick_focus_area' => [
            'title' => 'Em que área quer focar primeiro?',
            'prompt' => 'Em que área eu deveria começar?',
        ],
        'set_up_budget' => [
            'title' => 'Monta seu plano financeiro',
            'prompt' => 'Quero montar meu plano financeiro do mês.',
        ],
        'refresh_budget' => [
            'title' => 'Atualiza o orçamento do mês',
            'prompt' => 'Bora atualizar o orçamento desse mês.',
        ],
        'add_first_action' => [
            'title' => 'Cria a primeira ação concreta',
            'prompt' => 'Me ajuda a definir a primeira ação concreta desse goal.',
        ],
        'review_overdue' => [
            'title' => 'Tem ação atrasada',
            'prompt' => 'Tem ação atrasada? Vamos resolver ou adiar.',
        ],
        'log_first_win' => [
            'title' => 'Registra como foi essa conclusão',
            'prompt' => 'Conclui uma ação — quero registrar como foi.',
        ],
        'trim_heavy_plan' => [
            'title' => 'Plano lotado — bora enxugar?',
            'prompt' => 'Acho que tenho ações demais. Me ajuda a enxugar.',
        ],
        'add_second_goal' => [
            'title' => 'Abrir uma segunda área de foco',
            'prompt' => 'Quero abrir um segundo goal pra outra área da vida.',
        ],
        'revisit_dormant_goal' => [
            'title' => 'Esse goal anda parado',
            'prompt' => 'Esse goal anda parado — me ajuda a destravar.',
        ],
        'log_the_why' => [
            'title' => 'Por que esse goal importa pra você?',
            'prompt' => 'Quero registrar por que esse goal importa pra mim.',
        ],
        'revisit_worry' => [
            'title' => 'Aquela preocupação — materializou?',
            'prompt' => 'Vamos revisitar aquela preocupação que registrei — ela materializou?',
        ],
        'save_contact' => [
            'title' => 'Salva um contato pra compartilhar depois',
            'prompt' => 'Quero salvar um contato (contador, parceiro) pra compartilhar coisas depois.',
        ],
        'share_plan' => [
            'title' => 'Compartilha seu plano por email',
            'prompt' => 'Quero compartilhar meu plano por email com alguém.',
        ],
    ],

    'goal_context' => [
        'empty' => 'O usuário ainda não tem foco definido (sem foco definido). Antes de criar ações ou dar conselho específico, pergunte qual área ele quer trabalhar primeiro — pode ser finance, legal, emotional, health, fitness, learning, ou outro tema. Salve a resposta com RememberFact(kind="goal", label="<area>", summary="<o que ele quer trabalhar>").',
        'header' => 'Foco(s) ativo(s) do usuário (especializações que devem guiar suas respostas):',
    ],

    'placeholders' => [
        'budget_missing' => '_(snapshot indisponível)_',
        'plan_empty' => '_(plano vazio — nenhuma ação em curso)_',
        'plan_header' => '**📋 Plano atual**',
    ],

    'share' => [
        'default_subject' => 'Compartilhamento do meu Coach',
        'success' => 'Enviado pra :email (com cópia pra você).',
        'errors' => [
            'unauthenticated' => 'Erro: usuário não autenticado.',
            'empty_body' => 'Erro: o corpo do email não pode estar vazio.',
            'unknown_recipient' => 'Erro: ":value" não é um email válido nem um contato salvo.',
            'rate_limited' => 'Limite de envios atingido — tenta de novo em :minutes minuto(s).',
        ],
    ],

    'budget' => [
        'auto_close_note' => 'Concluída automaticamente quando o snapshot #:snapshot_id foi gerado.',
    ],

    'budget_flyout' => [
        'toggle' => 'Budget',
        'title' => 'Orçamento atual',
        'subtitle' => 'Mês :month',
        'net_income' => 'Renda líquida',
        'fixed_costs' => 'Custos fixos',
        'investments' => 'Investimentos',
        'savings' => 'Reservas',
        'leisure' => 'Lazer (sobra)',
        'total' => 'Total',
        'subtotal' => 'Subtotal',
        'total_with_buffer' => 'Total com buffer 15%',
        'empty_bucket' => '_(sem linhas)_',
        'deficit_warning' => 'Atenção: déficit de :amount — as caixas planejadas estouram a renda.',
    ],

    'read_budget' => [
        'unauthenticated' => 'Erro: usuário não autenticado.',
        'none' => 'Sem orçamento ainda — você nunca rodou o BudgetSnapshot. Pra criar um, precisamos da sua renda líquida + lista de custos fixos.',
    ],

    'life_context' => [
        'header' => 'Contexto de vida (transversal a todos os goals — use pra orientar conselhos em qualquer área):',
        'budget' => [
            'none' => 'Financeiro: sem orçamento ainda.',
            'surplus' => 'Financeiro: orçamento de :month com folga mensal de :amount.',
            'deficit' => 'Financeiro: orçamento de :month com déficit mensal de :amount.',
            'balanced' => 'Financeiro: orçamento de :month com renda e gastos batendo.',
        ],
        'tool_hint' => 'Quando a conversa tocar em valores grandes ou compromissos financeiros (mesmo fora do goal de finanças), chame BudgetSnapshot pra ver o detalhe antes de aconselhar. Se já existe orçamento (a linha acima diz qual mês), NÃO peça pro usuário criar um novo do zero — chame ReadBudget pra puxar o que existe.',
    ],

    'share_modal' => [
        'icon_label' => 'Compartilhar por email',
        'title' => 'Compartilhar essa mensagem',
        'default_subject' => 'Resumo do Coach — :date',
        'recipient_label' => 'Para',
        'recipient_placeholder' => 'email ou nome do contato salvo',
        'subject_label' => 'Assunto',
        'body_label' => 'Mensagem',
        'send' => 'Enviar',
        'cancel' => 'Cancelar',
    ],

    'specializations' => [
        'finance' => "FINANCE: Foco em fluxo de caixa, dívidas vs reservas, separação PJ/PF quando aplicável, e metas de patrimônio. Faça matemática concreta com os valores reais. NUNCA dê conselho fiscal específico — sempre referencie um contador pra dúvidas regulatórias.\n\nPlanejador Financeiro (4 caixas): quando o usuário entrevistar com renda + gastos OU pedir um plano financeiro, use o tool **BudgetSnapshot**. Ele divide a renda líquida em quatro caixas:\n  1. Custos Fixos (alvo 50-60%): aluguel, contas, mercado, seguros, transporte, dívidas, assinaturas. Aplica buffer automático de 15% pra cobrir linhas esquecidas.\n  2. Investimentos (alvo 10%): aposentadoria, ações, longo prazo.\n  3. Reservas (alvo 5-10%): emergência, viagens, metas específicas.\n  4. Lazer (alvo 20-35%): é a SOBRA — `renda líquida - fixos - investimentos - reservas`. Não orçe upfront, calcule.\n\nFluxo recomendado: pergunte renda líquida + lista os custos fixos linha a linha + investimentos + reservas. Quando o usuário mencionar um mês ('plano de junho', 'pra julho'), passe `month` no formato `YYYY-MM` ou `MM/YYYY` — não deixe vazio, senão vai pro mês corrente. Chame BudgetSnapshot com o breakdown. A tabela do tool aparece automaticamente no chat — **não repita os números nem reescreva a tabela**. Depois da chamada, comente em 1-2 frases qual caixa está fora do alvo e proponha UMA mudança concreta. O output do tool é a fotografia oficial; suas frases são o coaching em cima dela.",

        'legal' => 'LEGAL: Quando o assunto é contratual/fiscal/regulatório, lembre o usuário de consultar um advogado pra recomendações específicas. Você pode discutir conceitos gerais e ajudar a organizar perguntas pro profissional, mas não substitui assessoria.',

        'emotional' => 'EMOTIONAL: Use empatia genuína, valide sentimentos antes de propor soluções práticas. Evite minimizar ("vai ficar tudo bem") ou apressar. Pra crises (autolesão, ideação suicida), sempre redirecione pra serviços profissionais — no Brasil, CVV 188.',

        'health' => 'HEALTH: Discussões de saúde geral são organizacionais (consultas, exames, hábitos). Pra qualquer dor, sintoma novo ou diagnóstico, refira o usuário a um médico ou profissional de saúde — nunca interprete sintomas nem sugira medicamento.',

        'fitness' => 'FITNESS: Estruture treinos por consistência primeiro, intensidade depois. Pequenos ganhos sustentáveis valem mais que picos. Pra dor articular, lesão, ou início de programa de treino mais pesado, sempre refira a um profissional (educador físico, fisioterapeuta).',

        'learning' => 'LEARNING: Aprendizado funciona com prática progressiva e revisão espaçada. Ajude a estruturar metas pequenas, mensuráveis, com feedback frequente. Reconheça wins concretos. Use a regra dos 80/20: o que dá mais resultado por hora investida.',
    ],
];
