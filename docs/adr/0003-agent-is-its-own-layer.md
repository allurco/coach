# ADR 0003 — The Agent is its own layer, not a Domain Pack

- **Status:** Accepted
- **Date:** 2026-05-27

## Context

Coach is forkable open-source software. A fork must be able to ship
*without* an AI chat agent — Filament admin only, no LLM, no
conversation, no agent memory. So the agent has to be removable in
the same first-class way Domain Packs are.

But the agent isn't a Domain Pack as defined in [CONTEXT.md](../../CONTEXT.md).
A Pack is "a kind of life-thing the coach reasons about"
(Finance, Health, Career). The agent doesn't model a kind of life-
thing; it interprets all the life-things the packs publish. It also
reads every enabled pack — a behaviour that would violate ADR 0002
if the agent were itself a pack.

## Decision

The Agent lives in `app/Agent/` as its own physical layer, sibling
to `app/Domains/<Pack>/`. It is:

- **Optional** — removable via a config flag (`coach.agent.enabled`)
  plus deletion of the `app/Agent/` folder. The rest of Coach
  (Goals, Actions, Packs with their signals, Filament Resources)
  keeps working without it.
- **Singular** — a Coach fork has at most one agent. Agents are not
  additive like packs; you can't enable "Agent A + Agent B" the way
  you can enable "Finance + Health". A fork chooses one agent
  implementation or none.

Agent-owned concepts live under `app/Agent/`:

- The coaching persona / prompt voice
- The prompt builder (collects pack signals, composes the system prompt)
- The tool registry
- `AgentConversation` (the persisted thread per Goal)
- Agent memory (currently `CoachMemory` — free-form facts the agent
  stores about the user, pack-agnostic)
- The email-reply webhook + processor
- The chat UI surface (Filament's `Coach` page or its successor)

## Consequences

- The vocabulary stays clean: "Pack" = domain area, "Agent" =
  coaching interpreter. Neither term has to be overloaded.
- ADR 0002 ("packs don't reference each other") stays internally
  consistent — the agent reads every pack, but the agent isn't a
  pack, so the rule isn't violated.
- The chat-optional fork story is credible: there's a single folder
  to delete and a single config flag to flip.
- `app/Ai/` (current location of `CoachAgent`, tools) and the
  agent-owned models currently in `app/Models/` (`AgentConversation`,
  `CoachMemory`) move into `app/Agent/` in the upcoming refactor.

## Alternatives considered

- **Agent as a Pack (`app/Domains/Coach/`).** Symmetric and uniform
  but inverts ADR 0002 — the agent reads every other pack, which a
  pack isn't allowed to do. Would force one of: weakening ADR 0002,
  or making the Agent a "special pack" with different rules. Either
  way, vocabulary is muddled.
- **Agent stays in Core.** Smallest move, but makes the
  chat-optional story harder — removing the agent becomes surgical
  edits to core files rather than deleting a folder. Weakens the
  forkability claim that motivates the rest of the refactor.

## Why this matters

A future reviewer will reasonably suggest "for symmetry, make the
Agent a pack too." This ADR exists so the response is "we
considered that; the agent reads every pack so it can't be one
without breaking ADR 0002."
