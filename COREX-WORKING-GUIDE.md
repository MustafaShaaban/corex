# Corex — Working Guide & Continuity Protocol

**How to use this library, how to keep building it, and how any human or AI agent picks up where the last one left off.**

> Read this together with `COREX-FRAMEWORK.md` (the architecture reference). That document explains *what* Corex is. This one explains *how to work on it and with it* — and how to never lose continuity.

| | |
|---|---|
| **Audience** | Mustafa, the team, and any LLM (Claude Code, Codex, Cursor, Gemini) |
| **Companion doc** | `COREX-FRAMEWORK.md` |
| **Last updated** | 2026-06-07 |

---

## Part A — The Continuity Protocol (read this first)

The single biggest risk in an AI-built project is **lost context**: a new session, a different LLM, or a new teammate starts cold and makes decisions that contradict earlier ones. This protocol prevents that.

### A.1 — The source-of-truth hierarchy

When anything conflicts, resolve in this order (top wins):

1. **`specs/constitution.md`** — the non-negotiable rules.
2. **`COREX-FRAMEWORK.md`** — the architecture reference.
3. **The active spec** in `specs/` for the module being built.
4. **`PROGRESS.md`** — what is done, in progress, and next.
5. **The code** — the current implementation.

If code contradicts the constitution, the code is wrong, not the constitution.

### A.2 — Required files at the repo root (the agent entry points)

Every agent reads these before doing anything. Keep them current.

| File | Purpose | Who reads it |
|---|---|---|
| `CLAUDE.md` | Claude Code entry point — points to the docs below | Claude Code |
| `AGENTS.md` | Generic agent entry point (Codex, Cursor, etc.) | All other LLMs |
| `specs/constitution.md` | The rules | All agents |
| `COREX-FRAMEWORK.md` | Architecture reference | All agents + humans |
| `COREX-WORKING-GUIDE.md` | This file | All agents + humans |
| `PROGRESS.md` | Live status + next steps | All agents + humans |
| `DECISIONS.md` | Decision log (why we chose X over Y) | All agents + humans |

`CLAUDE.md` and `AGENTS.md` should be near-identical and short — they just orient the agent and hand off to the real docs. Example content is in §A.5.

### A.3 — The "always recommend next step" rule

**Every agent, at the end of every response, must end with a `NEXT STEP` block.** This is mandatory and goes in the constitution. It is what makes the project resumable by anyone.

The block has exactly this shape:

```
---
NEXT STEP
- Just completed: <one line>
- Recommended next: <one specific, actionable step>
- Why: <one line>
- Alternatives: <optional — other valid directions>
- Blockers/decisions needed from you: <optional>
---
```

Because the agent always states the recommended next step, you (or the next LLM) never have to reconstruct where things stand — you read the last `NEXT STEP` and continue. Pair this with `PROGRESS.md` (§A.4), which records the same information durably.

### A.4 — `PROGRESS.md` (the living status file)

Updated at the end of every working session — by the agent, automatically. Structure:

```markdown
# Corex — Progress

## Done
- [x] corex-core: Boot.php + DI container
- [x] corex-core: ControllerMap auto-discovery

## In progress
- [ ] QueryBuilder — where/orderBy done, eager loading (with) pending

## Next (recommended order)
1. Finish QueryBuilder eager loading + tests
2. make:model generator
3. Field driver abstraction (ACF-optional)

## Open decisions
- Logger: PSR-3 + which monitoring adapter? (see DECISIONS.md #7)

## Last session summary
2026-06-07 — built container + boot. Next: QueryBuilder.
```

A new session's first action is: **read `PROGRESS.md`, then continue from "Next."**

### A.5 — Example `CLAUDE.md` / `AGENTS.md`

```markdown
# Corex — Agent Entry Point

You are working on Corex, a Laravel-inspired WordPress framework.

BEFORE doing anything:
1. Read `specs/constitution.md` — the rules. They override everything.
2. Read `PROGRESS.md` — current status and the recommended next step.
3. Read the relevant spec in `specs/` for the module you're touching.
4. Skim `COREX-FRAMEWORK.md` for the architecture if unfamiliar.

WHILE working:
- Follow the constitution exactly. If a request conflicts with it, say so.
- Use the `wp corex make:*` generators rather than hand-writing boilerplate.
- Keep controllers thin; logic goes in services; data access in repositories.
- All styling via theme.json CSS variables. No hardcoded values. No CSS frameworks.

AFTER producing any code:
- Run the relevant guard skill on the diff ($wp-guard, $woo-guard,
  $clean-code-guard, $test-guard, $docs-guard) before presenting.
- Update `PROGRESS.md`.
- Log any non-trivial decision in `DECISIONS.md`.
- End your response with a NEXT STEP block (see constitution §"Next Step Rule").
```

### A.6 — `DECISIONS.md` (the decision log)

Records *why*, so no future agent re-litigates a settled choice. One entry per decision:

```markdown
## #12 — No CSS framework in shipped output
Date: 2026-06-07
Decision: Drop Bootstrap/Tailwind from shipped CSS; use theme.json CSS vars.
Why: HHEO proved build-time tokens block per-client theming + add page weight.
Alternatives considered: Tailwind (rejected: build-time tokens), Bootstrap (rejected: weight).
Status: Final.
```

---

## Part B — Quality Gates (the guard skills)

