# Corex — Agent Entry Point (Codex, Cursor, Gemini, any LLM)

You are working on **Corex**, a professional, Laravel-inspired WordPress framework.
Namespace `Corex\`, CLI `wp corex`, CSS prefix `--corex-`. Target: WordPress 7.0+,
PHP 8.3+, FSE block themes. Built spec-first (the CoreX loop, below). This file mirrors `CLAUDE.md`.

## Cold-start sequence (docs/internal/COREX-WORKING-GUIDE.md Part E)
A fresh agent, given only this repo, must be able to read four files and correctly
state what to build next — without anyone explaining. Do this, in order:
1. Read `specs/constitution.md` — the non-negotiable rules. They override everything.
   (Canonical source: `.specify/memory/constitution.md`; `specs/constitution.md` mirrors it.)
2. Read `PROGRESS.md` — current status; continue from the "Next" section.
3. Read the active spec in `specs/` for the module you're touching.
4. Skim `docs/internal/COREX-FRAMEWORK.md` (architecture) and `docs/internal/COREX-WORKING-GUIDE.md` (how we work).
   `docs/internal/COREX-EMAIL-ADDON.md` is the Corex Mail spec for when its turn comes.

## Source-of-truth hierarchy (top wins) — §A.1
1. `specs/constitution.md`  2. `docs/internal/COREX-FRAMEWORK.md`  3. the active module spec
4. `PROGRESS.md`  5. the code. If code contradicts the constitution, the code is wrong.

## Precedence between the instruction files themselves

Stated here once, and referenced rather than restated by the others. Four files tell an agent how to
work, and until spec 098 each of them carried its own partial copy of the workflow — which is three
chances for the copies to disagree and no way to tell which one was right.

1. **`specs/constitution.md`** (→ `.specify/memory/constitution.md`) — the non-negotiable rules.
   Overrides everything below, including a direct request. Where they conflict, say so rather than
   comply.
2. **`AGENTS.md`** — this file. The precedence order, the cold-start sequence, the Role Gate, and the
   required handoff format. `CLAUDE.md` is its Claude Code-flavoured mirror and must not diverge on
   anything but tooling.
3. **`CONTRIBUTING.md`** — the mechanics: branching, commit messages, versioning, how to run each
   suite, and what the guard gate runs. Where this file names a rule and `CONTRIBUTING.md` gives the
   command for it, `CONTRIBUTING.md` is the one to follow.
4. **`SECURITY.md`** — reporting, supported versions, and the dependency-advisory policy. Authoritative
   on its own subject over anything above it.

`README.md` carries the table of which *root document* answers which question. That is a different
question from this one, and neither list repeats the other.

## Role Gate — classify the session BEFORE editing (spec 061)
> Role Gate decides **where** you work · the spec decides **what** to build · Guard Gate decides **whether**
> it is safe to ship · UI/UX ProMax decides **whether** visible UI is good enough.

Pick exactly one mode and stay inside it (full detail: docs/internal/COREX-WORKING-GUIDE.md §F; prompts:
`docs/en/04-team-workflow/ai-agent-start-prompts.md`):

1. **CoreX Framework Mode** — edits `plugins/`, `addons/`, `packages/`, root `theme/`, root `specs/`, root `docs/`,
   `docs-app/`, `ROADMAP.md`, root `PROGRESS.md`, CoreX admin/login/docs UI, release/versioning. Follow the root
   agent files + `specs/constitution.md` + `docs/internal/COREX-WORKING-GUIDE.md` + `docs/internal/COREX-FRAMEWORK.md`. **Must not** edit
   `sites/<client>/` unless explicitly authorized.
2. **Client Site Mode** — edits `sites/<client>/` only (client plugin/theme, pages, blocks, templates, content,
   branding). Follow the root files for global safety + `sites/<client>/{AGENTS,CLAUDE,PROGRESS,DECISIONS}.md` +
   `sites/<client>/specs/`. **Must not** continue the CoreX roadmap or edit `plugins/`, `addons/`, `packages/`,
   root `theme/`, root `specs/`, `ROADMAP.md`, or root `PROGRESS.md` unless explicitly authorized.
3. **Deployment Mode** — edits the `dist` builder, `azure-pipelines.yml`, deploy scripts, release artifacts,
   runtime-file protection, rollback. **Must not** make client-design or framework-product changes beyond packaging.
4. **Docs/Planning Mode** — edits docs, specs, roadmap, decisions, prompts, handoffs. **Must not** ship runtime code
   unless explicitly authorized.

Never edit as source: `wp/wp-content/` and `dist/` (runtime/build output). `dist/` is generated and git-ignored.

## Required response/handoff format (spec 061)
End every working response with:
```text
SUMMARY            - What was done.
WORKSPACE          - Branch: / Repo root: / Git status: / Files changed:
MODE               - Framework / Client Site / Deployment / Docs-Planning
SPEC STATUS        - Spec path: / Slices: / Completed: / Remaining:
VERIFICATION       - Commands run: / Results: / Guards run:
BLOCKERS / DECISIONS NEEDED
RECOMMENDED NEXT STEP
---
NEXT STEP
- Just completed: / Recommended next: / Why: / Alternatives: / Blockers/decisions needed from you:
---
```

## WHILE working
- Run `git status --short --branch` before edits; work from the active feature branch, never from `main`.
- **Single workspace (docs/internal/COREX-WORKING-GUIDE.md §A.7):** work only from the normal project root checkout — no
  `.worktrees` without explicit owner approval. Before editing, verify root/branch/status/log/remote/worktree, and
  **stop and report** if you are on the wrong branch, in the wrong checkout, or see uncommitted changes you did not
  create. The active PR branch is the working source of truth — continue from its latest pushed commit; never
  recreate completed work. Push only to that branch while its PR is open.
- Claim branch, spec path, slice, and owned files before changing code. Do not edit files owned by another
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
- For multi-agent work, final reports and handoffs must name the branch, spec path, completed slices,
  files owned, verification commands/results, guard status, and any files being released for another agent.
- **End every response with a NEXT STEP block** (format in docs/internal/COREX-WORKING-GUIDE.md §A.3 / constitution).

## The CoreX loop (constitution v2.0.0, DECISIONS #621)
Every non-trivial change goes through these steps, in order. The skill named in each step is the tool;
where an agent has no skills, it does the same step by hand.

1. **Grill** (`grill-with-docs`): one question at a time until nothing about the change is open. Terms
   it settles go into `docs/_glossary.md`; decisions go into `DECISIONS.md` as the next free number.
2. **Research** (`research`), only when the spec depends on outside facts: findings go to
   `specs/NNN-name/research.md`.
3. **Spec**: `specs/NNN-name/spec.md`, from `.specify/templates/spec-template.md`. Requirements carry
   IDs (`FR-001`). The spec ends in **slices**: each one pull request, each naming the slices it waits
   on and the principles it touches. No separate plan or task file.
4. **Steps only the owner can do** (`wizard`): a script that walks the owner through them and checks each.
5. **Build one slice, test first** (`tdd`). A slice a person sees also follows "UI, UX and front end"
   below. Bugs start with `diagnosing-bugs`: reproduce, prove the cause, then the regression test.
6. **Guard Gate** (below), then **two-model review** (`debate-review --local`): one model finds
   problems, a model from another vendor tries to disprove them.
7. **Pull request** (`pr` for the description). Review rounds: `babysit-pr`.
8. After a release or a session that went wrong: `retro`, with what changes recorded in `DECISIONS.md`.

### UI, UX and front end: required, not optional
CoreX has no designer: every screen, page, block and email is designed and built by Claude. So any slice a
person sees runs these steps too, and a UI slice that skips one is not done.

1. **Design first, in Claude Design** (the Design artifact type in Claude, desktop or web), on the **CoreX
   design system** (the Design System artifact type), never on a palette or font picked for the occasion.
   Draw every state the spec names (empty, loading, error, full, a long name, a refused value) at desktop and
   phone width (375px), in light and dark, left to right and right to left.
2. **Approval and handoff.** The owner approves the design in the artifact. The approved version and its link
   go into `design/handoffs/<area>.md` (its README lists what a handoff holds), and the spec points at it. The
   approved design is the functional contract (DECISIONS #115): nothing in it ships as sample or placeholder.
3. **Build** with UI/UX ProMax (`ui-ux-pro-max`) on the constitution's rules: tokens from `theme.json` only,
   logical CSS properties, assets loaded only where the block is, every string translatable.
4. **Measure the rendered page**, never judge it from a screenshot: gaps, heights and alignment read off the
   page and kept as browser assertions; light, dark and right to left; 375px and 200% zoom; keyboard and focus.
   Run `npm run test:quality` (screens, accessibility, states, words, budget).
5. **Review the UI** before the two-model review: `corex-ui-reviewer` compares the render with the approved
   design, `design:accessibility-review` checks WCAG 2.2 AA, and `design:ux-copy` checks the words.
6. **One recorded round for the owner** (`ui-review-loop`), after steps 4 and 5 pass: a short recording of the
   slice's flow that the owner watches on a local review page and comments on at the moment in the video; the
   next round addresses each comment. Limits, because the model never sees the video but does see each step:
   - one round per slice, at the end, never while building; one more per batch of comments;
   - steps that return only what changed (`snapshot` with a diff), never a full page dump after every click;
   - run by `corex-tester` (Sonnet), so the steps fill that agent's context, not the main session's;
   - coverage audits (every screen and state) only when the owner asks, for example before a release;
   - only on a scratch site with test data, never a client site; on corex.local, mail goes to capture first,
     because the video and the timeline keep whatever was on screen and typed.
   The measured browser tests in step 4 stay the main check; the round shows what numbers cannot.
7. **A change to the look starts in the design system**, then reaches the code through `theme.json`, so the
   next design and the code stay the same.

The `/speckit-*` commands are retired. A spec that already has a `tasks.md` may finish its slices from
it. Matt Pocock's `to-spec`, `to-tickets`, `implement-spec`, `implement` and `wayfinder` are not used:
`specs/` and `ROADMAP.md` already hold what they would write elsewhere.
What to build next: `PROGRESS.md` ("Next") and `ROADMAP.md`.

## Agent skills
### Issue tracker
GitHub Issues on `MustafaShaaban/corex`. See `docs/agents/issue-tracker.md`.
### Triage labels
The five default roles (`needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`).
See `docs/agents/triage-labels.md`.
### Domain docs
Single-context, in CoreX's own files: `docs/_glossary.md` and `DECISIONS.md`, never a new
`GLOSSARY.md` or `docs/adr/`. See `docs/agents/domain.md`.

Durable memory lives in files (`PROGRESS.md`, `DECISIONS.md`, `specs/`), not chat.
Nothing here is Claude-only — this workflow is model-agnostic by design.
