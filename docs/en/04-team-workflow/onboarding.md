---
title: Onboarding a new developer
description: The first-day checklist for a developer joining a Corex project.
audience: contributor
stability: stable
last_verified: null
---

# Onboarding a new developer

This is the path from "I have repo access" to "I shipped my first reviewed change". It **links** to the
authoritative documents rather than restating their rules — read those; this page tells you the order.

## Day one checklist

1. **Get the code running.** Follow the [getting-started guide](../00-getting-started/) for your OS until
   `wp theme list` shows `corex`.
2. **Read the four authoritative files** (15 minutes — they are short and they govern everything):
   - [`specs/constitution.md`](../../../specs/constitution.md) — the non-negotiable rules.
   - [`COREX-FRAMEWORK.md`](../../../COREX-FRAMEWORK.md) — the architecture.
   - [`COREX-WORKING-GUIDE.md`](../../../COREX-WORKING-GUIDE.md) — how the team works + continuity.
   - [`PROGRESS.md`](../../../PROGRESS.md) — what is done and what's next.
3. **Understand the two doc surfaces:** this handbook (ops/contributor) vs the **docs-app** site (product +
   class reference). See [What lives where](../../README.md#what-lives-where).
4. **Run the tests** so you know the baseline is green:

   ```bash
   composer test
   ```

   ```text
   Tests:    295 passed (829 assertions)
   ```

5. **Skim the glossary** so the vocabulary (Service Provider, Repository, Event Bus, Guard Gate, …) is familiar:
   [`docs/_glossary.md`](../../_glossary.md).

## How work flows here (the one-paragraph version)

Corex is **spec-first**: nothing is built without a spec. You pick up the next item from `PROGRESS.md`, open a
spec with Spec Kit, build it test-first, run the Guard Gate on your diff, and open a PR into `develop` that must
pass CI. The full loop is in [The Spec Kit loop](./spec-kit.md); the rules each step enforces are in
[Branching & commits](./branching-and-commits.md) and [Quality gates](./quality-gates.md).

## Your first change

1. Read [Branching & commits](./branching-and-commits.md) and [Quality gates](./quality-gates.md).
2. Start from the smallest real task in [`PROGRESS.md`](../../../PROGRESS.md) → "Next".
3. If it touches new behaviour, open a spec first (see [The Spec Kit loop](./spec-kit.md)).
4. Run `git status --short --branch`, branch off `develop`, and claim your spec path, task IDs, and files owned.
5. Build it test-first, run the guard, record the handoff evidence, and open a PR.

## See also

- [Team workflow index](./index.md) · [Contributing on-ramp](../08-contributing/) ·
  [`COREX-WORKING-GUIDE.md`](../../../COREX-WORKING-GUIDE.md)
