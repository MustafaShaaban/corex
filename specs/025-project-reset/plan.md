# Implementation Plan: Project reset CLI (025)

**Branch**: `feature/025-project-reset` | **Date**: 2026-06-11 | **Spec**: [spec.md](./spec.md)

## Summary

`wp corex reset` with two modes. A **pure planner** turns a `ResetRequest` (mode, dry-run, confirmed) + a
`ResetInventory` (the Corex add-ons, `corex_*` option/flag keys, and demo markers) into an ordered `ResetPlan`
of `ResetAction`s — no WordPress, no DB. A pure `ResetGate` decides whether a destructive plan may execute
(full mode requires the typed safeguard). A thin `ResetCommand` (WP-CLI only) gathers the inventory from WP,
asks the planner for the plan, prints it (dry-run) or — after the gate passes — hands it to a `ResetExecutor`
(the WP/DB boundary) that applies each action. The destructive DB wipe lives only in the executor and is
unreachable unless the gate permits.

## Technical Context

**Language/Version**: PHP 8.3. **Primary Dependencies**: the spec-003/019 CLI engine pattern (pure core + thin
`class_exists('WP_CLI')` command), the spec-021 feature-flag registry (flag keys), WP/WP-CLI primitives at the
executor boundary (`deactivate_plugins`, `delete_option`, `wp_delete_post`, DB reset/core install).
**Testing**: Pest — the planner + gate are unit-tested headlessly; the executor's non-destructive actions
(soft reset) are integration-tested on `./wp`; the DB wipe is gated and **not** run against the dev DB.
**Project Type**: CLI tooling in `packages/cli`. **Constraints**: pure planner/gate (no WP); destructive op
confined to the executor and unreachable without the typed safeguard (fail-closed); dry-run changes nothing.

## Constitution Check (v1.2.1)

- [x] **III/IV (layering + DI)** — PASS. `ResetPlanner`/`ResetGate`/`ResetPlan`/`ResetAction`/`ResetRequest`/
  `ResetInventory` are pure, injected; `ResetCommand` + `ResetExecutor` are the only WP/WP-CLI surface, bound in
  `CliServiceProvider`.
- [x] **VII (security)** — PASS. The destructive full reset is **fail-closed**: `ResetGate::permits()` returns
  false unless the typed safeguard is present, and the executor's wipe is reachable only through a permitted
  plan (FR-005/FR-009). WP-CLI's own `--yes`/confirm is required *in addition*.
- [x] **IX (optional dep)** — PASS. WP-CLI confined to `ResetCommand` under `class_exists('WP_CLI')`; the
  planner/gate run headlessly.
- [x] **V/VI/VIII (tokens/blocks/RTL)** — N/A (CLI; no UI surface).
- [x] **X (spec)** — this plan implements spec 025 (written first).
- [x] **Guard Gate / DoD** — planned: clean-code-guard (planner/gate/command) + wp-guard (executor:
  deactivate/delete/wipe, capability + confirmation) + test-guard (Pest). Docs: `packages/cli/README.md` reset
  section. No i18n/RTL surface (CLI output strings stay WP-CLI conventional).

**Gate**: PASS.

## Architecture (to build)

| Component | Kind | Responsibility |
|---|---|---|
| `Reset/ResetRequest` | pure value object | mode (soft/full), dryRun, confirmed (the typed safeguard) |
| `Reset/ResetInventory` | pure value object | the Corex footprint the planner acts on: add-on plugin files, option keys, flag keys, demo page marker |
| `Reset/ResetAction` | pure value object | one action: kind (deactivate-addon / delete-option / remove-demo / db-wipe) + target + human label |
| `Reset/ResetPlan` | pure value object | ordered `ResetAction[]` + `isDestructive()` + `summary()` |
| `Reset/ResetPlanner` | pure service | `plan(ResetRequest, ResetInventory): ResetPlan` — builds the ordered actions per mode |
| `Reset/ResetGate` | pure service | `permits(ResetRequest): bool` — false for full mode without `confirmed` (fail-closed) |
| `Commands/ResetCommand` | WP-CLI boundary | gather inventory from WP → plan → dry-run print, else gate → execute; reads `--hard`, `--yes-i-mean-it`, `--dry-run` |
| `Reset/ResetExecutor` | WP/DB boundary | apply each action (`deactivate_plugins`, `delete_option`, revert front-page, `wp_delete_post`; full: DB reset + core install + activate theme) |

**Separation guarantee**: `ResetExecutor::wipeDatabase()` is only ever called for a `db-wipe` action, which the
planner emits only for full mode, which `ResetCommand` only executes when `ResetGate::permits()` is true — three
independent checks, the gate being the pure, unit-tested one.

## Project Structure (to create)

```text
packages/cli/src/
├── Reset/{ResetRequest,ResetInventory,ResetAction,ResetPlan,ResetPlanner,ResetGate,ResetExecutor}.php
└── Commands/ResetCommand.php
packages/cli/src/CliServiceProvider.php        (register planner/gate/executor + the command, WP-CLI-gated)
tests/Unit/Cli/ResetPlannerTest.php            (plan shapes per mode + dry-run)
tests/Unit/Cli/ResetGateTest.php               (permits matrix — the safety gate)
tests/Integration/Cli/ResetExecutorTest.php    (soft-reset actions on ./wp; DB wipe NOT run)
```

## Phase 0 / 1 artifacts

- `research.md` — the safety-gate design, the inventory sources, and the "fresh starter" primitive choice.
- `data-model.md` — the value objects + the action kinds + plan ordering.
- `contracts/reset-cli-contract.md` — the `wp corex reset` command surface (flags, exit behaviour, output).
- `quickstart.md` — how to run + validate each mode (incl. the gate refusal).

## Complexity Tracking

No unjustified violations. The planner/gate/executor split is the standard Corex CLI pattern; it exists here to
keep the destructive operation pure-gated and headless-testable, which is the whole point of the feature.
