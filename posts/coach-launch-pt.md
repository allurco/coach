---
title: "Coach.: um coach financeiro open-source com IA pra você self-hospedar"
description: "Construí uma IA pra me tirar do vermelho. Aí abri o código."
date: 2026-05-03
tags: [open-source, ia, laravel, filament, gemini]
---

# Coach.

Construí um coach financeiro com IA pra mim mesmo e acabei de abrir o
código em [github.com/allurco/coach](https://github.com/allurco/coach).

## O porquê

Alguns meses atrás eu tava com um déficit estrutural. A renda dava, a
matemática é que não fechava. Eu precisava de alguém — ou alguma coisa —
que não cansasse de perguntar todo dia *"você pagou aquela fatura ou tá
empurrando?"*.

Um consultor humano pesava: agendar, carga emocional, custo. Um app de
notas não me cobraria. Comecei a construir uma coisa que faz as duas:
mantém o plano e me cobra ele.

A primeira versão era só uma página Laravel com chat ligado no Gemini.
Funcionou rápido o suficiente pra eu continuar adicionando — análise
de PDF de fatura/extrato, ações com prazo, pings por email, e uma
camada de memória pra ele lembrar do que a gente já tinha decidido.

Depois de algumas semanas usando todo dia, percebi que qualquer pessoa
em situação parecida iria querer a mesma ferramenta. Reescrevi pra
multi-tenant e abri o repo.

## O que ele faz

Abre o app, escreve em português (ou inglês) o que tá rolando. O Coach:

- **Te entrevista no primeiro contato** — entende renda, despesas,
  identifica armadilhas (cheque especial, parcelamento a 491% ao ano),
  propõe 2-3 ações concretas pra semana.
- **Lê PDF** que você anexar — fatura, extrato, boleto. Devolve uma
  tabela estruturada + análise qualitativa (ex: "Vi R\$ 762 de Google
  Cloud no cartão pessoal; isso deveria estar no PJ").
- **Rastreia seu plano** — ações com prazo, prioridade, categoria.
  Você marca como concluído. O Coach cobra o que está parado.
- **Manda briefing matinal** às 8h ("Foco do dia: pague o cartão menor
  antes do maior gerar juros"), recap semanal no domingo à noite, e
  ping de ação parada no meio da semana.
- **Você pode responder por email** — responde o briefing pelo celular
  e o Coach atualiza o plano. A resposta volta pra mesma conversa via
  subaddressing no Reply-To.
- **Lembra entre conversas** — memória de longo prazo dos fatos que
  você consolidou semanas atrás. Quando você fala "a fatura Santander
  de maio", ele sabe do que você tá falando sem você reanexar.

## Por que abrir o código

Duas razões.

**Uma**, problema financeiro é muito mais comum do que as pessoas
admitem, e os apps que existem nessa área ou são agregadores bancários
que viram seus dados em targeting publicitário, ou são calculadoras de
orçamento estéreis que não pegam a parte humana. Quem roda o próprio
Coach na própria infra mantém os dados privados e ainda ganha uma
ferramenta com opinião.

**Duas**, eu uso isso todo dia. Abrir o código me força a manter em um
estado onde alguém de fora consegue clonar e rodar — que é a barra
mínima que eu quero pra software do qual dependo.

## Stack

- **Laravel 13** + **PHP 8.4** no backend
- **Filament v5** pra UI admin/chat (chat-first, sem dashboard)
- **Laravel AI SDK** com **Gemini 2.5 Flash** como modelo
- **Livewire 4 streaming** pro Coach digitar em tempo real
- **Resend** pra email outbound + webhook inbound
- **Pest** pros testes (75 hoje, incluindo isolamento multi-tenant)
- **SQLite** local, **MySQL** em produção
- **Tailwind v4** + CSS custom pra experiência da conversa

Tudo self-hosted. Não tem dependência de SaaS além da chave do Gemini
(free tier cobre um user) e Resend (free tier cobre um domínio).

## Multi-tenant desde o dia um

Quando reescrevi pra compartilhar, fui no row-level: cada ação e cada
memória pertence a um `user_id`, com global scopes garantindo que
acesso cruzado é impossível. Não tem registro público — admin convida
pelo menu do avatar e cada convidado define sua senha via link único.
Loop fechado.

## Como testar

```bash
git clone git@github.com:allurco/coach.git
cd coach
composer install && npm install && npm run build
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
```

Pronto local. O seeder imprime uma senha única do admin. Abre o app,
loga, começa a escrever sobre sua situação. O Coach entra em modo
onboarding, te entrevista, e começa a montar seu plano do zero.

Pra deploy em produção (Forge, Cloud, etc), o README explica o DNS
do Resend (o pulo do gato é *não colocar MX de receiving num domínio
que já roda Google Workspace*), config de SSL no Cloudflare (usa Full,
não Flexible — Flexible quebra cookie de sessão), e o requisito de
PHP 8.4.

---

Se você construir algo em cima, ou achar um bug, manda PR. O repo
tá em [github.com/allurco/coach](https://github.com/allurco/coach).
Licença MIT.
