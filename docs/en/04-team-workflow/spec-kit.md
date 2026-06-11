---
title: Working with Claude Code + Spec Kit
description: The spec-first loop the team uses to build Corex with an AI agent.
audience: contributor
stability: stable
last_verified: null
---

# Working with Claude Code + Spec Kit

Corex is built **spec-first**: a spec is written and reviewed before the code. This is enforced by the
constitution (Principle X) and is how an AI agent (Claude Code) and a human stay in sync. The authoritative
description is [`COREX-WORKING-GUIDE.md` Part D](../../../COREX-WORKING-GUIDE.md) and
[`COREX-SPECKIT-START.md`](../../../COREX-SPECKIT-START.md); this page is the working summary.

## The loop

```mermaid
flowchart LR
  specify[/speckit-specify<br/>spec.md] --> clarify[/speckit-clarify<br/>resolve ambiguity]
  clarify --> plan[/speckit-plan<br/>plan.md + Constitution Check]
  plan --> tasks[/speckit-tasks<br/>tasks.md TDD-ordered]
  tasks --> implement[/speckit-implement<br/>one task, guard, repeat]
  implement --> pr[PR → develop → CI green]
```

| Step | Produces | Rule |
|---|---|---|
| `/speckit-specify` | `spec.md` (user stories, FRs, success criteria) + a quality checklist | WHAT/WHY, no HOW |
| `/speckit-clarify` | resolved ambiguities recorded in the spec | only when a decision needs the user |
| `/speckit-plan` | `plan.md` + research/data-model/contracts/quickstart + a **Constitution Check** | the gate before code |
| `/speckit-tasks` | `tasks.md`, TDD-ordered, grouped by user story | tests precede implementation |
| `/speckit-implement` | code, one task at a time, each with its guard run | stop and review between tasks |

## The rules that never bend

- **No code before its spec.** If you are tempted to "just build it", open the spec first
  ([why](../../../DECISIONS.md) — see the compliance review that produced specs 018–024).
- **Every response ends with a `NEXT STEP` block** (Just completed / Recommended next / Why / Alternatives) so
  any teammate — or the next agent session — can resume instantly.
- **Continuity lives in files:** `PROGRESS.md` (status + next) and `DECISIONS.md` (why we chose X) are updated
  every session. Chat is disposable; these files are the project's memory.

## Conflicts win upward

If a request conflicts with the rules, the agent **surfaces the conflict and stops** rather than silently
choosing. The source-of-truth order ([`COREX-WORKING-GUIDE.md §A.1`](../../../COREX-WORKING-GUIDE.md)):

```text
1. specs/constitution.md   2. COREX-FRAMEWORK.md   3. the active spec
4. PROGRESS.md             5. the code
```

## Where the specs live

`specs/NNN-name/` — each holds `spec.md`, `plan.md`, `tasks.md`, and supporting artifacts. Browse
[`specs/`](../../../specs/) to see every module's spec.

## See also

- [Quality gates](./quality-gates.md) · [Branching & commits](./branching-and-commits.md) ·
  [`COREX-SPECKIT-START.md`](../../../COREX-SPECKIT-START.md)
