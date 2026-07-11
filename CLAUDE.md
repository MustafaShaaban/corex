# Corex — Agent Entry Point (Claude Code)

You are working on **Corex**, a professional, Laravel-inspired WordPress framework.
Namespace `Corex\`, CLI `wp corex`, CSS prefix `--corex-`. Target: WordPress 7.0+,
PHP 8.3+, FSE block themes. Built spec-first with Spec Kit.

## BEFORE doing anything
1. Read `specs/constitution.md` — the non-negotiable rules. They override everything.
   (Canonical source lives at `.specify/memory/constitution.md`; `specs/constitution.md`
   mirrors it for the source-of-truth hierarchy.)
2. Read `PROGRESS.md` — current status and the recommended next step. Continue from "Next".
3. Read the active spec in `specs/` for the module you're touching.
4. Skim `COREX-FRAMEWORK.md` for the architecture if unfamiliar; `COREX-WORKING-GUIDE.md`
   for how we work. `COREX-EMAIL-ADDON.md` is the Corex Mail spec for when its turn comes.

## Source-of-truth hierarchy (top wins) — COREX-WORKING-GUIDE.md §A.1
1. `specs/constitution.md`  2. `COREX-FRAMEWORK.md`  3. the active module spec
4. `PROGRESS.md`  5. the code. If code contradicts the constitution, the code is wrong.

## Role Gate — classify the session BEFORE editing (spec 061)
> Role Gate decides **where** you work · Spec Kit decides **what** · Guard Gate decides **whether it is safe to
> ship** · UI/UX ProMax decides **whether visible UI is good enough**.

Pick one mode and stay in it (full detail: COREX-WORKING-GUIDE.md §F; copy/paste prompts:
`docs/en/04-team-workflow/ai-agent-start-prompts.md`):
1. **CoreX Framework Mode** — `plugins/`, `addons/`, `packages/`, root `theme/`/`specs/`/`docs/`, `docs-app/`,
   `ROADMAP.md`, root `PROGRESS.md`, framework UI, release. Must not edit `sites/<client>/` unless authorized.
2. **Client Site Mode** — `sites/<client>/` only. Must not continue the CoreX roadmap or edit framework dirs.
3. **Deployment Mode** — `dist` builder, `azure-pipelines.yml`, deploy/rollback. Packaging changes only.
4. **Docs/Planning Mode** — docs/specs/roadmap/decisions/prompts. No runtime code unless authorized.

Never edit as source: `wp/wp-content/` and `dist/` (runtime/build output; `dist/` is generated + git-ignored).
End every response with the required SUMMARY/…/NEXT STEP handoff format (see AGENTS.md / COREX-WORKING-GUIDE.md §F).

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
- Keep controllers thin; logic goes in services; data access in repositories.
- Everything injected via the PSR-11 container — never `new` a dependency inside a method.
- All styling via `theme.json` CSS variables. No hardcoded colors/sizes/fonts. No CSS frameworks.
- Logical CSS properties (RTL-first). No optional plugin (ACF/Woo/Polylang) as a hard dependency.

## AFTER producing any code (Definition of Done — COREX-WORKING-GUIDE.md §D.4)
- **Guard Gate:** run the relevant guard skill on the diff BEFORE presenting it. Auto-install
  it first if missing. No diff ships until its guard runs clean.
  - any production code → `clean-code-guard`
  - WP plugin/theme/block/REST/AJAX/query → `wp-guard`
  - WooCommerce code → `woo-guard` (on top of wp-guard)
  - test code → `test-guard`   ·   docs/README/docstrings → `docs-guard`
- Write tests (Pest unit, Jest for JS blocks, Playwright E2E). i18n-ready, RTL-verified, WCAG 2.2 AA.
- Update `PROGRESS.md`; log any non-trivial decision in `DECISIONS.md`.
- For multi-agent work, final reports and handoffs must name the branch, spec path, completed task IDs,
  files owned, verification commands/results, guard status, and any files being released for another agent.
- **End every response with a NEXT STEP block** (format in COREX-WORKING-GUIDE.md §A.3 / constitution).

## Spec Kit workflow (commands are namespaced `speckit-*`)
`/speckit-constitution` → `/speckit-specify` → `/speckit-clarify` → `/speckit-plan`
→ `/speckit-tasks` → `/speckit-implement`. Write the spec before the code; review between tasks.
Module build order: see `COREX-SPECKIT-START.md` ("The rhythm from here").

<!-- SPECKIT START -->
For additional context about technologies to be used, project structure,
shell commands, and other important information, read the current plan
at specs/068-admin-product-functional-completion/plan.md
<!-- SPECKIT END -->
