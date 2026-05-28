# ADR 0007 — Chat-centric Workspace; Tools are pack-contributed Livewire components

- **Status:** Accepted
- **Date:** 2026-05-28

## Context

Phase 8 shipped a "Tool Box" navigation group — standalone Filament
pages for Plan, Goals, and Contacts (`app/Filament/Pages/`). In use this
felt wrong: the chat is the product's center, yet the most-used surfaces
lived on separate routes duplicating what the chat sidebar/flyouts
already showed. The product owner's model is sharper:

- **Chat is king.** Everything happens inside a per-Goal screen.
- **Goals is the start screen.** You pick a Goal, drill into it, and go
  back out to switch Goals.
- **Tools are reached from the chat**, not from a separate nav — a
  bottom tab bar on mobile, a right rail on desktop.
- The tool next to Chat **changes with the Goal's pack** (Finance →
  Budget; a future Fitness pack → a workout Tool).

This was validated with a throwaway clickable prototype before any code
(see the mobile-nav redesign). The terminology was also sharpened: see
`CONTEXT.md` — **Tool** (visible workspace surface) vs **Agent Tool**
(invisible LLM function), and **Workspace** (the per-Goal screen). "Tool
Box" is retired.

Multiple Domain Packs are planned shortly, so the mechanism that decides
"which Tools does this Goal have" must be pack-extensible, not hardcoded.

## Decision

**1. One state-driven Workspace, not a page per screen.**
A single Filament page renders the **Goals start screen** when no Goal is
active and the **Workspace** when one is. Screen and Tool switching is
client-side state (Livewire properties + Alpine for the slide
transitions), synced to `?goal=&tool=` via Livewire `#[Url]` so deep
links and the back button work. The three standalone Tool Box pages are
**retired**; their behaviour moves into Tools.

**2. Responsive: desktop master–detail, mobile drill-in.**
Desktop = persistent Goals rail + chat + right Tool rail. Mobile = Goals
start screen → Workspace with a bottom tab bar; the rail collapses to a
full-screen Tool swap. One page, switched by a Tailwind breakpoint.

**3. Every Tool is a self-contained Livewire component.**
`Plan` (the existing `App\Livewire\PlanFlyout`), `Contacts` (extracted
from its retired page), and `Budget` (extracted out of the Finance
`HasBudgetFlyout`/`HasBudgetShare` traits into a pack-owned `BudgetTool`
component — which also removes the Agent page's dependency on those
Finance traits). The Workspace embeds them with `<livewire:…/>`.

**4. Tools are contributed via a core `ToolRegistry`, pack self-registration.**
A core `ToolRegistry` holds Tool descriptors `{key, label, heroicon,
component, isPrimary, scope}`. Core self-registers Plan + Contacts; the
Finance pack self-registers Budget (primary, finance-scoped) by extending
the registry in its ServiceProvider — the same pattern as
[ADR 0006](./0006-packs-self-register-into-core-catalogs.md). The
Workspace renders its tabs from `coreTools + tools(activeGoal.label)`,
so it stays pack-agnostic and a new pack's Tools appear without editing
the Workspace. This is the UI twin of the Agent Tool contribution work.

## Consequences

- The chat page grows into the app shell, but Tools are separate
  components and screens are Blade partials, so it stays navigable.
- Old `/plan`, `/goals`, `/contacts` routes go away (redirect to the
  Workspace with the matching `?tool=`).
- The deferred Candidate-1 trait leak (Agent page `use`-ing Finance
  traits) is closed as a side effect of extracting `BudgetTool`.
- Delivered in four mergeable slices: (1) ToolRegistry + Tool
  components, (2) Workspace shell + retire Tool Box, (3) tab bar /
  rails + motion, (4) chat polish + dark.
- Amends [ADR 0005](./0005-shared-ui-lives-in-app-livewire.md): the
  `app/Livewire/` convention stands, but its justification shifts from
  "two host pages" to "one Workspace embedding the component across
  breakpoints."

## Alternatives considered

- **A page per screen** (Goals page + Workspace page): more
  Filament-native routing, but slide transitions across full page loads
  are awkward and the SPA feel is lost.
- **Hardcode finance→Budget for v1**, add the registry later: ships the
  shell faster but bakes in the Core→pack hardcode we have been removing,
  and would be revisited the moment a second pack lands.
- **Keep the Tool Box pages, add a tab bar**: rejected — contradicts the
  chat-king model and leaves two ways to reach the same surfaces.
