# RFC 0002 — Coach as a self-hostable, extensible coaching app

- **Status**: Proposed
- **Author**: rogerssampaio (with Claude pair)
- **Created**: 2026-05-13
- **Supersedes**: —
- **Frames**: RFC 0001 (prompt pipeline is one of seven extension points
  this RFC commits to)

## 1. Summary

Coach is positioned as a **self-hostable Laravel app with plug-and-play
hooks** — not a Composer package. Users clone the repo, configure, and
deploy. Builders who want to customize do so through documented extension
points (config-driven registries, Laravel events, Service Provider boot
hooks) without forking the core.

To make future package extraction cheap (if and when demand justifies it),
the codebase follows package-shaped conventions: an `App\Coach\*` namespace
for extensible code, public contracts in `App\Coach\Contracts\*`, a
`CoachServiceProvider` that owns boot/register, and publishable
config/lang/views/migrations/locale-knowledge via standard Laravel tags.

The boundary today is **directory namespace + naming convention**, not
Composer package boundary. The boundary is reviewable and enforceable;
the extraction step can happen later as a mechanical refactor.

## 2. Status quo & motivation

Coach reached i18n and locale-knowledge plug-and-play (PR #48–#51) without
ever deciding the meta-question: *is this an app or a framework?* That
ambiguity surfaces every time we touch a registry-shaped thing —
`CoachAgent::tools()` is a hardcoded array, goal labels live as a class
const, tone personas are a `match()` inside the agent. Each one is a future
extension point that we keep building "inline" because we haven't committed
to a shape.

The core has not been locked: no public release, no semver, no published
contracts. This is a one-time window to design the extension surface
correctly before usage locks decisions in.

## 3. Decision

### 3.1 The form

**Coach is a Laravel application, opensource and self-hostable.** Clone the
repo, configure `.env`, run migrations, deploy. The deliverable is a
running coaching system, not a library.

We **do NOT** extract Coach into a Composer package now. Package
extraction is deferred until concrete demand from a builder who needs to
embed Coach into an existing Laravel app.

### 3.2 The architecture

While shipping as an app, the codebase follows **package-shaped
conventions** so future extraction is mechanical, not a rewrite:

- `app/Coach/*` namespace: all reusable, contract-bearing code
- `app/Coach/Contracts/*`: public interfaces (semver-tracked)
- `app/Coach/PromptStages/*`, `app/Coach/Tools/Built/*`,
  `app/Coach/Tips/Built/*`, etc.: built-in implementations
- `App\Providers\CoachServiceProvider`: owns register + boot, all bindings
- `config/coach.php`: single config file with all extension registries
- Publishable resources tagged: `coach-config`, `coach-lang`, `coach-views`,
  `coach-migrations`, `coach-locales`
- `app/Coach/Events/*`: domain events (semver-tracked payloads)

The current code that doesn't follow this layout (e.g. `app/Ai/Agents/CoachAgent.php`,
`app/Ai/Tools/*`) migrates incrementally — RFC 0001 starts this migration
for prompts. Subsequent RFCs cover tools, tips, goal labels, etc.

### 3.3 The audience

| Audience | Today's priority | What they get |
|---|---|---|
| Solo / small team self-hosting | **Primary** | Turnkey deploy, sane defaults, easy locale + customization |
| Builders building on top | Secondary | Extension points that work without forking |
| Saas operators reselling Coach | Future | Package extraction when ready |

Self-hosters dominate. Builders are served by the hooks but aren't the
target user — we don't over-engineer for them.

## 4. Committed extension points (v1 surface)

The seven points below become **stable public API at v1.0**. Each
point gets its own implementation RFC and PR. Until v1.0, they're
documented as `@experimental` and may change.

### Tier 1 — primary extension surface

#### 4.1 Tools
**Contract**: `Laravel\Ai\Contracts\Tool` (already exists in `laravel/ai`).
**Registry**: `config('coach.tools')` — array of class names. CoachAgent
reads this at construction.
**Plugin use case**: Add `NotionSearch`, `LinearCreateIssue`,
`StripeRecentCharges` — agent picks them up automatically.

#### 4.2 Prompt stages
See **RFC 0001** for the full design. Pipeline of `PromptStage` classes
registered in `config('coach.prompt.stages.{mode}')` + extensions in
`config('coach.prompt.extensions')`.

#### 4.3 Domain events
**Initial set** (4-5 most-used lifecycle moments):
- `App\Coach\Events\ActionCreated`
- `App\Coach\Events\ActionCompleted`
- `App\Coach\Events\BudgetSnapshotCreated`
- `App\Coach\Events\GoalCreated`
- `App\Coach\Events\ConversationStarted`

Payload shape is the contract. Renaming fields = breaking change. Adding
fields = additive (backwards-compatible).

**Plugin use case**: Listen for `ActionCompleted` → log to analytics,
send Slack notification, update streak counter.

#### 4.4 Mail templates publishable
`php artisan vendor:publish --tag=coach-views` exports `resources/views/emails/*`.
Plugin overrides any template by re-publishing or via view path priority.

