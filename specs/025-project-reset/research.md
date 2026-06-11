# Research: Project reset CLI (025)

## R1 — How to make a destructive command fail-closed

**Decision**: A pure `ResetGate::permits(ResetRequest): bool` that returns `false` for `mode=full` unless
`request->confirmed` is `true`; the `confirmed` flag is set **only** when the operator passed the typed
safeguard `--yes-i-mean-it`. The command additionally requires WP-CLI's own confirmation (`WP_CLI::confirm` /
`--yes`). The executor's `wipeDatabase()` is reachable only via a `db-wipe` action that the planner emits only
for full mode and the command runs only when the gate permits.

**Rationale**: Three independent gates (typed flag → gate → planner-emitted action), with the decisive one
being pure and unit-testable, mean the wipe cannot fire by accident or from a non-interactive path that didn't
opt in (FR-009). Fail-closed is the constitution's security default (Principle VII).

**Alternatives considered**: relying on WP-CLI's `--yes` alone (rejected — too easy to script accidentally);
an interactive y/n prompt only (rejected — not headless-testable, and prompts get auto-answered in CI).

## R2 — What feeds the planner (the inventory)

**Decision**: The command gathers a `ResetInventory` from WordPress and hands it to the pure planner:
- **add-on plugin files**: the active `corex-*` plugins (excluding the Corex core plugin + theme).
- **option keys**: every `corex_*` option, including `corex_features_*` (the flag keys come from the spec-021
  features registry) and `corex_setup_demo_seeded`.
- **demo marker**: the seeded Home page id (from `page_on_front` when `corex_setup_demo_seeded === '1'`) and the
  prior front-page settings to revert.

**Rationale**: Keeping the *gathering* in the command and the *deciding* in the planner is the Corex CLI
pattern (pure core fed by the WP boundary). The planner never guesses — it acts only on what it is given, so it
is deterministic and testable.

**Alternatives considered**: the planner reading WP directly (rejected — breaks purity/testability); a
hardcoded add-on list (rejected — drifts; gather the live active set instead).

## R3 — What "fresh Corex starter" restores to (the DB-wipe target)

**Decision**: Full reset = reset the database to a clean WordPress install (drop + recreate the standard
schema/options via WP-CLI's `wp db reset` + `wp core install` equivalents at the executor boundary), then
**activate only the Corex theme** and leave **no** `corex-*` add-ons active and **no** Corex options/flags/demo
content. The Corex core plugin remains present/active (it is the framework, mirroring the documented install in
PROGRESS "Environment quick reference"). The spec's *Fresh Corex starter* section is the authoritative
definition.

**Rationale**: "Theme only, no add-ons" from the input, made precise: a clean WP + Corex theme + Corex core,
nothing else. This is a deterministic, verifiable end state (SC-003).

**Alternatives considered**: also deactivating the Corex core plugin (rejected — that would un-Corex the site,
not reset it to a Corex *starter*); preserving users/admin (out of scope — a full wipe is a full wipe; the
operator opts in explicitly).

## R4 — Soft reset is not a safety gate

**Decision**: Soft reset touches only Corex's own footprint (its add-ons, its options/flags, the content it
seeded) and never deletes arbitrary content, so it does not require the typed safeguard — only the normal run.
A dry-run is offered for both modes.

**Rationale**: The blast radius is bounded to what Corex created; treating it as destructive would be
friction without safety benefit. The dry-run gives "show me first" for the cautious.

**Alternatives considered**: gating soft reset too (rejected — disproportionate; reserve the gate for the
irreversible DB wipe).
