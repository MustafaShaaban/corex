<!--
SYNC IMPACT REPORT
Latest amendment: 1.2.0 → 1.2.1 (PATCH — clarified Principle VII's scope: admin-menu screens are exempt
from the route middleware pipeline but MUST use the shared AdminGuard helper, not hand-rolled cap+nonce).
  - 1.2.1 (2026-06-11): Principle VII scope clarification (admin-menu screens → AdminGuard). Remediation
    P5 of the compliance review. No new principle; existing AdminDashboard + SetupWizardScreen refactored
    onto `Corex\Security\Admin\AdminGuard` (5 Pest tests). Templates unaffected. DECISIONS.md #58.
  - 1.2.0 (2026-06-11): Added "The Pre-Implementation Confirmation Rule (mandatory)" under
    Operating Rules — every request must be confirmed against the constitution + specs before
    implementing; non-trivial work follows the Spec Kit flow (no code without a spec); the
    relevant guard runs before any diff is presented; PROGRESS/DECISIONS are updated and every
    response ends with NEXT STEP; a request to skip these requires an explicit, logged exception.
    Adopted after a compliance review found the 13-item "Finish Corex" initiative was built
    without spec files. Templates unaffected. DECISIONS.md #54.
  - 1.1.0 (2026-06-07): Added "The Environment Gate (mandatory)" under Operating Rules —
    a working WordPress install that recognizes the Corex theme + plugins is required before
    any framework code is written or resumed. Templates unaffected. DECISIONS.md #18.
  - 1.0.0 (2026-06-07): initial adoption.

Initial adoption (1.0.0):
Modified principles: all 10 newly defined from COREX-FRAMEWORK.md §1.
Added sections:
  - Core Principles I–X (the 10 non-negotiables)
  - Operating Rules: Next Step Rule, Guard Gate, Definition of Done
  - Source-of-Truth Hierarchy
  - Governance
Removed sections: none (template placeholders replaced).
Templates requiring updates:
  ✅ .specify/templates/plan-template.md — Constitution Check gate filled with the 10 Corex gates
  ✅ .specify/templates/spec-template.md — read; no mandatory-section change required (no conflict)
  ✅ .specify/templates/tasks-template.md — "Tests OPTIONAL" line replaced with the constitution's
     mandatory-tests + Guard Gate + i18n/RTL/WCAG requirement
  ✅ specs/constitution.md — pointer stub to this canonical file
Deferred TODOs: none.
-->

# Corex Constitution

The non-negotiable rules of Corex. Every contributor — human or AI — reads this first,
on every session, before doing anything else. When a tool, skill, generated snippet, or
request conflicts with these rules, **the rules win**: say so rather than comply.

This file is the canonical constitution. `specs/constitution.md` is a pointer to it; both
resolve to these rules (the documented source-of-truth hierarchy references `specs/`).

## Core Principles

### I. The Theme Is a Skin, Not a Skeleton
The theme holds presentation only — FSE templates, parts, patterns, style variations, and
token consumption.
- MUST NOT contain business logic, CPT/taxonomy registration, or plugin bootstrapping.
- MUST remain disposable: deactivating the theme breaks presentation, never data or API.
Rationale: presentation and domain must be separable so a rebrand never risks data or behavior.

### II. Plugins Boot Themselves
`corex-core` self-initializes on `plugins_loaded`, independent of any theme.
- MUST function in CLI, REST, admin, and cron contexts — not only on front-end page loads.
- MUST NOT depend on a theme being active to register controllers, hooks, or services.
Rationale: the engine is the product; the theme is interchangeable.

### III. Thin Controllers, Fat Services
- Controllers MUST only route, validate input shape, call one service method, return a response.
  No DB calls, no business rules in controllers.
- Services MUST hold business logic and orchestrate; they MUST NOT query the database directly
  or echo output.
- Repositories MUST be the only layer that talks to the data source.
- Models describe the shape of an entity (value objects), not god classes.
Rationale: each layer is reasoned about and tested alone.