**Plugin use case**: White-label the CoachPing / BudgetReminder emails.

### Tier 2 — completes the registry pattern

#### 4.5 Goal labels + specializations
Move `Goal::LABELS` const to `config('coach.goals.labels')`. Specialization
text in lang files keyed by label. Plugin adds new labels with their own
specialization prompts.

**Plugin use case**: Add `writing`, `cooking`, `language-learning` labels
with custom prompts.

#### 4.6 Tone persona registry
Refactor `tonePersona()` from internal `match()` to a registry indexed by
locale. Plugin registers a new tone for a new locale.

**Plugin use case**: Add `es_ES` voice with Spanish examples; pairs with
`resources/prompts/locale/es_ES.md`.

#### 4.7 Tip resolvers
Tips already use a class hierarchy. Expose the registry to
`config('coach.tips')`. Plugin adds custom tip classes.

**Plugin use case**: Org-specific nudge — "you missed yesterday's standup;
log it now?".

## 5. Architectural conventions ("as if package")

### 5.1 Namespace boundary

```
app/
├── Coach/                          ← reusable, contract-bearing
│   ├── Contracts/                  ← public interfaces (semver-locked at v1)
│   │   ├── PromptStage.php
│   │   ├── Tip.php
│   │   └── ...
│   ├── PromptStages/               ← built-in stage implementations
│   ├── Tips/                       ← built-in tip implementations
│   ├── Events/                     ← public domain events
│   ├── PromptBuilder.php
│   └── ...
├── Ai/                             ← bridges to laravel/ai SDK
│   ├── Agents/CoachAgent.php       ← uses App\Coach\* contracts internally
│   └── Tools/                      ← Tool implementations (could move to Coach/Tools/Built/)
├── Filament/                       ← Filament UI; not part of public API yet
├── Models/                         ← schemas (DB-locked at v1)
├── Services/                       ← internal services (not in public API)
└── Providers/
    └── CoachServiceProvider.php    ← owns register + boot for all of App\Coach\*
```

### 5.2 Service Provider

`CoachServiceProvider` consolidates all bindings:

```php
public function register(): void
{
    $this->mergeConfigFrom(__DIR__.'/../../config/coach.php', 'coach');
}

public function boot(): void
{
    $this->publishes([
        __DIR__.'/../../config/coach.php' => config_path('coach.php'),
    ], 'coach-config');

    $this->publishes([
        __DIR__.'/../../resources/views/emails' => resource_path('views/vendor/coach/emails'),
    ], 'coach-views');

    $this->publishes([
        __DIR__.'/../../lang' => lang_path('vendor/coach'),
    ], 'coach-lang');

    $this->publishes([
        __DIR__.'/../../resources/prompts/locale' => resource_path('prompts/locale'),
    ], 'coach-locales');

    $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
}
```

The provider lives in `app/Providers/CoachServiceProvider.php` today. When
extracted to a package, it moves to `src/CoachServiceProvider.php` of the
package with minimal changes.

### 5.3 Public contracts

Every extension point has a **contract** in `App\Coach\Contracts\*`:

```php
namespace App\Coach\Contracts;

interface PromptStage
{
    public function handle(PromptBuilder $builder, \Closure $next): PromptBuilder;
}

interface Tip
{
    public function id(): string;
    public function priority(): int;
    public function applies(User $user, ?Goal $goal): bool;
    public function title(): string;
    public function prompt(): string;
}

// etc.
```

Built-in implementations live alongside but implement the contract.
Plugins implement the contract directly.

### 5.4 Auto-discovery for plugin packages

Plugin packages declare their service provider via Laravel's standard
package discovery (`composer.json` → `extra.laravel.providers`). The
plugin's provider's `boot()` appends to Coach's config:

```php
// In a plugin package
public function boot(): void
{
    config()->set('coach.tools', array_merge(
        config('coach.tools', []),
        [NotionSearch::class, NotionAddPage::class],
    ));

    Event::listen(ActionCompleted::class, [LogToAnalytics::class, 'handle']);
}
```

No `vendor:publish` required for runtime registration. Publishing is only
needed for view/config overrides.

## 6. Versioning & stability

| Phase | Trigger | Stability promise |
|---|---|---|
| 0.x (current) | All work to date | Experimental. Any contract may change. |
| 0.x → 1.0-rc | All 7 extension points implemented + `docs/extending.md` written + skeleton plugin repo exists | Contracts frozen for review. Feedback period. |
| 1.0 | After 2-4 weeks of rc + at least one real external plugin shipping | Contracts locked. Breaking changes in `App\Coach\Contracts\*` require major bump. |
| 1.x | Additive changes only | New extension points OK. New event fields OK (must be optional). New built-in stages OK. |

**Breaking change in 1.x = bug**. Either re-do it correctly or wait for 2.0.

## 7. Core / not extensible by design

Some things stay internal because they're load-bearing for safety or
correctness:

- **Multi-tenant scope** (global scope `owner` on Action, Budget, Goal,
  CoachMemory, AgentConversation). Plugins cannot disable or bypass.
