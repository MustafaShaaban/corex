# Corex — Agent Entry Point (Claude Code)

You are working on **Corex**, a professional, Laravel-inspired WordPress framework.
Namespace `Corex\`, CLI `wp corex`, CSS prefix `--corex-`. Target: WordPress 7.0+,
PHP 8.3+, FSE block themes. Built spec-first (the CoreX loop, below).

## BEFORE doing anything
1. Read `specs/constitution.md` — the non-negotiable rules. They override everything.
   (Canonical source lives at `.specify/memory/constitution.md`; `specs/constitution.md`
   mirrors it for the source-of-truth hierarchy.)
2. Read `PROGRESS.md` — current status and the recommended next step. Continue from "Next".
3. Read the active spec in `specs/` for the module you're touching.
4. Skim `docs/internal/COREX-FRAMEWORK.md` for the architecture if unfamiliar; `docs/internal/COREX-WORKING-GUIDE.md`
   for how we work. `docs/internal/COREX-EMAIL-ADDON.md` is the Corex Mail spec for when its turn comes.

## Source-of-truth hierarchy (top wins) — docs/internal/COREX-WORKING-GUIDE.md §A.1
1. `specs/constitution.md`  2. `docs/internal/COREX-FRAMEWORK.md`  3. the active module spec
4. `PROGRESS.md`  5. the code. If code contradicts the constitution, the code is wrong.

**Precedence between the instruction files** (constitution → `AGENTS.md` → `CONTRIBUTING.md`, with
`SECURITY.md` authoritative on its own subject) is stated once, in `AGENTS.md`. This file is its
Claude Code-flavoured mirror and must not diverge from it on anything but tooling.

## Role Gate — classify the session BEFORE editing (spec 061)
> Role Gate decides **where** you work · the spec decides **what** · Guard Gate decides **whether it is safe to
> ship** · UI/UX ProMax decides **whether visible UI is good enough**.

Pick one mode and stay in it (full detail: docs/internal/COREX-WORKING-GUIDE.md §F; copy/paste prompts:
`docs/en/04-team-workflow/ai-agent-start-prompts.md`):
1. **CoreX Framework Mode** — `plugins/`, `addons/`, `packages/`, root `theme/`/`specs/`/`docs/`, `docs-app/`,
   `ROADMAP.md`, root `PROGRESS.md`, framework UI, release. Must not edit `sites/<client>/` unless authorized.
2. **Client Site Mode** — `sites/<client>/` only. Must not continue the CoreX roadmap or edit framework dirs.
3. **Deployment Mode** — `dist` builder, `azure-pipelines.yml`, deploy/rollback. Packaging changes only.
4. **Docs/Planning Mode** — docs/specs/roadmap/decisions/prompts. No runtime code unless authorized.

Never edit as source: `wp/wp-content/` and `dist/` (runtime/build output; `dist/` is generated + git-ignored).
End every response with the required SUMMARY/…/NEXT STEP handoff format (see AGENTS.md / docs/internal/COREX-WORKING-GUIDE.md §F).

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
- Keep controllers thin; logic goes in services; data access in repositories.
- Everything injected via the PSR-11 container — never `new` a dependency inside a method.
- All styling via `theme.json` CSS variables. No hardcoded colors/sizes/fonts. No CSS frameworks.
- Logical CSS properties (RTL-first). No optional plugin (ACF/Woo/Polylang) as a hard dependency.

## AFTER producing any code (Definition of Done — docs/internal/COREX-WORKING-GUIDE.md §D.4)
- **Guard Gate:** run the relevant guard skill on the diff BEFORE presenting it. Auto-install
  it first if missing. No diff ships until its guard runs clean.
  - any production code → `clean-code-guard`
  - WP plugin/theme/block/REST/AJAX/query → `wp-guard`
  - WooCommerce code → `woo-guard` (on top of wp-guard)
  - test code → `test-guard`   ·   docs/README/docstrings → `docs-guard`
- Write tests (Pest unit, Jest for JS blocks, Playwright E2E). i18n-ready, RTL-verified, WCAG 2.2 AA.
- Update `PROGRESS.md`; log any non-trivial decision in `DECISIONS.md`.
- For multi-agent work, final reports and handoffs must name the branch, spec path, completed slices,
  files owned, verification commands/results, guard status, and any files being released for another agent.
- **End every response with a NEXT STEP block** (format in docs/internal/COREX-WORKING-GUIDE.md §A.3 / constitution).

## The CoreX loop (constitution v2.0.0, DECISIONS #621)
Grill (`grill-with-docs`) → research if needed (`research`) → spec with slices in `specs/NNN-name/spec.md`
→ owner-only steps as a checked script (`wizard`) → one slice at a time, test first (`tdd`; bugs start
with `diagnosing-bugs`) → Guard Gate → two-model review (`debate-review --local`) → PR (`pr`, then
`babysit-pr` for review rounds) → `retro` after a release. Anything a person sees is also designed first in
Claude Design on the CoreX design system, approved, built with `ui-ux-pro-max`, measured in the browser and
reviewed against the design (`AGENTS.md`, "UI, UX and front end"): required, not optional. Each step in full: `AGENTS.md`, "The CoreX
loop". The `/speckit-*` commands are retired; a spec that already has a `tasks.md` may finish from it.
What to build next: `PROGRESS.md` ("Next") and `ROADMAP.md`.

## Models, effort and agents (Claude Code tooling)
This table is the instruction the Agent tool's `model` and `effort` settings need: use it, do not pick
by feel. Max effort only when the owner asks for it, for one task.

| Work | Who | Model | Effort |
|---|---|---|---|
| Grilling, specs, architecture, final judgment on a diff | the main session | Fable | high |
| Designs and the CoreX design system in Claude Design | the main session | Fable | high |
| Building a slice (`tdd`), fixing a diagnosed bug | `corex-builder` agent | Opus | medium (high for a bug or a UI slice) |
| Tests, fixtures, lockfile and docs chores | `corex-tester` agent | Sonnet | medium |
| Searching and reading across the repo | `corex-scout` agent | Haiku | low |
| Reviewing a diff against its spec and the constitution | `corex-reviewer` agent | Fable | high |
| Comparing a rendered screen with its approved design, measured | `corex-ui-reviewer` agent | Fable | high |
| The recorded round for the owner (`ui-review-loop`), one per UI slice | `corex-tester` agent | Sonnet | medium |
| The second, other-vendor review | `debate-review`'s `review-debate` lane | Codex | high |

- The five agents are in `.claude/agents/`. Each brief names the spec, the slice, the files the agent
  owns, and the gate commands, because an agent does not see this conversation.
- **Builders work in the root checkout, one at a time per set of files.** Two builders run together only
  when their files do not overlap. No worktrees without the owner's approval (§A.7 still holds).
- The orchestrator reads every diff an agent leaves, runs the guards, and commits. Agents never commit.
- The main session can be switched between Fable and Opus from the app's model picker; long routine
  stretches (merging main into branches, release chores) run on Opus.

## Agent skills
### Issue tracker
GitHub Issues on `MustafaShaaban/corex`. See `docs/agents/issue-tracker.md`.
### Triage labels
The five default roles (`needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`).
See `docs/agents/triage-labels.md`.
### Domain docs
Single-context, in CoreX's own files: `docs/_glossary.md` and `DECISIONS.md`, never a new
`GLOSSARY.md` or `docs/adr/`. See `docs/agents/domain.md`.
