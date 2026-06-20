# Corex — Agent Entry Point (Codex, Cursor, Gemini, any LLM)

You are working on **Corex**, a professional, Laravel-inspired WordPress framework.
Namespace `Corex\`, CLI `wp corex`, CSS prefix `--corex-`. Target: WordPress 7.0+,
PHP 8.3+, FSE block themes. Built spec-first with Spec Kit. This file mirrors `CLAUDE.md`.

## Cold-start sequence (COREX-WORKING-GUIDE.md Part E)
A fresh agent, given only this repo, must be able to read four files and correctly
state what to build next — without anyone explaining. Do this, in order:
1. Read `specs/constitution.md` — the non-negotiable rules. They override everything.
   (Canonical source: `.specify/memory/constitution.md`; `specs/constitution.md` mirrors it.)
2. Read `PROGRESS.md` — current status; continue from the "Next" section.
3. Read the active spec in `specs/` for the module you're touching.
4. Skim `COREX-FRAMEWORK.md` (architecture) and `COREX-WORKING-GUIDE.md` (how we work).
   `COREX-EMAIL-ADDON.md` is the Corex Mail spec for when its turn comes.

## Source-of-truth hierarchy (top wins) — §A.1
1. `specs/constitution.md`  2. `COREX-FRAMEWORK.md`  3. the active module spec
4. `PROGRESS.md`  5. the code. If code contradicts the constitution, the code is wrong.

## WHILE working
- Run `git status --short --branch` before edits; work from the active feature branch, never from `main`.
- **Single workspace (COREX-WORKING-GUIDE.md §A.7):** work only from the normal project root checkout — no
  `.worktrees` without explicit owner approval. Before editing, verify root/branch/status/log/remote/worktree, and
  **stop and report** if you are on the wrong branch, in the wrong checkout, or see uncommitted changes you did not
  create. The active PR branch is the working source of truth — continue from its latest pushed commit; never
  recreate completed work. Push only to that branch while its PR is open.
- Claim branch, spec path, task IDs, and owned files before changing code. Do not edit files owned by another
  active agent unless the handoff explicitly transfers them.
- Follow the constitution exactly. If a request conflicts with it, say so rather than comply.
- Use the `wp corex make:*` generators (once built) rather than hand-writing boilerplate.
- Thin controllers, fat services, repositories own data access. Everything injected via the
  PSR-11 container — never instantiate a dependency inside a method.
- All styling via `theme.json` CSS variables. No hardcoded values. No CSS frameworks.
- Logical CSS properties (RTL-first). No optional plugin (ACF/Woo/Polylang) as a hard dependency.

## AFTER producing any code (Definition of Done — §D.4)
- **Guard Gate:** run the relevant guard skill on the diff BEFORE presenting it. Auto-install
  if missing. No diff ships until its guard runs clean.
  clean-code-guard (any prod code) · wp-guard (WP code) · woo-guard (Woo) · test-guard · docs-guard.
- Tests written (Pest / Jest / Playwright), i18n-ready, RTL-verified, WCAG 2.2 AA for UI.
- Update `PROGRESS.md`; log non-trivial decisions in `DECISIONS.md`.
- For multi-agent work, final reports and handoffs must name the branch, spec path, completed task IDs,
  files owned, verification commands/results, guard status, and any files being released for another agent.
- **End every response with a NEXT STEP block** (format in COREX-WORKING-GUIDE.md §A.3 / constitution).

## Spec Kit workflow (commands namespaced `speckit-*`)
`/speckit-constitution` → `/speckit-specify` → `/speckit-clarify` → `/speckit-plan`
→ `/speckit-tasks` → `/speckit-implement`. Spec before code; review between tasks.
Module build order: see `COREX-SPECKIT-START.md` ("The rhythm from here").

Durable memory lives in files (`PROGRESS.md`, `DECISIONS.md`, `specs/`), not chat.
Nothing here is Claude-only — this workflow is model-agnostic by design.
