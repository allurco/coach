# Coach.

Personal accountability coach for financial recovery.

A self-hosted multi-tenant Laravel + Filament app where an AI coach (Gemini)
helps users out of financial trouble by:

- Tracking actions of a recovery plan with deadlines and priorities
- Sending scheduled email pings (morning brief, weekly recap, stuck-action nudges)
- Reading PDFs uploaded (faturas, extratos, boletos) and summarizing them
- Remembering important facts across conversations (long-term memory)
- Replying via email — replies thread back into the same conversation

Each user has their own isolated plan, memories, and conversations. Admin
invites others through the avatar menu — no public registration.

---

## Requirements

- **PHP 8.4** (the lock file pins it; older PHP will fail at `composer install`)
- **Composer 2**
- **Node 22+** and `npm`
- **MySQL 8+** (production) or **SQLite** (local dev)
- A **Gemini API key** — free tier works, [get one at aistudio.google.com](https://aistudio.google.com)
- A **Resend account** for outgoing email + inbound replies (optional for local dev)

---

## Local install

```bash
# 1. Clone
git clone git@github.com:allurco/coach.git && cd coach

# 2. PHP and frontend deps
composer install
npm install && npm run build

# 3. Environment
cp .env.example .env
php artisan key:generate

# 4. Database (SQLite local default)
touch database/database.sqlite
php artisan migrate --seed
#    ↑ prints a random admin password — copy it; it's shown once.

# 5. Run
herd link              # if using Laravel Herd (recommended on macOS)
# or:
php artisan serve
```

Open `https://coach.test` (Herd) or `http://localhost:8000`, log in with
`SEEDER_ADMIN_EMAIL` + the password printed above.

---

## Required `.env` keys

The minimum for the app to boot:

```dotenv
APP_NAME=Coach
APP_ENV=local
APP_URL=https://coach.test
APP_LOCALE=pt_BR              # or "en"
APP_FALLBACK_LOCALE=pt_BR
APP_TIMEZONE=America/Fortaleza

# Database — SQLite for local, MySQL for production
DB_CONNECTION=sqlite
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=coach
# DB_USERNAME=...
# DB_PASSWORD=...

# AI
GEMINI_API_KEY=AIzaSy...

# Email (Resend)
MAIL_MAILER=resend
RESEND_API_KEY=re_...
MAIL_FROM_ADDRESS="coach@coach.allur.co"
MAIL_FROM_NAME="Coach"

# Coach
COACH_NOTIFICATION_EMAIL=you@example.com   # where scheduled pings go
COACH_WEBHOOK_SECRET=                      # 32+ char random string
COACH_REPLY_DOMAIN=coach.allur.co          # subaddressing host for Reply-To

# Seeder admin (read once on first `db:seed`)
SEEDER_ADMIN_EMAIL=you@example.com
SEEDER_ADMIN_NAME=Your Name
```

`COACH_WEBHOOK_SECRET`: generate with `php -r "echo bin2hex(random_bytes(32)),\"\n\";"`.

---

## Email setup (Resend)

The Coach sends scheduled pings and accepts replies via inbound webhook.
Both go through Resend.

### 1. Verify your sending domain

In **Resend → Domains → Add domain**, verify either:

- **A subdomain (recommended for free tier)** — e.g. `coach.allur.co`. Single
  verification covers both sending and receiving without touching root MX.
- **Root domain + separate `inbound.your.com`** — paid plan, two verifications.

Add the DNS records Resend gives you (SPF, DKIM, DMARC). Wait for "Verified".

### 2. Enable Receiving

Inside the verified domain, toggle **Enable Receiving** on. Resend gives you
an MX record for the same subdomain. Add it.

> ⚠️ Don't enable Receiving on the root domain if you already use Google
> Workspace, Microsoft 365, or any other email provider — it will replace the
> MX and break your inbox. Use a subdomain instead.

### 3. Configure the inbound webhook

**Resend → Webhooks → Add Endpoint**
- URL: `https://your-domain.com/webhooks/coach-email`
- Add the `X-Coach-Secret` header set to your `COACH_WEBHOOK_SECRET`
- Subscribe to the **email.received** event

The webhook payload supports these fields (Resend sends all of them):
`from`, `to`, `subject`, `text`, `html`. The `to` field carries the
conversation id (`reply+UUID@coach.allur.co`) so replies thread correctly.

### 4. Test sending

```bash
php artisan tinker --execute='Mail::raw("test", fn ($m) => $m->to("you@example.com")->subject("test"));'
```

Check your Gmail (or whatever inbox) for SPF/DKIM/DMARC = PASS in the headers.

---

## Multi-tenant: how to invite users

The app is closed registration — only admins invite.

1. Log in as admin
2. Click your **avatar (top right) → Invite user**
3. Fill name + email, optionally check "Admin"
4. Resend sends them an email with a one-time link to set their password
5. They click → set password → land in their (empty) Coach
6. Coach interviews them onboarding-style and builds their plan

Existing users (if you migrated from a single-tenant install) are all
auto-promoted to admin during the migration so nobody loses access.

---

## Onboarding (first user)

When you first log in your plan and memories are empty. Two paths:

### A. Talk to the Coach (recommended)

Open `/` and start typing. The Coach detects empty state and goes into
onboarding mode — interviews you, identifies problems, creates actions
and saves profile facts (`kind=perfil`) as you talk.

### B. Bulk-import via paste

Paste a structured prompt into the Coach chat with your situation
(profile facts) and your existing actions. The Coach will save them via
`RememberFact(kind='perfil')` and `CreateAction` calls. See
`database/seeds/plan.example.json` and `coach-context.example.json` for
the kind of detail to include.

---

## Internationalization

Two locales shipped: **pt_BR** (default) and **en**. Switch with
`APP_LOCALE` in `.env`. All UI strings, the invitation email, and
the set-password page are translated. Translation files live in
`lang/{pt_BR,en}/{coach,users,invitation}.php`.

To add another locale, copy `lang/en/` to `lang/<code>/` and translate.

---

## Production deploy (Forge example)

1. Create site on Forge pointing to your repo, branch `main`, web root `/public`
2. **Servers → PHP** → install **8.4** and set as CLI version
3. **Site → Meta** → set PHP version to **8.4**
4. **Environment**: set every key from the section above (note — Forge runs
   `config:cache`, so `env()` outside config files won't read your values;
   the app already only reads via `config('coach.*')`)
5. **Deploy script** — defaults are fine; ensure it runs `composer install`,
   `npm install && npm run build`, `php artisan migrate --force`,
   `php artisan optimize:clear`
6. **Cron**: enable Forge's scheduler or add manually:
   ```cron
   * * * * * cd /home/forge/coach.allur.co && php artisan schedule:run >> /dev/null 2>&1
   ```
7. **Cloudflare**: SSL/TLS mode = **Full** (not Flexible). Flexible breaks the
   session cookie loop because Laravel reads the request as HTTP.
8. **First seed**: SSH to the server, run `php artisan db:seed` once.
   Copy the random admin password it prints.
9. Visit your domain, log in, change password in your settings.

---

## Architecture

```
app/
├── Ai/
│   ├── Agents/FinanceCoach.php   ← prompt + 5 tools, persona, onboarding mode
│   └── Tools/                     ← ListActions, CreateAction, UpdateAction, RememberFact, RecallFacts
├── Console/Commands/
│   ├── CoachMorningPing.php       ← daily 8am
│   ├── CoachWeeklyBriefing.php    ← Sunday 8pm
│   └── CoachStuckCheck.php        ← weekday noon
├── Filament/
│   ├── Pages/Coach.php            ← chat interface (streaming) + plan flyout
│   └── Resources/Users/           ← admin-only user management
├── Http/Controllers/
│   ├── CoachWebhookController.php ← inbound email reply parser
│   └── InvitationController.php   ← public set-password page
├── Mail/
│   ├── CoachPing.php              ← scheduled ping with conv-aware Reply-To
│   └── UserInvitation.php         ← invite email
├── Models/
│   ├── Action.php                 ← plan items, scoped to user_id
│   ├── CoachMemory.php            ← long-term memories, scoped to user_id
│   └── User.php                   ← FilamentUser, isAdmin, invitation helpers
└── Services/
    ├── EmailReplyParser.php       ← strip quoted text from replies
    └── CoachReplyProcessor.php    ← logs in user, routes reply to conversation
```

Multi-tenant isolation is enforced via Eloquent global scopes on `Action`
and `CoachMemory` — every query auto-filters by `auth()->id()`. Console
commands and the webhook controller call `auth()->login($user)` before
running the agent so the scope still kicks in.

---

## Tests

```bash
./vendor/bin/pest --compact
```

75 tests covering: tools (CreateAction/UpdateAction date parsing, scoping),
multi-tenant isolation (Alice can't see Bob's plan), invitation flow
(token validation, expiry, single-use, panel access blocking),
Reply-To routing (subaddressing extraction, fallback to subject),
webhook auth, EmailReplyParser, Action model.

CI runs the same on PHP 8.4 (`.github/workflows/tests.yml`).

---

## Stack

- **Laravel 13** + **PHP 8.4**
- **Filament v5** (admin panel + chat UI)
- **Laravel AI SDK** (`laravel/ai`) — Gemini provider
- **Livewire 4** streaming for real-time coach responses
- **Resend** (transactional email + inbound webhook)
- **Pest 4** (tests)
- **Tailwind v4** + custom CSS for the Coach experience
- **SQLite** (local) / **MySQL** (production)

---

## License

MIT