- **Action / Budget / Goal / User / CoachMemory schemas**. Changes go
  through migrations, not plugin overrides.
- **Auth flow** (invite-only). Plugins cannot expose public registration
  without explicit user opt-in (TBD how).
- **`laravel/ai` contracts** (`Promptable`, `Tool`, conversation store).
  These are external; we don't own their stability.
- **Webhook signature verification** (`COACH_WEBHOOK_SECRET`). Plugins
  cannot bypass; they can listen to inbound events after verification.

## 8. Plugin author DX

### 8.1 Documentation surfaces

- `docs/extending.md` — main entry, examples for each extension point
- `docs/rfcs/` — design decisions (this RFC + per-extension-point RFCs)
- `docs/locales.md` — locale knowledge contribution guide (exists informally
  in README; extract here)
- API reference (auto-generated from PHPDoc? TBD)

### 8.2 Skeleton plugin repo

Maintain a `coach-plugin-skeleton` repository (separate from Coach core)
showing:
- `composer.json` with `extra.laravel.providers` auto-discovery
- Example ServiceProvider boot() registering a tool, listening to an event,
  appending a prompt stage
- Tests against Coach's contracts
- README explaining the conventions

Plugin authors `git clone coach-plugin-skeleton my-plugin && rm -rf .git`
to bootstrap.

### 8.3 Versioning expectations

Plugins declare compatibility via Composer constraints:

```json
{
    "require": {
        "allurco/coach-app": "^1.0"
    }
}
```

Plugin authors test against multiple Coach versions in CI.

## 9. Open questions

1. **Where does CoachServiceProvider live today?**
   `app/Providers/` is the conventional Laravel app location and is fine
   for now. When extracted to a package, it moves with the rest.

2. **Do we expose `replaceSection()`** for prompt stages (carryover from
   RFC 0001)? Recommend yes for v1, with named sections so plugins can
   target specifically. Tradeoff: tighter coupling between plugin and
   built-in stage names.

3. **Do plugins get write access to `config('coach.*')` at runtime**, or
   should there be a `Coach::registerTool()`-style facade? Recommend
   config-array approach for now — simpler, no new API surface. Facade
   can come later if config-array gets awkward.

4. **Migrations from plugins**: do plugins ship their own migrations
   (for plugin-specific tables)? Recommend yes — standard Laravel
   package practice. Document the conventions.

5. **What happens when two plugins register conflicting things** (e.g.
   two tools with the same name)? Recommend: error at boot, ask user to
   resolve. Fail loud.

6. **Package extraction trigger**: what concrete signal makes us extract
   to a Composer package? Suggest: 3+ external plugins shipping, OR a
   user requesting embed-in-existing-app. Until then, app stays
   monolithic.

## 10. Implementation phases

Each phase = one or more PRs. RFCs for individual extension points
reference this RFC as their parent.

### Phase 0 — foundation (this RFC + RFC 0001)

- This RFC adopted
- RFC 0001 (prompt pipeline) accepted
- `App\Coach\*` namespace created
- `CoachServiceProvider` consolidated (currently bindings live ad-hoc)
- `config/coach.php` reviewed and extended where needed

### Phase 1 — implement Tier 1

- **Tools**: refactor `CoachAgent::tools()` to read from `config('coach.tools')`
- **Prompt stages**: implement RFC 0001
- **Domain events**: ship the initial 4-5 events; convert one existing
  behavior (e.g. tip resolver) to listen instead of poll
- **Mail publishing**: add publish tags

### Phase 2 — implement Tier 2

- **Goal labels**: config-driven registry
- **Tone persona**: registry per locale
- **Tip resolvers**: config registry

### Phase 3 — DX & stabilization

- `docs/extending.md`
- Skeleton plugin repo (`coach-plugin-skeleton`)
- One real external plugin (could be simple — e.g. "Slack notification on
  ActionCompleted")
- 0.x → 1.0-rc → 1.0

## 11. Drawbacks

1. **Designing for a future that may not come.** If Coach never gets
   external plugins, the extension surface is dead weight. Mitigation:
   the conventions (namespace, config-driven) are useful even if zero
   plugins exist — they make the codebase more testable.

2. **Maintenance burden grows with each extension point.** Each becomes a
   compat promise. Mitigation: 7 points is the cap. Anything beyond gets
   its own RFC and explicit cost/benefit decision.

3. **Architectural conventions can be ignored over time.** A future
   developer adds a hardcoded array somewhere and the boundary erodes.
   Mitigation: PR review checklist + a `phpstan` rule that flags
   `App\Coach\*` violations (future work).

4. **0.x → 1.0 timeline is uncertain.** We don't know when "skeleton
   plugin + docs + real external plugin" lands. Could be weeks, could
   be months. Mitigation: ship 0.x publicly as soon as Phase 1 lands;
   gather feedback; iterate.

## 12. Decision

Pending discussion on open questions in §9. Implementation does NOT begin
until §9.1–§9.6 are resolved (even if "defer to later RFC").
