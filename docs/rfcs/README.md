# RFCs

Architectural decision records for non-trivial changes to Coach. Write one
before refactors that affect public extension points, multi-PR work, or
patterns the team will follow for future work.

## Format

`{NNNN}-{kebab-case-title}.md` — numbered sequentially. Sections:

1. **Summary** — 2-3 sentences
2. **Motivation** — why this, why now
3. **Current state** — what we have today
4. **Proposed design** — what we're choosing
5. **Public API** — what downstream code sees (if any)
6. **Migration plan** — how we get from here to there, broken into PRs
7. **Drawbacks & risks** — be honest
8. **Alternatives considered** — what we rejected and why
9. **Open questions** — known unknowns
10. **Implementation checklist** — concrete PR-by-PR scope

## Status

- **Proposed** — open for discussion
- **Accepted** — green-lit, ready to implement
- **Implemented** — code shipped
- **Superseded** — a later RFC replaced this

## Index

| # | Title | Status |
|---|---|---|
| [0001](0001-prompt-pipeline-and-extensibility.md) | Prompt assembly pipeline & extensibility hooks | Proposed |
