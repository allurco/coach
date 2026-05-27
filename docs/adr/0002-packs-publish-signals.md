# ADR 0002 — Packs publish signals; they do not reference each other directly

- **Status:** Accepted
- **Date:** 2026-05-27

## Context

Coach is a *holistic* coaching platform: "you can't help someone
with their health while ignoring their debt." The agent must reason
across all enabled packs when coaching any single goal — a finance
shortfall is relevant context for a health goal, and vice versa.

But forkability requires packs be independently removable. A fork
that ships only Finance must work without Health code present.

These two requirements pull in opposite directions: holistic reading
wants packs to know about each other; forkability wants them not to.

## Decision

**Packs publish read-only Signals; they do not reference each other.**

A Signal is a piece of pack-derived context (text or structured)
that the agent's prompt builder collects from every enabled pack on
every prompt. The Finance pack contributes a budget signal; the
Health pack contributes a sleep/workout signal; the agent
concatenates them into the system prompt regardless of which pack
owns the active goal.

Cross-pack synthesis ("your back pain may be stress from the budget
shortfall") happens in the **agent's reasoning** — the LLM is the
integrator — not in pack code. Packs are publishers, not
collaborators.

## Consequences

- A pack's only public surface is its service provider, its models
  (read by the core/agent as needed), and its signal contribution.
- Removing a pack is a clean operation: its signals stop appearing
  in prompts, no other pack breaks.
- Structured cross-pack computation (e.g. a literal `burnoutScore()`
  that fuses Finance + Health data) lives in the agent layer or a
  future synthesis-only layer, not in any pack.
- The product's holistic stance is enforceable in one place — the
  agent's prompt builder iterating over enabled packs — not spread
  across pack code.
- The forkability story stays credible: a fork with only Finance is
  fully functional; adding Health is purely additive.

## Alternatives considered

- **Direct pack-to-pack references.** Finance imports Health
  (or vice versa) for cross-cutting calculations. Rejected: tight
  coupling, breaks forkability, fragile when packs are toggled.
- **Hybrid — signals default, direct refs allowed.** Rejected:
  "opt-in direct" becomes the default once one developer realizes
  signals are limited for their use case; erodes the rule over time.
  Better to keep the wall solid and put cross-cutting smarts in
  the agent layer where they belong.

## Why this matters

A future reviewer adding a pack feature will be tempted to import
another pack's data directly — it's the obvious shortcut. This ADR
exists so they reach for the agent-layer escape hatch (or a
signal-only contract extension) instead of quietly creating the
cross-pack coupling we explicitly rejected.
