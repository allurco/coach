# Coach.

A self-hosted, multi-tenant AI accountability coach — a chat that doesn't
get tired of asking *"did you actually do that today?"*.

Started as a personal financial-recovery app, generalized into a
goal-agnostic workspace: each goal (debt, fitness, language, side
project, habit, anything) is its own conversation, plan, and memory
context. The AI specializes itself to the goal you're talking to.

## What it does

- **Goals as workspaces** — each goal owns its conversations, plan
  actions, and the agent persona. Sidebar lists them; switching is
  one click.
- **Agent creates goals via chat** — say "I want to start tracking my
  health" and the agent calls `CreateGoal(name='Saúde', label='health')`
  itself; the goal appears in the sidebar.
- **Plan tracking** — actions with deadlines, priorities, importance,
  difficulty, attachments. Inline complete + snooze.
- **Document analysis** — drop a PDF (fatura, extrato, contrato) and
  the agent parses it into a structured table + qualitative read.
- **Long-term memory** — `RememberFact`/`RecallFacts` tools persist
  facts across conversations. Profile facts (`kind=perfil`) survive
  between goals.
- **Scheduled email pings** — daily morning brief, weekly recap,
  weekday stuck-action nudges, replies thread back into the conversation.
- **PWA** — installable on iOS/Android, offline shell, pinned thinking
  dots while the model warms up.

Each user is fully isolated via Eloquent global scopes. Admins invite
others via the avatar menu — no public registration.

---

## Requirements