### IV. Everything Is Injected
- Dependencies MUST be resolved through the PSR-11 container.
- Code MUST NOT instantiate its dependencies inside methods (`new Service()` inside a method
  is a violation).
Rationale: injection is what makes the framework testable.

### V. Design Tokens Are Runtime, Never Build-Time
- `theme.json` MUST be the single source of truth for design tokens, exposed as CSS custom
  properties; per-site `brand.json` overrides resolve at runtime.
- MUST NOT introduce build-time token systems (no Tailwind tokens, no Bootstrap variables).
- Blocks MUST consume CSS variables, never a raw hex/size/font value.
Rationale: a rebrand is configuration, not a recompile.

### VI. Assets Load Conditionally
- A block's CSS/JS MUST load only when that block is present on the page (declared in `block.json`).
- MUST NOT load any global CSS/JS library. Ever.
Rationale: pay only for what renders.

### VII. Security Is Declarative and Automatic
- Routes MUST declare their middleware (`nonce`, `auth`, `throttle`, `sanitize`).
- Controllers MUST NOT hand-write security checks; the middleware applies them.
- Output MUST be escaped, input sanitized, queries prepared, capabilities + nonces enforced.
- Scope clarification (v1.2.1): this declarative pipeline governs Corex **routes** — the REST/AJAX
  controller lifecycle that carries a `Request`/`Response` through the middleware `Pipeline`. WordPress
  **admin-menu screens** (`admin_menu`/`admin_init` page callbacks) are a different lifecycle with no Corex
  `Request`, so they are exempt from the pipeline — but they MUST NOT hand-roll their cap + nonce check
  either; they route it through the single shared `Corex\Security\Admin\AdminGuard` helper. (DECISIONS #58.)
Rationale: security that is automatic cannot be forgotten.

### VIII. RTL Is a First-Class Citizen
- Styling MUST use logical CSS properties (`margin-inline-start`, `inset-inline-end`, …) by default.
- Arabic (RTL) layouts MUST be correct by default, not patched afterward. `postcss-rtlcss`
  handles edge cases only.
Rationale: bilingual AR/EN is a core requirement, not an afterthought.

### IX. No Optional Dependency Is a Hard Dependency
- ACF, WooCommerce, Polylang, and WPML MUST be detected and adapted to behind an interface
  (driver/abstraction), never `require`d.
- The framework MUST run fully with none of them installed.
Rationale: portability and zero forced licensing/coupling.

### X. The Spec Is the Source of Truth
- Code MUST be generated from the specification. When intent changes, the spec changes first.
- A spec MUST exist (and be reviewed) before non-trivial code is written for a module.
Rationale: the durable artifact is the spec; chat history is disposable.

## Operating Rules

These apply to every agent on every response, in addition to the principles above.

### The Next Step Rule (mandatory)
Every response MUST end with a NEXT STEP block, exactly this shape:

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

Rationale: it makes the project resumable by any human or LLM at any time.

### The Guard Gate (mandatory)
No diff is **presented, committed, or merged** until the relevant guard skill has run clean on it.
- Before running a guard, the agent MUST check it is installed; if missing, install it
  (`npx skills add amElnagdy/guard-skills --skill <name> --agent claude-code`) and then run it.
  A missing guard is never an excuse to skip the gate.
- Guard mapping (run all that apply to the diff):

  | The diff changed | Guard to run |
  |---|---|
  | Any production code | `clean-code-guard` |
  | WP plugin/theme/block/REST/AJAX/query | `wp-guard` |
  | WooCommerce code | `woo-guard` (on top of `wp-guard`) |
  | Test code | `test-guard` |
  | Docs / README / docstrings | `docs-guard` |

Rationale: the constitution is the law; the guards are the inspector.

### Definition of Done (per feature/task)
A change is done only when ALL hold:
- [ ] Follows this constitution.
- [ ] Generated via `wp corex make:*` where a generator applies.
- [ ] Unit + E2E tests written and green (Pest / Jest / Playwright).
- [ ] Relevant guard(s) run clean (Guard Gate).
- [ ] WCAG 2.2 AA for any UI.
- [ ] Strings translation-ready (i18n); no hardcoded user-facing text.
- [ ] RTL verified.
- [ ] Docs updated in the same change (docs-guard checks drift).
- [ ] `PROGRESS.md` updated; non-trivial choices logged in `DECISIONS.md`.

### The Environment Gate (mandatory)
Corex is a WordPress framework: it cannot run, be tested, or be meaningfully reviewed without a
working WordPress install around it. Therefore:
- The project **REQUIRES a working WordPress install** (WP ≥ 7.0) in which the monorepo is mapped
  into `wp-content/` (junctions/symlinks; the repo stays the single source of truth — core is
  never committed).
- Before writing or resuming any framework code, an agent **MUST verify WordPress loads and
  recognizes the Corex theme and plugins** — `wp theme list` shows `corex`, `wp plugin list`
  shows `corex-core`, `corex-blocks`, `corex-config`, and the site boots with them active and no
  PHP fatals. If it does not, fixing the environment comes first; no module code proceeds on a
  broken install.
- The install/mapping procedure is recorded in `DECISIONS.md` and `PROGRESS.md`; an agent reads
  those before assuming the environment is missing.
Rationale: framework code written against an absent WordPress is unverifiable — the gap that
prompted this rule.

### The Pre-Implementation Confirmation Rule (mandatory)

Applies to **every** request, idea, or discussion — not only large builds. Before writing
any code or making any change in response to a request, the agent MUST:

1. **Confirm against the standard first.** Check the request against this constitution and
   the relevant spec(s). If it conflicts, **say so and stop** — surface the conflict and wait
   for the user, rather than complying.
2. **Spec before code (Spec Kit flow).** For any non-trivial implementation, follow
   `/speckit-specify → /clarify → /plan → /tasks → /implement`. A reviewed spec MUST exist in
   `specs/` **before** the code is written (Principle X). No code without a spec.
3. **Guard before the diff.** Run the relevant guard skill (Guard Gate) clean on the change
   before presenting it; install the guard first if it is missing.
4. **Update continuity + close the loop.** Update `PROGRESS.md` and log non-trivial choices in
   `DECISIONS.md`, and end every response with the NEXT STEP block.
5. **Exceptions are explicit.** If the user asks for something that skips any of the above, the
   agent MUST remind them of this rule and obtain the user's **explicit confirmation of the
   exception** before proceeding, and record the exception in `DECISIONS.md`. Autonomy or an
   "implement it" instruction is NOT, by itself, an exception to spec-first or the Guard Gate.

Rationale: an autonomous "Finish Corex" initiative delivered working, tested code but **bypassed
the Spec Kit flow** — no spec files were written before the code. Authority order (A.1) puts the
constitution above any prose brief; this rule makes "confirm, then spec, then build" the default
that a brief cannot silently override.

## Source-of-Truth Hierarchy

When anything conflicts, resolve in this order (top wins):

1. `specs/constitution.md` — these non-negotiable rules.
2. `COREX-FRAMEWORK.md` — the architecture reference.
3. The active spec in `specs/` for the module being built.
4. `PROGRESS.md` — what is done, in progress, and next.
5. The code — the current implementation.

If code contradicts the constitution, the code is wrong, not the constitution.

## Governance

- This constitution supersedes all other practices and conventions.
- **Amendments** MUST be made by editing this canonical file, recording the change in the
  Sync Impact Report comment above, bumping the version per the policy below, and logging the
  rationale in `DECISIONS.md`. Dependent Spec Kit templates MUST be re-checked in the same change.
- **Versioning policy** (semantic):
  - MAJOR — backward-incompatible governance/principle removal or redefinition.
  - MINOR — a new principle/section or materially expanded guidance.
  - PATCH — clarifications, wording, non-semantic refinements.
- **Compliance** — every spec, plan, task set, and diff is reviewed against these principles.
  The Spec Kit `/speckit-plan` Constitution Check gate enforces this before implementation.
- Architectural changes MUST update `COREX-FRAMEWORK.md` in the same change (its §26 rule).

**Version**: 1.2.1 | **Ratified**: 2026-06-07 | **Last Amended**: 2026-06-11
