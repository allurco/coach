# Coach — Context

This is the glossary for the Coach codebase. Terms here are canonical;
use them exactly. Implementation details do not belong in this file —
this is a dictionary, not a spec.

## Coach

The product. A holistic life-coaching platform that helps people unblock
themselves and achieve their goals. Open-source (`allurco/coach`).
Forkable. The agent/chat is one interface, not the product itself.

## Domain Pack

A pack represents **a kind of life-thing the coach reasons about** —
a coarse-grained area of someone's life. Examples (planned, not all
shipped): Finance, Health, Career, Relationships, Study.

A Domain Pack owns the data, signals, and pack-specific behaviour
for its area. The Coach core knows nothing about Finance; the Finance
pack contributes Finance to the Coach.

**Counter-examples — these are not packs:**

- "Budgeting" is not a pack. It's one capability *inside* the Finance
  pack. Packs are domain-sized, not capability-sized.
- "Money Coach" is not a pack. That's a *coach edition* — a possible
  later concept where a fork pre-composes packs + persona + theme.
  Editions are deferred; packs are the unit we're designing now.

Finance is the first Domain Pack. Its existence proves the pack
contract — until a second pack exists, the contract is hypothetical.

## Goal

A user-defined focus area — "Pay off the credit card", "Run a 10k",
"Get my CNPJ regularised". Every Action and AgentConversation attaches
to a Goal. A user always has at least one active Goal.

A Goal carries a **label** that identifies which Domain Pack owns it
(see `Goal::LABELS`). Values like `finance`, `health`, `legal`,
`learning` correspond 1:1 to Domain Packs. `general` is the safe
fallback when no pack claims the goal.

This means: **Goal.label = Pack identifier.** A Goal labelled
`finance` is owned by the Finance pack; the pack's tools, signals,
and copy are the ones available to that Goal.

## Action

A single concrete commitment the user makes in service of a Goal —
"Cancel the second credit card by Friday", "Lift weights Tuesday".
Actions belong to the core, not to any pack. Any pack can produce
Actions; the core owns the lifecycle (pending, in-progress,
completed, cancelled, snooze).

The legacy `Action::CATEGORIES` enum mixes pack-specific values
(`financial`, `tax`) into a central list — that's a leak, not a
feature. Packs should contribute their own sub-categories; the
core should not enumerate them.

## Signal

A read-only summary a Domain Pack publishes about the user, intended
to be consumed by the agent when building a prompt. Examples:
"net monthly delta: −R$ 1,200" (Finance), "slept under 6h for 3
consecutive nights" (Health), "filed 0 invoices this month" (Legal).

Signals are how packs **talk to each other through the agent** — the
agent collects every enabled pack's signals on every prompt, so when
coaching a health goal it still sees the financial signal, and vice
versa. This is the mechanism behind the product's holistic stance:
*you can't coach health while ignoring debt.*

**Packs do not reference each other directly.** Finance never imports
Health. Cross-pack synthesis ("the user's back pain may be stress
from the budget shortfall") happens in the agent's reasoning, not
in pack code. The integrating intelligence is the LLM; packs only
publish facts.

## Agent

The coaching interpreter. Reads Signals from every enabled Domain
Pack, builds prompts, runs the LLM, persists conversations, and
emits coaching to the user. Lives in its own layer (`app/Agent/`),
separate from Domain Packs.

The Agent is **optional but singular**: a fork either ships with an
agent or without one, and if with one, it's a single coherent
agent — not a stack of agents the way Packs stack.

Agent-owned concepts:
- The coaching persona (the prompt voice)
- The prompt builder (composes core context + every enabled Pack's
  signals into the system prompt)
- The Agent Tool registry (the Agent Tools the agent may call — see
  **Agent Tool**, distinct from **Tool**)
- AgentConversation (the persisted thread per Goal)
- Agent Memory (free-form facts the agent stores about the user,
  pack-agnostic — currently `CoachMemory`)

The Agent is what makes Coach holistic in practice. Packs publish
facts in isolation; the Agent is the only thing that sees the whole
person at once.

## Tool

A **workspace UI surface** the user opens — the Budget planner, the
Plan, the Contacts manager. Tools are what the Workspace's tab bar
(mobile) / right rail (desktop) shows, and what the **ToolRegistry**
lists. A Tool is either **core** (applies to every Goal — Plan,
Contacts) or **pack-contributed** (scoped to a Pack's Goals — Budget
belongs to Finance). Each Pack may designate one **primary** Tool: the
slot that sits next to Chat in the tab bar and changes with the active
Goal's pack (Finance → Budget, a future Fitness pack → a workout Tool).

Packs contribute Tools by self-registering into the core `ToolRegistry`
from their ServiceProvider, the same self-registration pattern as
Tips and commands (see [ADR 0006](docs/adr/0006-packs-self-register-into-core-catalogs.md)).
A Tool is a self-contained Livewire component, so it can be embedded
in the Workspace on mobile and desktop alike.

**Tool ≠ Agent Tool.** A Pack often ships both for the same area:
Finance contributes a *Budget* **Tool** (the visible planner) and a
*ReadBudget* **Agent Tool** (the invisible LLM function). They are
different artifacts that may share a topic.

"Tool Box" is **retired terminology** — it named the old standalone
Filament pages (Plan/Goals/Contacts) that the Workspace replaces.

## Agent Tool

An **LLM-callable function** the Agent invokes mid-conversation —
`ReadBudget`, `CreateAction`, `ShareViaEmail`. Invisible to the user;
the user sees only its effect. Registered in the Agent layer
(`CoachAgent`), distinct from the user-facing **Tool**.

## Workspace

The **per-Goal screen**: the chat plus that Goal's Tools. The user
reaches a Workspace by selecting a Goal from the Goals start screen.
On mobile the Workspace fills the screen and a bottom tab bar swaps
between Chat and the Goal's Tools (drill in; go back to switch Goals).
On desktop it's master–detail: a persistent Goals rail, the chat, and
a right rail that opens the selected Tool. Same screen, two layouts at
a breakpoint.

## Core

The universal, non-pluggable foundation. Owns the concepts every
Coach fork needs regardless of which Domain Packs are enabled or
whether the Agent is present:

- The User (identity, locale, multi-tenant scoping)
- The Goal (the focus area; pack identity via `Goal.label`)
- The Action (commitments toward a Goal)
- The Contact (people involved in the user's life)
- Locale infrastructure and the shared lang loader
- Authentication, invitations, the global owner-scope pattern

Core is **not** optional. A Coach fork without Core is not a Coach.
By contrast, Domain Packs and the Agent are both removable; the Core
is what's left when you remove them.

Core stays in idiomatic Laravel paths (`app/Models`, `app/Services`,
`app/Http`, `app/Providers`) — it's not in an `app/Core/` folder.
The path-as-layer convention only applies to extracted layers
(packs, agent); core inherits Laravel's defaults.