- **PHP 8.4**
- **Composer 2**, **Node 22+**, **npm**
- **MySQL 8+** (production) or **SQLite** (local dev)
- A **Gemini API key** — free tier works,
  [get one at aistudio.google.com](https://aistudio.google.com)
- A **Resend account** for outgoing email + inbound replies (optional
  for local dev)

---

## Local install

```bash
git clone git@github.com:allurco/coach.git && cd coach

composer install
npm install && npm run build

cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate --seed
#    ↑ prints a random admin password — copy it; it's shown once.

herd link              # if using Laravel Herd (recommended on macOS)
# or:
php artisan serve
```

Open `https://coach.test` (Herd) or `http://localhost:8000`, log in
with `SEEDER_ADMIN_EMAIL` + the password printed above.

---

## Required `.env` keys

```dotenv
APP_NAME=Coach
APP_ENV=local
APP_URL=https://coach.test
APP_LOCALE=pt_BR              # or "en"
APP_FALLBACK_LOCALE=pt_BR
APP_TIMEZONE=America/Fortaleza

# Database — SQLite for local, MySQL for production
DB_CONNECTION=sqlite

# AI — pro for the interactive chat (more reliable on multi-tool turns),
# flash for short single-shot crons + email replies
GEMINI_API_KEY=AIzaSy...
COACH_MODEL_INTERACTIVE=gemini-2.5-pro
COACH_MODEL_BACKGROUND=gemini-2.5-flash

# Email (Resend)
MAIL_MAILER=resend
RESEND_API_KEY=re_...
MAIL_FROM_ADDRESS="coach@coach.allur.co"
MAIL_FROM_NAME="Coach"

# Coach
COACH_NOTIFICATION_EMAIL=you@example.com
COACH_WEBHOOK_SECRET=                      # 32+ char random string
COACH_REPLY_DOMAIN=coach.allur.co

# Optional — Tavily web search tool (free tier: 1000 queries/month)
TAVILY_API_KEY=

# Seeder admin (read once on first `db:seed`)
SEEDER_ADMIN_EMAIL=you@example.com
SEEDER_ADMIN_NAME=Your Name
```

`COACH_WEBHOOK_SECRET`: generate with
`php -r "echo bin2hex(random_bytes(32)),\"\n\";"`.

---

## Email setup (Resend)

Coach sends scheduled pings and accepts replies via inbound webhook.
Both go through Resend.

### 1. Verify your sending domain

In **Resend → Domains → Add domain**, verify either:

- **A subdomain (recommended for free tier)** — e.g. `coach.allur.co`.
  Single verification covers both sending and receiving without
  touching root MX.
- **Root domain + separate `inbound.your.com`** — paid plan, two
  verifications.

Add the DNS records Resend gives you (SPF, DKIM, DMARC). Wait for
"Verified".

### 2. Enable Receiving

Inside the verified domain, toggle **Enable Receiving** on. Resend
gives you an MX record for the same subdomain. Add it.

> ⚠️ Don't enable Receiving on the root domain if you already use
> Google Workspace, Microsoft 365, or any other email provider —
> it will replace the MX and break your inbox. Use a subdomain
> instead.

### 3. Configure the inbound webhook

**Resend → Webhooks → Add Endpoint**
- URL: `https://your-domain.com/webhooks/coach-email`
- Add the `X-Coach-Secret` header set to your `COACH_WEBHOOK_SECRET`
- Subscribe to the **email.received** event

The `to` field carries the conversation id
(`reply+UUID@coach.allur.co`) so replies thread correctly.

### 4. Test sending

```bash
php artisan tinker --execute='Mail::raw("test", fn ($m) => $m->to("you@example.com")->subject("test"));'
```

Check the headers for SPF/DKIM/DMARC = PASS.

---

## Multi-tenant: how to invite users

Closed registration — only admins invite.

1. Log in as admin
2. Click your **avatar (top right) → Invite user**
3. Fill name + email, optionally check "Admin"
4. Resend sends them a one-time link to set their password
5. They click → set password → land in their (empty) Coach
6. Coach interviews them onboarding-style and builds their plan

---

## Onboarding (first user)

When you first log in, your plan and memories are empty. Two paths:

### A. Talk to the Coach (recommended)

Open `/` and start typing. The agent detects the empty state, runs
in onboarding mode — interviews you, identifies your situation,
saves profile facts (`kind=perfil`) and creates actions as the
conversation flows.

### B. Bulk-import via paste

Paste a structured prompt with your situation and existing actions.
The agent will save them via `RememberFact(kind='perfil')` and
`CreateAction` calls. See `database/seeds/plan.example.json` and
`coach-context.example.json` for the level of detail to include.

---

## Goals & specialization

Each goal carries a `label` that the agent uses to specialize its
tone and guardrails:

| label | specialization |
| --- | --- |
| `general` | discovery mode, asks where to focus |
| `finance` | hard math on cash flow, never gives tax advice |
| `legal` | refers to a lawyer for specifics |
| `emotional` | empathy first, escalates to crisis lines on red flags |
| `health` | refers symptoms to a doctor |
| `fitness` | consistency over intensity |
| `learning` | spaced practice, 80/20 rule |

Specialization prompts live in `lang/{pt_BR,en}/coach.php` so they
ship in both locales. Add a new label by adding a key to the
`specializations` array and listing it in `Goal::LABELS`.

---

## Internationalization

Two locales: **pt_BR** (default) and **en**. Switch with `APP_LOCALE`
in `.env`. Every UI string, the invitation email, and the
set-password page are translated. Files in
`lang/{pt_BR,en}/{coach,users,invitation}.php`.

To add another locale, copy `lang/en/` to `lang/<code>/` and translate.

---

## Production deploy (Forge example)

1. Create site on Forge pointing to your repo, branch `main`,
   web root `/public`
2. **Servers → PHP** → install **8.4** and set as CLI version
3. **Site → Meta** → set PHP version to **8.4**
4. **Environment**: set every key from the section above. Forge runs
   `config:cache`, so values must come through `config('coach.*')`
   — the app already does this everywhere.
5. **Deploy script**: ensure it runs `composer install`,
   `npm install && npm run build`, `php artisan migrate --force`,
   `php artisan optimize:clear`
6. **Cron**: enable Forge's scheduler or add manually:
   ```cron
   * * * * * cd /home/forge/coach.allur.co && php artisan schedule:run >> /dev/null 2>&1
   ```
7. **Cloudflare**: SSL/TLS mode = **Full** (not Flexible). Flexible
   breaks the session-cookie loop because Laravel reads the request
   as HTTP.
8. **First seed**: SSH to the server, run `php artisan db:seed` once.
   Copy the random admin password it prints.
9. Visit your domain, log in, change password in your settings.

---

## Architecture

```
app/
├── Ai/
│   ├── Agents/FinanceCoach.php   ← prompt, persona, onboarding mode,
│   │                              tool wiring, active-goal resolution
│   └── Tools/                     ← 8 tools the agent can call
│       ├── ListActions / CreateAction / UpdateAction
│       ├── CreateGoal             ← agent opens new sidebar workspaces
│       ├── RememberFact / RecallFacts
│       └── WebSearch / WebFetch
├── Console/Commands/
│   ├── CoachMorningPing           ← daily 8am
│   ├── CoachWeeklyBriefing        ← Sunday 8pm
│   └── CoachStuckCheck            ← weekday noon
├── Filament/
│   ├── Pages/Coach.php            ← chat UI, plan flyout, goal sidebar,
│   │                              streaming + auto-retry on truncation
│   └── Resources/Users/           ← admin-only user management
├── Http/Controllers/
│   ├── CoachWebhookController     ← inbound email reply parser
│   └── InvitationController       ← public set-password page
├── Mail/
│   ├── CoachPing                  ← scheduled ping with conv-aware Reply-To
│   └── UserInvitation             ← invite email
├── Models/
│   ├── Action                     ← plan items, scoped to (user_id, goal_id)
│   ├── AgentConversation          ← read model over laravel/ai messages
│   ├── CoachMemory                ← long-term memories, scoped to user_id
│   ├── Goal                       ← workspace; owns actions + conversations
│   └── User                       ← FilamentUser, isAdmin, invitation helpers
└── Services/
    ├── EmailReplyParser           ← strip quoted text from replies
    └── CoachReplyProcessor        ← logs in user, routes reply to conversation
```

Multi-tenant isolation is enforced by Eloquent global scopes on
every model that holds user data (`Action`, `CoachMemory`, `Goal`,
`AgentConversation`). Console commands and the webhook controller
call `auth()->login($user)` before invoking the agent so the scope
kicks in.

When the agent's reply looks truncated (ends with `:` and no tool
call) or hallucinates an action it didn't run, `Coach.php` runs an
auto-retry pass with a corrective system nudge. A
`decorateAssistantResponse()` helper appends a discreet warning
when the second pass also misses, and the whole event is logged at
WARN level for postmortem.

---

## Tests

```bash
./vendor/bin/pest --compact
```

178 tests covering: tools (CreateAction/UpdateAction/CreateGoal —
date parsing, goal scoping, label validation), goal-specialization
prompt routing, multi-tenant isolation, response decorator
(truncation + hallucinated-tool detection), invitation flow,
Reply-To routing, webhook auth, EmailReplyParser, Action model,
PWA assets, and the Coach send guard.

CI runs the same suite on PHP 8.4 (`.github/workflows/tests.yml`).
Tests are written to pass in **both locales** (`pt_BR` + `en`) — CI
runs with `APP_LOCALE=en` while local dev defaults to `pt_BR`.

---

## Stack

- **Laravel 13** + **PHP 8.4**
- **Filament v5** (admin panel + chat UI)
- **Laravel AI SDK** (`laravel/ai`) — Gemini 2.5 Pro for the interactive
  chat, 2.5 Flash for the cron pings + email reply flows
- **Livewire 4** streaming for real-time coach responses
- **Resend** (transactional email + inbound webhook)
- **Pest 4** (tests)
- **Tailwind v4** + custom CSS for the Coach experience
- **SQLite** (local) / **MySQL** (production)
- Optional: **Tavily** for the WebSearch tool

---

## License

MIT