Adopt the [guard-skills](https://github.com/amElnagdy/guard-skills) as the enforcement layer for the constitution.

**Auto-install rule (put in the constitution):** before running a guard, the agent checks whether it is installed. If it is not, the agent installs it first, then uses it. No diff ships without its guard — a missing guard is never an excuse to skip the gate.

```bash
# Install per agent (example: Claude Code)
npx skills add amElnagdy/guard-skills --skill wp-guard --agent claude-code
npx skills add amElnagdy/guard-skills --skill woo-guard --agent claude-code
npx skills add amElnagdy/guard-skills --skill clean-code-guard --agent claude-code
npx skills add amElnagdy/guard-skills --skill test-guard --agent claude-code
npx skills add amElnagdy/guard-skills --skill docs-guard --agent claude-code

# If a guard is missing when needed, the agent runs the matching line above
# automatically before proceeding.
```

**When to run which guard:**

| After the agent changed | Run |
|---|---|
| Any production code | `$clean-code-guard` |
| WP plugin/theme/block/REST/AJAX/query | `$wp-guard` |
| WooCommerce code | `$woo-guard` (on top of wp-guard) |
| Test code | `$test-guard` |
| Docs / README / docstrings | `$docs-guard` |

**Definition of done** (put in the constitution): no diff is presented or merged until the relevant guard runs clean on it. The constitution is the law; the guards are the inspector.

> Also worth knowing: `WordPress/agent-skills` is the broader build-time catalog. Use it to *build*, guard-skills to *check*.

---

## Part C — Using the Library (consumer workflow)

For a developer *using* Corex to build a site (not building the framework itself).

### C.1 — Start a project
```bash
composer create-project corex/framework my-site
cd my-site
wp corex init --namespace="Acme" --prefix="acme" --mode=fse
npx wp-env start
composer install && npm install && npm run build
```

### C.2 — Apply a client brand (the design intake — see Framework doc §10)
1. Put the client's palette, fonts, and scale into `theme/theme.json`.
2. For per-site variants, create `brand.json` with overrides.
3. Self-host fonts (variable + unicode-range subset).
4. Run `wp corex brand:apply`.

### C.3 — Add a feature
```bash
wp corex make:model Product --cpt --rest --ability
wp corex make:service ProductService
wp corex make:controller ProductController
wp corex make:block product-grid --dynamic
```
Declare fields on the model (works with or without ACF), implement the service, register a connector so editors bind blocks to fields.

### C.4 — Enable optional modules
```bash
wp corex install corex/forms
wp corex install corex/profile-manager
```

### C.5 — Ship
```bash
wp corex health:check       # audit before deploy
# commit → PR → CI gates → merge → tag release
```

---

## Part D — Developing the Library (contributor workflow)

For working *on* Corex itself.

### D.1 — Golden rules
- Never put business logic in the theme.
- Never instantiate dependencies inside methods — inject them.
- Never hardcode a color, size, or font — use tokens.
- Never load an asset globally — scope it to a block.
- Never write a security check by hand — declare middleware.
- Never make an optional plugin (ACF, Woo, WPML) a hard dependency.

### D.2 — The spec-first loop (Spec Kit)
```
/constitution   → write/update the rules
/specify        → describe the module's behavior
/clarify        → resolve ambiguities
/plan           → technical plan
/tasks          → break into tasks
/implement      → build one task, review, repeat
```
Write the spec before the code. The spec is the durable artifact.

### D.3 — Per-task cycle
1. Pick the next task from `PROGRESS.md`.
2. Generate scaffolding with `wp corex make:*`.
3. Implement following the constitution.
4. Write tests (Pest unit, Playwright E2E).
5. Run the relevant guard on the diff.
6. Update `PROGRESS.md` + `DECISIONS.md`.
7. End with a `NEXT STEP` block.
8. PR into `develop` → green CI → merge.

### D.4 — Definition of done (per feature)
- [ ] Follows the constitution
- [ ] Generated via CLI where applicable
- [ ] Unit + E2E tests, green
- [ ] Relevant guard run clean
- [ ] WCAG 2.2 AA for any UI
- [ ] Strings translation-ready (i18n)
- [ ] RTL verified
- [ ] Docs updated (this matters — docs-guard checks drift)
- [ ] `PROGRESS.md` updated

---

## Part E — How Any LLM Continues the Work

This is the answer to "make sure any LLM can continue working on this."

1. **Cold start sequence** for a fresh agent (state this in `AGENTS.md`):
   read `constitution.md` → `PROGRESS.md` → active spec → continue from "Next."
2. **Model-agnostic by design** — Spec Kit, the guard skills, and the entry files all work across Claude Code, Codex, Cursor, Gemini. Nothing is Claude-only.
3. **Durable memory lives in files, not chat** — `PROGRESS.md`, `DECISIONS.md`, and the specs are the project's memory. Chat history is disposable; these files are not.
4. **The NEXT STEP block** at the end of every response means the handoff point is always explicit.
5. **The guards** mean a different LLM can't quietly violate the standards — the gate catches it.

The test of success: a brand-new LLM, given only the repo, can read four files and correctly state what to build next — without you explaining anything.

---

## Part F — Maintenance

- Update `PROGRESS.md` every session (agent does this automatically).
- Log decisions in `DECISIONS.md` as they happen.
- Update `COREX-FRAMEWORK.md` in the *same PR* as any architectural change.
- Run `wp corex docs:generate` in CI to keep auto-derived docs from drifting.
- Re-run `docs-guard` on doc changes before shipping.

---

*Continuity is a feature. Treat these files as production code.*
