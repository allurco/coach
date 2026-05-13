# RFC 0001 — Prompt assembly pipeline & extensibility hooks

- **Status**: Proposed
- **Author**: rogerssampaio (with Claude pair)
- **Created**: 2026-05-13
- **Supersedes**: —
- **Related**: PR #48 (i18n + locale knowledge), PR #51 (locale attachment template)

## 1. Summary

Refactor `CoachAgent::instructions()` from a single 250-line heredoc into a
Laravel **Pipeline** of small, single-purpose stages. Add a config-driven
extension point (`config('coach.prompt.extensions')`) so contributors can
register custom prompt stages without forking `CoachAgent.php`. Add two
domain events (`BuildingPrompt`, `PromptBuilt`) for observability and
mutation hooks.

This is the next layer of the open-source-readiness work that started with
i18n. Locale is plug-and-play via markdown files; this makes **behavior**
plug-and-play via PHP classes.

## 2. Motivation

Three real pain points today:

1. **`instructions()` is a god method.** Hard to read, hard to test parts in
   isolation, hard to extend. Any new section means editing the heredoc and
   re-running all prompt-assertion tests.

2. **No extension point for downstream apps.** A team that wants to add
   org-specific rules ("our company uses NetSuite for accounting") has no
   place to inject text into the prompt. They'd fork the class.

3. **No lifecycle hooks for the agent's work.** When the user completes an
   action, that's a moment worth reacting to (analytics, streak counters,
   tip resolvers, notifications). Today the completion happens inside a
   trait with no way for downstream code to hook in.

## 3. Current state

```php
// app/Ai/Agents/CoachAgent.php (~745 lines)
public function instructions(): Stringable|string
{
    $stats = $this->planStats();
    $recentMemories = $this->recentMemoriesSummary();
    $userContext = $this->userContext();
    $goalContext = $this->goalContext();
    $lifeContext = $this->lifeContext();
    $today = $this->todayContext();
    $tonePersona = $this->tonePersona();
    $localeKnowledge = $this->renderLocaleKnowledgeSection();
    $isOnboarding = Action::count() === 0;

    if ($isOnboarding) {
        return $this->onboardingInstructions(...);
    }

    return <<<PROMPT
        You are the user's personal coach — not an app, a person.

        ## Today
        $today

        ## Personality
        - Direct and firm, but a friend
        ...

        // ~200 more lines of heredoc
    PROMPT;
}
```

Sections are concatenated by string interpolation. No way to insert,
remove, or reorder from outside the class. Onboarding mode has its own
parallel heredoc.

## 4. Proposed design

### 4.1 Pipeline

```php
public function instructions(): Stringable|string
{
    $mode = Action::count() === 0 ? 'onboarding' : 'standard';

    $builder = (new PromptBuilder($mode))
        ->withActiveGoalId($this->activeGoalId)
        ->withConversationId($this->conversationId);

    event(new BuildingPrompt($builder)); // listeners can mutate

    $stages = array_merge(
        config("coach.prompt.stages.{$mode}", []),
        config('coach.prompt.extensions', []),
    );

    $prompt = Pipeline::send($builder)
        ->through($stages)
        ->thenReturn()
        ->toString();

    event(new PromptBuilt($builder, $prompt));

    return $prompt;
}
```

### 4.2 PromptBuilder DTO

```php
final class PromptBuilder
{
    public string $mode;
    public ?int $activeGoalId = null;
    public ?string $conversationId = null;

    /** @var array<int, string> ordered prompt sections, joined with "\n\n" at the end */
    public array $sections = [];

    public function append(string $section): self { ... }
    public function prepend(string $section): self { ... }
    public function replaceSection(string $key, string $section): self { ... }
    public function toString(): string { ... }
}
```

Stages mutate `$builder` and return it. Mutation is explicit (`append`,
`replaceSection`) — no surprise side effects.

### 4.3 Stage interface

```php
interface PromptStage
{
    public function handle(PromptBuilder $builder, \Closure $next): PromptBuilder;
}
```

Each built-in stage is a single-purpose class:

```php
app/Coach/PromptStages/
├── Header.php          ← "You are the user's personal coach..."
├── Today.php
├── Personality.php
├── TonePersona.php
├── LocaleKnowledge.php
├── LifeContext.php
├── GoalContext.php
├── UserContext.php
├── PlanStats.php
├── RecentMemories.php
├── MemoryArchitecture.php
├── HardRule.php
├── ToolsDoc.php
├── Behavior.php
├── InviolableRules.php
└── CriticalRule.php
```

