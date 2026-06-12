---
title: Contributing
description: How to contribute to Corex — the on-ramp to the authoritative working guide.
audience: contributor
stability: stable
last_verified: null
---

# Contributing

How to make a change to Corex and get it merged. This page is the **on-ramp** — it walks you through the path
and links the authoritative sources rather than duplicating their rules.

## Before your first contribution

1. Get a working environment: [getting-started](../00-getting-started/) for your OS.
2. Read the rules: [the constitution](../../../specs/constitution.md) and
   [`COREX-WORKING-GUIDE.md`](../../../COREX-WORKING-GUIDE.md).
3. Understand the workflow: [Team workflow](../04-team-workflow/).

## The path for a change

```mermaid
flowchart LR
  pick[Pick the next task<br/>PROGRESS.md → Next] --> spec[Open a spec<br/>/speckit-specify]
  spec --> build[Build test-first]
  build --> guard[Run the Guard Gate on the diff]
  guard --> pr[Branch off develop → PR → CI green]
  pr --> merge[Review → merge to develop]
```

1. **Pick a task** from [`PROGRESS.md`](../../../PROGRESS.md) → "Next" (or agree a new one).
2. **Open a spec first** — code never precedes its spec (Principle X). See
   [The Spec Kit loop](../04-team-workflow/spec-kit.md).
3. **Build it test-first** (Pest / Jest / Playwright). See [Quality gates](../04-team-workflow/quality-gates.md).
4. **Run the Guard Gate** on your diff (clean-code / wp / woo / test / docs) and fix any violation.
5. **Branch, commit, PR**: branch off `develop`, use [Conventional Commits](../04-team-workflow/branching-and-commits.md),
   open a PR into `develop`; CI must be green.
6. **Update continuity**: `PROGRESS.md` + `DECISIONS.md`, and end your work with a `NEXT STEP` block.

## What a good PR looks like

- It links its spec and describes what changed and **why**.
- It shows tests passing (and adds tests for new behaviour).
- It obeys the constitution: thin controllers, DI (no `new` of collaborators), token-only styling, escaping +
  nonces + capabilities, i18n-ready, RTL-verified.
- It touches one concern — small and reviewable.

## Reporting a bug or proposing a feature

Open a GitHub issue describing the behaviour and the expected result. A feature becomes a **spec** before it
becomes code — so a clear problem statement is the most useful thing you can provide.

## See also

- [Team workflow](../04-team-workflow/) (the full detail) · [`COREX-WORKING-GUIDE.md`](../../../COREX-WORKING-GUIDE.md)
  · [the constitution](../../../specs/constitution.md)
