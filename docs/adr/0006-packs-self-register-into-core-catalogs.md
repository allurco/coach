# ADR 0006 — Domain Packs self-register into Core catalogs

- **Status:** Accepted
- **Date:** 2026-05-28

## Context

The Phase 0–9 refactor split Coach into Core, Domain Packs, and the
Agent. [ADR 0002](./0002-packs-publish-signals.md) gave packs exactly
one way to reach the rest of the app: `contributeSignal()`. Everything
else a pack needs to contribute — tools, in-screen Tips, scheduled
commands, placeholders, Goal labels — had no contract, so Finance-owned
pieces were left sitting in Core:

- `App\Tips\SetUpBudget` and `App\Tips\RefreshBudget` lived in Core's
  `app/Tips/` and were registered in a hardcoded array in
  `AppServiceProvider`, importing `App\Domains\Finance\Models\Budget`.
- `CoachCarryBudgetForward` and `CoachMonthlyBudgetReminder` lived in
  Core's `app/Console/Commands/` and were scheduled from Core's
  `routes/console.php`.

Both are **Core → Pack import leaks**: a fork that disables the Finance
pack fatal-errors on boot. The architecture review surfaced this as the
one present-day defect (the rest of the "leaks bag" is latent until a
pack pressures it). Multiple packs are planned in the short term, so the
contribution pattern every future pack copies needs to be settled now.

Two shapes were on the table:

1. **Formal contract methods** — add `DomainPack::tips(): array`,
   `DomainPack::commands(): array`, etc. Core code iterates enabled
   packs and pulls each contribution.
2. **Self-registration** — a pack's ServiceProvider reaches out in
   `register()`/`boot()` and registers itself into the Core catalog
   using ordinary Laravel container/scheduler APIs.

## Decision

**Domain Packs self-register into Core catalogs from their
ServiceProvider; Core never enumerates pack contributions.**

Concretely, for the contributions covered here:

- **Tips** — Core's `TipResolver` exposes a generic
  `register(Tip ...$tips): self` append method. The pack appends its
  Tips via `$this->app->extend(TipResolver::class, …)` in `boot()`.
  Core's `AppServiceProvider` registers only Core's own Tips and imports
  no pack class.
- **Commands** — the pack registers its commands with
  `$this->commands([...])` and defines their schedule with
  `$this->callAfterResolving(Schedule::class, …)` in `boot()`. The
  command schedule leaves Core's `routes/console.php` entirely.

Core catalog surfaces (`TipResolver::register`, the container, the
scheduler) stay **pack-agnostic** — they expose "you may append," not
"here is where Finance goes." The pack is the only side that names
itself.

We explicitly rejected **formal contract methods** (option 1). With many
packs incoming we do not yet know the union of things packs will
contribute; freezing `tips()`/`commands()`/… onto the `DomainPack` base
now would lock the contract before its shape is known, and each new
contribution kind would force a base-class change every pack must absorb.
Self-registration lets a pack contribute a *new kind* of thing (a future
placeholder registry, a tool registry) by calling that subsystem's own
registration API, without widening the `DomainPack` base. The trade-off:
contributions are discovered by reading pack ServiceProviders rather than
one interface — acceptable, and mirrored by how Laravel packages already
self-register.

## Consequences

- A new pack follows one pattern: register yourself in your
  ServiceProvider; never edit Core to be "told about" you.
- Core catalogs need a generic append seam (e.g. `TipResolver::register`)
  but no knowledge of any pack.
- Disabling a pack (removing it from `config('coach.enabled_packs')`)
  removes its Tips and scheduled commands with it — no Core edit, no
  dangling import.
- A narrow architecture test (`App\Tips`, `App\Console\Commands` must not
  use `App\Domains`) guards against regression and will widen toward a
  blanket Core/Agent → Pack ban as the remaining leaks close.
- This ADR sets the precedent the rest of the leaks bag (placeholder
  registry, tool registry, Goal labels, verbatim-tool marker) should
  follow: a pack-agnostic append seam in Core/Agent, the pack
  self-registering against it.
