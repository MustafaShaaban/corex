# Corex — Spec Kit Starter Prompt

Paste the blocks below into Claude Code **in order**. Each block is one step. Wait for completion and review before the next. This follows Spec Kit best practice: establish the constitution first, specify before planning, plan before tasking, task before implementing.

---

## STEP 0 — Prerequisites (run in your terminal first)

```bash
# Install Spec Kit
uvx --from git+https://github.com/github/spec-kit.git specify init corex-framework
cd corex-framework

# Install the guard skills for Claude Code
npx skills add amElnagdy/guard-skills --skill wp-guard --agent claude-code
npx skills add amElnagdy/guard-skills --skill woo-guard --agent claude-code
npx skills add amElnagdy/guard-skills --skill clean-code-guard --agent claude-code
npx skills add amElnagdy/guard-skills --skill test-guard --agent claude-code
npx skills add amElnagdy/guard-skills --skill docs-guard --agent claude-code

# If any guard is ever missing when needed, the agent must install it
# automatically (constitution rule) before running it — never skip the gate.

# Open Claude Code in this directory, then place the reference docs
# (COREX-FRAMEWORK.md, COREX-WORKING-GUIDE.md, COREX-EMAIL-ADDON.md) in the root.
```

> **Required environment step — WordPress must be installed.** Corex is a WordPress framework and
> cannot run, be tested, or be reviewed without a WordPress install around it (constitution
> "Environment Gate", v1.1.0). After scaffolding the monorepo, install WordPress (≥ 7.0) and map
> the repo into `wp-content/` (junctions/symlinks; core is gitignored, never committed). Verify
> with `wp theme list` (shows `corex`) and `wp plugin list` (shows `corex-core`, `corex-blocks`,
> `corex-config`) and that the site boots before writing or resuming framework code. The concrete
> install + mapping for this project is recorded in `DECISIONS.md` #18 and `PROGRESS.md`.

---

## STEP 1 — The opening prompt (paste into Claude Code)

```
You are helping me build Corex, a professional, Laravel-inspired WordPress
framework, using spec-driven development with Spec Kit.

Two reference documents are in the repo root:
- COREX-FRAMEWORK.md — the full architecture reference (read it fully)
- COREX-WORKING-GUIDE.md — how we work and maintain continuity (read it fully)

Read BOTH documents completely before responding.

Then, set up the continuity system described in COREX-WORKING-GUIDE.md Part A:
1. Create CLAUDE.md and AGENTS.md at the repo root using the template in §A.5.
2. Create PROGRESS.md using the structure in §A.4 (empty but structured).
3. Create DECISIONS.md using the format in §A.6, pre-filled with the key
   decisions already made (name=Corex, layered architecture, runtime CSS
   tokens over build-time, no CSS framework in output, ACF-optional field
   drivers, WordPress 7.0 Abilities API for agents, Polylang with WPML-
   compatible abstraction, Pest for testing, tags-not-branches for releases).

Do not write framework code yet. Only set up the continuity scaffolding.

When done, end with the NEXT STEP block defined in COREX-WORKING-GUIDE.md §A.3.
```

---

## STEP 2 — Write the constitution (Spec Kit)

```
/constitution

Create the Corex constitution from COREX-FRAMEWORK.md §1 (Philosophy &
Constitution). Include, as enforceable rules:

- The 10 principles in §1, verbatim in intent.
- The "Next Step Rule": every response ends with a NEXT STEP block
  (format in COREX-WORKING-GUIDE.md §A.3).
- The "Guard Gate": no diff is presented or merged until the relevant
  guard skill (wp-guard / woo-guard / clean-code-guard / test-guard /
  docs-guard) has run clean on it.
- The Definition of Done from COREX-WORKING-GUIDE.md §D.4.
- The source-of-truth hierarchy from §A.1.

Keep it concise and machine-actionable — this file is read by every agent
on every session.
```

---

## STEP 3 — Specify the first module (the core)

```
/specify

Specify the corex-core foundation module. Behavior to capture:

- Boot: a Boot class that self-initializes on plugins_loaded, independent
  of any theme. Works in CLI, REST, admin, and cron contexts.
- DI Container: PSR-11 compliant. Resolves controllers, services,
  repositories. Supports singleton and factory bindings.
- ControllerMap: auto-discovers and registers all controllers — no manual
  allowlist.
- HookRegistry: controllers declare hooks in a hooks() method; the registry
  wires them on boot.
- Config: three-tier resolution (.env → options table → defaults), exposed
  via a Config facade.

Scope: ONLY this foundation. No models, no blocks, no forms yet.
Reference COREX-FRAMEWORK.md §2, §4, §5 for structure.
```

---

## STEP 4 — Continue the Spec Kit flow

```
/clarify
```
(Answer the clarifying questions it raises, guided by the reference docs.)

```
/plan

Plan the corex-core module. Target: WordPress 7.0+, PHP 8.3+, Composer
PSR-4 autoload, monorepo layout per COREX-FRAMEWORK.md §4. Use PHP 8.3
features (attributes, enums, readonly) where they improve clarity.
```

```
/tasks
```
(Review the generated task list against PROGRESS.md ordering.)

```
/implement

Implement the first task only. After producing code:
- run $wp-guard and $clean-code-guard on the diff,
- write Pest tests,
- update PROGRESS.md and DECISIONS.md,
- end with the NEXT STEP block.

Then stop and wait for my review before the next task.
```

---

## The rhythm from here

Repeat per module, in this recommended order (each is a `/specify` → `/plan` → `/tasks` → `/implement` cycle):

1. **corex-core foundation** (Boot, Container, ControllerMap, HookRegistry, Config) ← you are here
2. **Model + Field driver system** (ACF-optional) + **QueryBuilder**
3. **CLI generators** (`make:model`, `make:controller`, `make:block`)
4. **corex-blocks** (auto-discovery, conditional assets, connectors)
5. **Middleware + Security module** (the backoffice door)
6. **theme + design token pipeline** (theme.json → CSS vars → brand.json)
7. **Forms engine**
8. **Abilities/MCP layer**
9. **Corex Mail (Email Studio) add-on** — templates, event triggers, queue, attachments
10. **Other add-ons** (profile-manager, woo) — each its own spec
11. **Setup wizard + demo content**

After every single `/implement`, the agent ends with a NEXT STEP block and
updates PROGRESS.md — so you, or any other LLM, can resume instantly.

Note: Corex Mail (9) comes right after the Abilities layer because the Forms
engine (7), Profile Manager, and WooCommerce all *consume* it — but it only
needs the Event Bus and Models, which exist by step 2. You may pull it earlier
if email is needed sooner; it has no dependency on blocks or theme.

---

## Why this order

The foundation (1) must exist before anything mounts on it. Models +
QueryBuilder (2) are used by everything. The CLI (3) makes all later work
faster. Blocks, security, theme, and forms (4–7) are the visible product.
Abilities (8) and add-ons (9) extend a working core. The wizard (10) wraps
a finished product for distribution.

This is the dependency order — build inward-out, never the reverse.
