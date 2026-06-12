---
title: Team workflow
description: How the team works — onboarding, branching, commits, PR review, Spec Kit, and the quality gates.
audience: contributor
stability: stable
last_verified: null
---

# Team workflow

The newcomer on-ramp to how Corex is built. These pages **link** to the authoritative
[`COREX-WORKING-GUIDE.md`](../../../COREX-WORKING-GUIDE.md) and [the constitution](../../../specs/constitution.md)
— they explain them for a first-timer, they do not replace them.

## Pages

| Page | What it covers |
|---|---|
| [Onboarding](./onboarding.md) | The first-day path: get running, read the four files, run the tests, ship your first change. |
| [Branching & commits](./branching-and-commits.md) | git-flow-lite, Conventional Commits, the PR review process, releasing. |
| [The Spec Kit loop](./spec-kit.md) | Spec-first with Claude Code: `/speckit-specify → /plan → /tasks → /implement`, and the rules that never bend. |
| [Quality gates](./quality-gates.md) | The Guard Gate (clean-code/wp/woo/test/docs), Pest, Jest, Playwright, CI, the Definition of Done. |

## The one-paragraph version

Corex is **spec-first**: nothing is built without a spec. You take the next item from
[`PROGRESS.md`](../../../PROGRESS.md), open a spec with Spec Kit, build it test-first, run the Guard Gate on your
diff, and open a PR into `develop` that must pass CI. Releases are cut by merging `develop` → `main` and tagging
`vX.Y.0`. Continuity lives in files (`PROGRESS.md`, `DECISIONS.md`, the specs), and every response ends with a
`NEXT STEP` block so anyone can resume.
