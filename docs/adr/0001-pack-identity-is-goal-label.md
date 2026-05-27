# ADR 0001 — Pack identity is `Goal.label`

- **Status:** Accepted
- **Date:** 2026-05-27

## Context

Coach is reorganizing from a single-app structure into a Core plus
pluggable Domain Packs (Finance, Health, Career, Legal, …). Each
Pack needs a stable identifier so the system can filter goals/data
by pack, route AI tools to the right pack, and register a pack's
signals against the right scope.

The codebase already has `Goal::LABELS` — a const enum with values
`general`, `finance`, `legal`, `emotional`, `health`, `fitness`,
`learning`. These are exactly the concepts we'd otherwise call
Domain Packs.

## Decision

`Goal.label` values **are** the canonical Pack identifiers. A pack
registers itself under a label string (e.g. the Finance pack
declares `label() === 'finance'`). A goal labelled `finance` belongs
to the Finance pack; the pack's tools, signals, and copy are the
ones available to that goal.

## Consequences

- Pack registration must declare a label string. The `Goal::LABELS`
  const is lifted out of the `Goal` model into a `PackRegistry`,
  assembled at boot from enabled packs plus the built-in `general`
  fallback.
- Removing a pack means goals previously labelled with its label
  must fall back to `general` (or be migrated explicitly). The pack
  registration flow owns that semantic.
- `Action::CATEGORIES` values that overlap with pack identity
  (`financial`) become redundant — packs own their own
  action sub-categories, and the central enum is shrunk to its
  truly cross-cutting members.
- One Goal belongs to one pack (no multi-pack goals via this
  identifier). Multi-domain situations are handled by having
  multiple goals, each labelled.

## Alternatives considered

- **Separate `pack_id` field on Goal.** Rejected: introduces
  redundancy with `label`; users would maintain both; double the
  migration burden whenever a pack is renamed.
- **`Goal.label` stays decorative; pack identity lives elsewhere.**
  Rejected: two parallel concepts mean future confusion about which
  is authoritative.
- **Defer the relationship.** Rejected: the ambiguity costs more
  every time it has to be re-explained; the next architecture
  review will re-suggest the same question.

## Why this matters

A future reviewer reading the code cold will see a `label` field on
`Goal` and a `PackRegistry` and wonder which is authoritative. This
ADR exists so they don't propose adding a separate `pack_id` field
and quietly create the parallel concept we explicitly rejected.