Each is testable in isolation: `(new Today)->handle($builder, fn ($b) => $b)`.

### 4.4 Config

```php
// config/coach.php
return [
    'prompt' => [
        'stages' => [
            'standard' => [
                \App\Coach\PromptStages\Header::class,
                \App\Coach\PromptStages\Today::class,
                \App\Coach\PromptStages\Personality::class,
                \App\Coach\PromptStages\TonePersona::class,
                \App\Coach\PromptStages\LocaleKnowledge::class,
                // ... rest of the standard stages, in order
            ],
            'onboarding' => [
                \App\Coach\PromptStages\Header::class,
                \App\Coach\PromptStages\Today::class,
                \App\Coach\PromptStages\OnboardingStage::class,
                // ... onboarding-specific stages
            ],
        ],
        'extensions' => [
            // Plugin / app-specific stages added by downstream code.
            // Auto-discovered via package service providers OR added here
            // manually. Run AFTER the built-in stages by default; use
            // PromptBuilder::prepend() / replaceSection() to insert
            // elsewhere.
        ],
    ],
];
```

### 4.5 Events

```php
namespace App\Events;

/** Fired BEFORE the pipeline runs. Listeners can mutate the builder. */
class BuildingPrompt
{
    public function __construct(public PromptBuilder $builder) {}
}

/** Fired AFTER the pipeline runs. Read-only — for logging/telemetry. */
class PromptBuilt
{
    public function __construct(
        public PromptBuilder $builder,
        public string $finalPrompt,
    ) {}
}
```

`BuildingPrompt` gives plugins one more lever beyond pipeline stages — useful
when ordering inside the pipeline doesn't matter and a simple "add this
fact" hook is enough.

### 4.6 Domain event (calibration)

Pick **one** lifecycle event to validate the pattern. Recommendation:
`ActionCompleted`. Used by:

- Tip resolver (currently runs on every render — could be event-driven)
- Streak counter (future)
- Analytics (future)
- Notifications (future)

```php
namespace App\Events;

class ActionCompleted
{
    public function __construct(
        public Action $action,
        public ?string $resultNotes = null,
    ) {}
}
```

Fired from `HasPlanFlyout::confirmCompleteAction()` after the DB update.

## 5. Public API for plugin authors

A plugin that adds an org-specific section:

```php
// In a Composer package published as "acme/coach-netsuite-plugin"

namespace Acme\CoachNetsuite\PromptStages;

use App\Coach\Contracts\PromptStage;
use App\Coach\PromptBuilder;

class NetsuiteContext implements PromptStage
{
    public function handle(PromptBuilder $builder, \Closure $next): PromptBuilder
    {
        $section = <<<EOS
            ## Accounting tooling (Acme org)

            This user's company uses NetSuite. When suggesting financial
            actions, prefer NetSuite-compatible workflows.
            EOS;

        $builder->append($section);

        return $next($builder);
    }
}

// In the plugin's ServiceProvider::boot()
config(['coach.prompt.extensions' => array_merge(
    config('coach.prompt.extensions', []),
    [NetsuiteContext::class],
)]);
```

Or for a simpler reactive hook:

```php
// Plugin's EventServiceProvider
Event::listen(BuildingPrompt::class, function ($event) {
    if (auth()->user()?->isAcmeEmployee()) {
        $event->builder->append('## Acme employee context\n...');
    }
});
```

## 6. Migration plan

### Phase 1 — Refactor without behavioral change (1 PR)

- Create `app/Coach/PromptBuilder.php` and `app/Coach/Contracts/PromptStage.php`.
- Create `app/Coach/PromptStages/*` — extract each section from the current
  heredoc into a stage class. **Output must be byte-identical** to the
  current `instructions()`.
- Add `config/coach.php` with stage arrays.
- Refactor `CoachAgent::instructions()` to use Pipeline.
- Add snapshot test: capture the current `instructions()` output, assert
  the new pipeline produces the same string for fixed contexts.
- Existing tests should pass unchanged.

### Phase 2 — Events + extensions config (1 PR)

- Add `BuildingPrompt` and `PromptBuilt` events.
- Wire `config('coach.prompt.extensions')` into the pipeline.
- Add `ActionCompleted` event + fire from `HasPlanFlyout`.
- Document the public API in `docs/extending.md`.
- Tests for: event firing, extension stage executes, ActionCompleted listener.

### Phase 3 — Broader Laravel-way audit (separate RFCs)

- Tools registration via config / service container.
- More domain events (Budget snapshot, conversation started, goal switched).
- Service objects for queries (`BudgetQueries`, `ActionQueries`).
- Policies for admin-only operations.
- Notification channels.

