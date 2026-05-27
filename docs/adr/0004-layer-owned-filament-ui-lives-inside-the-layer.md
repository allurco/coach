# ADR 0004 — Layer-owned Filament UI lives inside the layer

- **Status:** Accepted
- **Date:** 2026-05-27

## Context

Coach uses [Filament](https://filamentphp.com/) for its admin panel.
Filament conventionally discovers Pages, Resources, and related
classes from `app/Filament/` via `discoverPages()`, `discoverResources()`,
etc., on the panel provider. Almost every Laravel/Filament app
follows that convention.

But Coach has three architectural layers — Core, Agent, and Domain
Packs (see [CONTEXT.md](../../CONTEXT.md) and ADRs
[0002](./0002-packs-publish-signals.md) /
[0003](./0003-agent-is-its-own-layer.md)). Each layer is intended
to be a vertical slice: a pack ships its models, lang, tools, AND
its UI; deleting the pack folder removes everything. The Agent is
analogous — it ships its own models, services, routes, and (now)
its chat page.

If layer-owned Filament UI lives in central `app/Filament/`, the
vertical-slice claim breaks. A fork that disables the Finance pack
or strips the Agent leaves orphan Pages/Resources/views at the
central path. Worse, a fork that drops the Agent still sees the
chat page in the panel, because Filament discovered it from the
central scan.

## Decision

**Layer-owned Filament UI lives inside the layer that owns it.**

- Agent-owned Filament classes and views live under `app/Agent/Filament/`
  and `app/Agent/resources/views/`.
- Pack-owned Filament classes and views live under
  `app/Domains/<Pack>/Filament/` and `app/Domains/<Pack>/resources/views/`.
- Central `app/Filament/` is reserved for **cross-cutting admin**:
  the panel provider, user/account management, anything that is
  genuinely not layer-owned.

Each layer registers its Filament discovery and view namespace
through its service provider:

- `AgentServiceProvider::boot()` adds `app/Agent/Filament/Pages` to
  the panel's page-discovery path (or registers pages explicitly),
  gated on `coach.agent.enabled`.
- `<Pack>ServiceProvider::boot()` does the same for its layer, with
  `loadViewsFrom()` registering the view namespace
  (`agent::*`, `finance::*`, etc.).

## Consequences

- Central `app/Filament/` shrinks to the panel provider and any
  genuinely cross-cutting admin (Users today). Most Filament code
  lives in the layer that owns its data.
- A fork that disables the Agent or a Pack also disables its Filament
  surface — no orphan Pages, no orphan view paths.
- Slightly non-idiomatic Laravel/Filament. A new contributor scanning
  `app/Filament/` and finding it nearly empty has to learn this rule
  before going looking elsewhere. This ADR is the answer when they
  ask why.
- Cross-layer Filament dependencies are unusual but possible (e.g. a
  pack's Filament Page composing a core trait). Treat those as
  refactoring targets, not the norm — see ADR
  [0002](./0002-packs-publish-signals.md) on packs not referencing
  each other.

## Alternatives considered

- **Keep all Filament in `app/Filament/`.** Idiomatic, but couples
  ownership location to convention. Defeats the deletion-test for
  pack/agent removal. Rejected: forkability is load-bearing and
  more important than convention conformance.
- **Per-page explicit registration without folder convention.** Each
  Page registered manually by its layer's SP, regardless of physical
  location. More flexible but inconsistent — readers can't predict
  where a Page lives from its identifier. Rejected: convention
  inside the layer is still preferable.
- **Use Filament's Cluster API.** Group pages into clusters under
  `app/Filament/Clusters/`. Filament-native grouping, but still puts
  everything inside central `app/Filament/` and forces specific
  Filament conventions for naming/discovery. Rejected: doesn't
  solve the vertical-slice problem; trades one form of central
  coupling for another.

## Why this matters

A future contributor reading `app/Filament/` and finding only
`AdminPanelProvider` plus a `Users` resource will reasonably propose
consolidating "the rest" back into central. This ADR exists so the
response is: the rest lives where its layer owns it; that's the
forkability story, not a missed cleanup.
