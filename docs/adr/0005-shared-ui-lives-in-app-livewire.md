# ADR 0005 — Shared UI between layers lives as a Livewire component in `app/Livewire/`

- **Status:** Accepted (amended by [ADR 0007](./0007-chat-centric-workspace-and-tool-contribution.md))
- **Date:** 2026-05-27

> **Amendment (2026-05-28, ADR 0007):** The original motivation below was
> that `PlanFlyout` is shared between **two host pages** — the Coach chat
> page and a standalone Plan tool page. ADR 0007 retires the standalone
> Plan page; `PlanFlyout` is now embedded in the single **Workspace**,
> reused across breakpoints (mobile tab view + desktop right rail) and
> wrapped as the `Plan` **Tool**. The `app/Livewire/` convention and the
> layer-neutral discipline still hold — only the "two host pages"
> justification is superseded.

## Context

[ADR 0004](./0004-layer-owned-filament-ui-lives-inside-the-layer.md) says
layer-owned Filament UI lives inside the layer (`app/Agent/Filament/`,
`app/Domains/<Pack>/Filament/`, `app/Filament/` for cross-cutting admin).
That covers single-owner UI cleanly.

But the layered architecture also produces **genuinely cross-layer UI**
— surfaces that more than one layer wants to render, where forcing
ownership into one of them is dishonest. The first concrete instance:

- The Coach **chat page** (Agent layer, `app/Agent/Filament/Pages/Coach.php`)
  shows a compact plan-flyout sidebar listing the user's Actions.
- The new **Plan tool** (Core, `app/Filament/Pages/Plan.php`) shows the
  same plan-flyout view at full width.

Both surfaces render the **same** Blade view, with the same complete-with-notes
modal, the same snooze-duration menu, the same status filters. They share
real UI, not just data.

We can't put this shared UI in Agent (Core would import across into the
Agent layer, contradicting ADR 0002's "no cross-pack-or-layer references"
spirit). We can't put it in Core's `app/Filament/` either, because then
the Agent's chat page reaches across to render it — same problem.
"Shared" needs its own home.

## Decision

**Cross-layer Livewire components live in `app/Livewire/`** with their
Blade in `resources/views/livewire/`. They are:

- **Self-contained** — own their state, methods, view; consumed via
  `<livewire:component-name :prop="..." />` tags.
- **Layer-neutral** — neither Agent nor Core nor any Pack owns them.
  They depend only on Core models (Goal, Action, User, Contact) so
  every layer can safely consume them.
- **Filament-aware but Filament-independent** — they render inside
  Filament Pages today, but a fork swapping Filament for something
  else can still embed them via Livewire's standard tag.

The first instance: `App\Livewire\PlanFlyout` — the action list with
filter pills, complete-with-notes modal, snooze menu. Used by the chat
page in the sidebar (`<livewire:plan-flyout :active-goal-id="$activeGoalId" />`)
and by the Plan tool page at full width (`<livewire:plan-flyout
:show-goal-picker="true" />`).

## Consequences

- `app/Livewire/` is reserved for **cross-layer reusable** UI only.
  Layer-private Livewire components stay inside the layer
  (`app/Agent/Livewire/`, `app/Domains/<Pack>/Livewire/`). This
  preserves the deletion-test: removing a layer removes its private
  components; cross-layer ones survive because they're not owned by
  any single layer.
- Cross-layer components depend ONLY on Core models. They never
  reference Agent or Pack-specific types. This is the same discipline
  as ADR 0002 — packs don't reference each other; cross-layer UI
  doesn't reference layer specifics.
- Each cross-layer component is a real architectural decision —
  "is this UI genuinely shared, or am I taking a shortcut?" Sharing
  prematurely couples two surfaces; building separately ages better
  if their needs diverge. Apply the same "1 adapter = hypothetical
  seam, 2 adapters = real seam" guideline: only extract here when
  there are two real consumers.
- Existing layer-private patterns (`app/Agent/Filament/Concerns/`
  traits, layer-local Blade partials) stay as they are. ADR 0005 is
  additive — it names a new home for a specific kind of UI, not a
  rewrite of where everything lives.

## Alternatives considered

- **Put `PlanFlyout` in `app/Filament/`** (central core Filament).
  Rejected: Agent's chat page would import across layers. Same
  ownership problem ADR 0004 was trying to avoid.

- **Put `PlanFlyout` in `app/Agent/`** (the layer that built it first).
  Rejected: Core's Plan page would import from Agent. Cross-layer
  dependency that contradicts the layered-architecture story.

- **Use a Filament-specific shared mechanism** (e.g., a shared
  Filament Action, custom view component). Rejected: locks the
  shared UI to Filament. A fork swapping Filament loses the reusable
  component too. Livewire components are framework-light enough to
  outlive a Filament dependency.

- **Duplicate the trait + Blade across both surfaces.** Rejected:
  goes against the user's explicit decision ("the design should
  remain as it is, on the flyout" — same design across both
  surfaces). Duplication means visual drift over time.

- **Use a service class for the logic + duplicate the Blade.**
  Rejected: shares only half the story. The Blade has interactions
  bound to the host (complete modal, snooze menu) — the visual
  fidelity ask covers both PHP and Blade together. A component
  encapsulates both.

## Why this matters

A future contributor adding a new tool will eventually want to embed
the plan-flyout (or some other cross-layer view) somewhere new. This
ADR exists so the answer is "use the `app/Livewire/` component" — not
"copy-paste the Blade" or "extract a new shared service and reinvent
the encapsulation." Naming the convention saves the same debate three
PRs from now.