## 7. Drawbacks & risks

| Risk | Severity | Mitigation |
|---|---|---|
| Over-engineering for solo use | Medium | Only worth shipping if open-source is the goal. Today CLAUDE.md says it is. |
| Test refactor cost | Medium | Snapshot test locks the output during refactor. ~10 existing prompt tests should pass unchanged. |
| Stage ordering bugs | Medium | Array order is explicit and reviewable in config; document the constraints in `docs/extending.md`. |
| Performance regression | Low | Pipeline overhead is negligible (method calls). Same DB queries run as today. |
| Plugin author confusion | Medium | Ship `docs/extending.md` with a worked example. |
| Onboarding diverges from standard | Low | Two pipelines registered side-by-side under `prompt.stages.{mode}`. |

## 8. Alternatives considered

### A. Events only (no pipeline)

```php
$prompt = $base;
event(new BuildingPrompt($builder));
// listeners add sections
```

**Why rejected**: Laravel events don't naturally enforce ORDER. Prompt
sections are order-sensitive (locale knowledge before hard-rule that
references it). Priority on listeners works but is fragile.

### B. Plugin static registry (Filament-style)

```php
CoachAgent::registerStage(new MyStage, priority: 50);
```

**Why rejected**: Globally-mutable class state; harder to test in
isolation; not idiomatic Laravel. Pipeline + config is cleaner.

### C. Macros on CoachAgent

```php
CoachAgent::macro('myCustomSection', fn () => '...');
```

**Why rejected**: Macros are for adding methods, not for accumulating
output. Doesn't compose with the existing heredoc cleanly.

### D. Service container resolution per section

```php
foreach (config('coach.prompt.sections') as $key) {
    $output .= app("coach.prompt.section.{$key}")->render();
}
```

**Why rejected**: Loses the pipeline's natural mutation pattern and
context passing. Pipeline gives you `$next` for chaining naturally.

## 9. Open questions

1. **Where do built-in stages live?** Options: `app/Coach/PromptStages/`,
   `app/Ai/PromptStages/`, or `app/Prompts/Stages/`. Recommend
   `app/Coach/PromptStages/` to group with the future `app/Coach/`
   namespace for non-AI coaching logic.

2. **Should `PromptBuilder` be immutable** (returns new instances) or
   mutable? Pipeline traditionally uses mutable. Recommend mutable for
   simplicity; pipeline stages already return the builder anyway.

3. **Do we expose `replaceSection(string $key, ...)`** so a plugin can
   OVERRIDE a built-in section (e.g. replace `Personality` with a
   custom one)? Recommend yes — but with named sections (each built-in
   stage tags its section), so plugins can target specifically.

4. **How does a Composer package register its stages?** Two paths:
   - Manually: user adds to `config/coach.php` after installing.
   - Auto: package ships a ServiceProvider that appends to
     `coach.prompt.extensions` in `boot()`. Laravel auto-discovers.
   Recommend BOTH supported; document the auto-discovery path.

5. **Reference Gemini's prompting strategies**: every stage should be
   designed with knowledge of how Gemini handles system instructions,
   function calling, and multi-turn context. See
   <https://ai.google.dev/gemini-api/docs/prompting-strategies?hl=pt-br>
   before designing new stages or rearranging order.

## 10. Implementation checklist

PR1 — pipeline refactor (no behavioral change):

- [ ] `app/Coach/Contracts/PromptStage.php`
- [ ] `app/Coach/PromptBuilder.php`
- [ ] `app/Coach/PromptStages/*` (15 stages extracted from current heredoc)
- [ ] `config/coach.php` `prompt.stages.standard` + `prompt.stages.onboarding`
- [ ] Refactor `CoachAgent::instructions()` to use Pipeline
- [ ] Refactor `CoachAgent::onboardingInstructions()` to use Pipeline (same shape)
- [ ] Snapshot test: byte-identical output for fixed contexts
- [ ] Existing prompt-assertion tests pass unchanged
- [ ] Pint + Pest green

PR2 — events + extensions:

- [ ] `App\Events\BuildingPrompt` + `PromptBuilt`
- [ ] `coach.prompt.extensions` config wired into pipeline
- [ ] `App\Events\ActionCompleted` + fire from `HasPlanFlyout`
- [ ] `docs/extending.md` with worked example
- [ ] Tests for event firing, extension stage executes, ActionCompleted listener

## 11. Decision

Pending discussion. Open questions in §9 to resolve first.
