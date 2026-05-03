# Coach.

Personal accountability coach for financial recovery.

A self-hosted Laravel + Filament app where an AI coach (Gemini) helps you
out of financial trouble by:

- Tracking actions of a recovery plan with deadlines and priorities
- Sending scheduled email pings (morning brief, weekly recap, stuck-action nudges)
- Reading PDFs you upload (faturas, extratos, boletos) and summarizing them
- Remembering important facts across conversations (long-term memory)
- Replying to your email messages via inbound webhook

## Setup

```bash
# 1. Clone
git clone git@github.com:allurco/coach.git && cd coach

# 2. Install
composer install
npm install && npm run build

# 3. Configure env
cp .env.example .env
php artisan key:generate

# Required env vars to fill in .env:
#   GEMINI_API_KEY        — from https://aistudio.google.com
#   RESEND_API_KEY        — from https://resend.com (optional, for real email)
#   SEEDER_ADMIN_EMAIL    — your email (will be the admin user)
#   SEEDER_ADMIN_NAME     — your display name
#   COACH_NOTIFICATION_EMAIL  — where pings get sent
#   COACH_WEBHOOK_SECRET  — random 32-char string for inbound webhook auth

# 4. Database
touch database/database.sqlite
php artisan migrate --seed
# Note the random admin password printed — save it.

# 5. Optional: customize coach personality
cp database/seeds/coach-context.example.json database/seeds/coach-context.json
# Edit coach-context.json with your situation, goals, etc.

# 6. Run
php artisan serve   # or use Herd: herd link
```

Open `http://localhost:8000`, log in with the admin email + password from step 4.

## Onboarding

Two ways to populate your plan:

### A. Talk to the Coach (recommended for new users)

Just open `/coach` and start typing. The Coach detects an empty plan and
goes into onboarding mode — interviews you about your situation, identifies
problems, and creates actions via tools as it learns. Same way a human coach
would work.

### B. Bulk-import a JSON

If you already have a plan structured as JSON:

1. Drop your file at `database/seeds/plan.json` (gitignored — your file stays local)
2. Run `php artisan db:seed --class=Database\\Seeders\\InitialPlanSeeder`

See `database/seeds/plan.example.json` for the expected shape.

You can also paste a structured JSON directly into the Coach chat — it'll
parse and call CreateAction for each item (after confirming with you).

## Architecture

```
app/
├── Ai/
│   ├── Agents/FinanceCoach.php   ← prompt + 5 tools, persona, onboarding mode
│   └── Tools/                     ← ListActions, CreateAction, UpdateAction, RememberFact, RecallFacts
├── Console/Commands/
│   ├── CoachMorningPing.php       ← daily 8am Fortaleza
│   ├── CoachWeeklyBriefing.php    ← Sunday 8pm
│   └── CoachStuckCheck.php        ← weekday noon
├── Filament/
│   ├── Pages/Coach.php            ← chat interface with streaming
│   └── Resources/Actions/         ← plan CRUD
├── Http/Controllers/
│   └── CoachWebhookController.php ← inbound email reply parser
├── Mail/CoachPing.php             ← Mailable for scheduled pings
├── Models/
│   ├── Action.php                 ← plan items
│   └── CoachMemory.php            ← long-term memories
└── Services/
    ├── EmailReplyParser.php       ← strip quoted text from replies
    └── CoachReplyProcessor.php    ← route reply to conversation
```

## Stack

- Laravel 13 + PHP 8.4
- Filament v5 (admin panel + UI)
- Laravel AI SDK (`laravel/ai`) — Gemini provider
- Resend (email delivery)
- Livewire 4 streaming for real-time coach responses
- SQLite (default, swap for MySQL in `.env`)

## Production

Schedule needs cron running on the server:

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

For inbound email replies to work, the app must be publicly reachable and
your email provider (Resend) must be configured with the webhook URL pointing
to `/webhooks/coach-email` with the `X-Coach-Secret` header.

## License

MIT
