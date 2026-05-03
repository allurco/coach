---
title: "Coach.: an open-source AI financial coach you self-host"
description: "I built an AI to coach me out of financial trouble. Then I open-sourced it."
date: 2026-05-03
tags: [open-source, ai, laravel, filament, gemini]
---

# Coach.

I built an AI financial coach for myself, and just open-sourced it at
[github.com/allurco/coach](https://github.com/allurco/coach).

## Why

A few months ago I had a structural deficit. Income was fine, the math
wasn't. I needed someone — or something — that wouldn't get tired of
asking *"did you actually pay that today?"* every morning.

A human consultant felt heavy: scheduling, emotional load, the cost.
A note-taking app wouldn't push back. So I started building a thing
that does both: keeps the plan, and pings me about it.

The first version was just a Laravel page with a Gemini-powered chat.
It got useful fast enough that I kept adding to it — PDF analysis of
fatura/extrato, action tracking with deadlines, scheduled email pings,
and a memory layer so it remembered what we'd already decided.

After a few weeks of using it daily, I figured anyone in a similar mess
might want the same tool. So I rewrote it for multi-tenancy and made
the repo public.

## What it actually does

Open the app, type in plain Portuguese (or English) what's going on.
The Coach:

- **Interviews you on first contact** — figures out the income/expense
  picture, identifies traps (cheque especial, parcelamento at 491% APR),
  proposes 2-3 concrete actions for the week.
- **Reads PDFs** you upload — fatura, extrato, boleto. Gives you a
  structured table + a qualitative analysis (e.g. "I see R\$ 762 in
  Google Cloud charges on your personal card; that should probably move
  to your business card").
- **Tracks your plan** — actions with deadlines, priorities, categories.
  You mark them done as you go. The Coach pings about what's stuck.
- **Sends a morning brief** at 8am ("Foco do dia: pay the smaller card
  before the bigger one fires interest"), a weekly recap on Sunday
  evening, and a stuck-action nudge mid-week.
- **Replies via email work** — answer the morning brief from your phone
  and the Coach updates the plan. The reply threads back to the same
  conversation via subaddressing on the Reply-To.
- **Remembers across conversations** — long-term memory of facts you
  consolidated weeks ago. So when you say "the Santander fatura from
  May", it knows what you mean without you re-uploading.

## Why open source

Two reasons.

**One**, financial trouble is way more common than people admit, and
the apps in this space are either banking-aggregator surveillance
(your data → ad targeting) or sterile budget calculators that miss
the human part. Someone running their own Coach on their own infra
keeps their data private and gets a tool that's actually opinionated.

**Two**, I'm using this thing every day. Open-sourcing it forces me
to keep it in a state where someone else could actually clone and run
it — which is the bar I want for software I rely on.

## Stack

- **Laravel 13** + **PHP 8.4** for the backend
- **Filament v5** for the admin/chat UI (chat-first, no dashboard)
- **Laravel AI SDK** with **Gemini 2.5 Flash** as the model
- **Livewire 4 streaming** so the Coach types in real time
- **Resend** for outgoing email + inbound webhook
- **Pest** for tests (75 right now, including multi-tenant isolation)
- **SQLite** local, **MySQL** in production
- **Tailwind v4** + custom CSS for the conversation experience

The whole thing is self-hosted. There's no SaaS dependency beyond the
Gemini API key (free tier covers a single user) and Resend (free tier
covers a single domain).

## Multi-tenant from day one

When I rewrote for sharing, I went row-level: every action and every
memory belongs to a `user_id`, with global scopes ensuring cross-tenant
access is impossible. There's no public registration — admins invite
users from the avatar menu, and each invitee sets their own password
via a one-time link. Closed-loop.

## How to try it

```bash
git clone git@github.com:allurco/coach.git
cd coach
composer install && npm install && npm run build
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
```

That's it locally. The seeder prints a one-time admin password.
Open the app, log in, start typing about your situation. The Coach
goes into onboarding mode, interviews you, and starts building your
plan from scratch.

For production deploy on Forge or Cloud, the README walks through
DNS for Resend (the gotcha is *don't put receiving MX on a domain
already running Google Workspace*), Cloudflare SSL settings (use Full,
not Flexible — Flexible breaks session cookies), and the PHP 8.4
requirement.

---

If you build something on top of it, or fix a bug, PR away. The repo
is at [github.com/allurco/coach](https://github.com/allurco/coach).
MIT license.
