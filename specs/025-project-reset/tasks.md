# Tasks: Project reset CLI (025)

**Forward spec** — code does not exist yet. TDD-ordered (Corex DoD: tests precede/accompany implementation).
The pure planner + gate are foundational (shared by all stories); the per-story tasks add the executor actions
and command paths. The destructive DB wipe is gated and **not** run against the dev DB. FR→component map is in
`plan.md`; the command surface is in `contracts/reset-cli-contract.md`.

## Phase 1: Setup

- [x] T001 Create `packages/cli/src/Reset/` and confirm the spec-019 CLI pattern (pure core + thin WP-CLI command in `CliServiceProvider`) is the model to follow.

## Phase 2: Foundational (pure planner + gate — blocks all stories)

- [x] T002 [P] Create the value objects in `packages/cli/src/Reset/`: `ResetRequest` (mode/dryRun/confirmed), `ResetInventory` (addonPlugins/optionKeys/demoPageId), `ResetAction` (kind/target/label), `ResetPlan` (actions + `isDestructive()` + `summary()`) per `data-model.md`.
- [x] T003 Write `tests/Unit/Cli/ResetGateTest.php` (RED): `ResetGate::permits()` — soft always true; full true only when `confirmed`, false otherwise (the fail-closed matrix).
- [x] T004 Implement `packages/cli/src/Reset/ResetGate.php` to pass T003 (pure; no WP).
- [x] T005 Write `tests/Unit/Cli/ResetPlannerTest.php` (RED): soft plan = deactivate-addon* → remove-demo → delete-option* (ordered) from an inventory; full plan = a single `db-wipe` action; `isDestructive()` true only for full; an empty inventory yields a "nothing to reset" soft plan.
- [x] T006 Implement `packages/cli/src/Reset/ResetPlanner.php` to pass T005 (pure; `plan(ResetRequest,ResetInventory): ResetPlan`).

**Checkpoint**: the safety gate + the plan are proven headlessly with no WP/DB.

## Phase 3: User Story 1 — Soft reset to a clean Corex slate (P1) 🎯 MVP

**Goal**: deactivate Corex add-ons, clear `corex_*` options + flags, remove seeded demo — non-Corex untouched.
**Independent test**: quickstart §4 + the executor integration test (§5).

- [x] T007 [US1] Write `tests/Integration/Cli/ResetExecutorTest.php` (RED): on `./wp`, `ResetExecutor::apply()` deactivates a seeded test add-on, deletes a seeded `corex_*` option, and removes a seeded demo page + reverts `show_on_front`/`page_on_front`.
- [x] T008 [US1] Implement `packages/cli/src/Reset/ResetExecutor.php` soft actions (`deactivate-addon`→`deactivate_plugins`, `delete-option`→`delete_option`, `remove-demo`→revert front page + `wp_delete_post`) to pass T007. (The `db-wipe` arm is added in US2.)
- [x] T009 [US1] Implement `packages/cli/src/Commands/ResetCommand.php` soft path: gather the `ResetInventory` from WP (active `corex-*` plugins, `corex_*` options incl. flags, demo page id), build the plan, execute via the executor, and `WP_CLI::success` a summary (FR-002/003/004/008).
- [x] T010 [US1] Register the command + bind `ResetPlanner`/`ResetGate`/`ResetExecutor` in `packages/cli/src/CliServiceProvider.php` under `class_exists('WP_CLI')` (`wp corex reset`).

**Checkpoint**: `wp corex reset` performs a correct, bounded soft reset on the real install.

## Phase 4: User Story 2 — Full reset behind a safety gate (P1)

**Goal**: `--hard` wipes the DB to a fresh Corex starter, but only with the typed safeguard; else fail-closed.
**Independent test**: quickstart §3 (refusal) + §2 (`--hard --dry-run`).

- [x] T011 [US2] Extend `ResetCommand` for the full path: read `--hard` (→ `mode=full`), `--yes-i-mean-it` (→ `confirmed`), and require WP-CLI confirm; call `ResetGate::permits()` and **refuse with no side effects** when false (FR-005/FR-009, fail-closed), printing the missing-safeguard message.
- [x] T012 [US2] Implement `ResetExecutor::wipeDatabase()` (the `db-wipe` arm): reset DB + reinstall WP core + activate only the Corex theme (the *fresh Corex starter*, spec definition). Guard it so it is only invoked for a `db-wipe` action from a permitted plan.
- [x] T013 [US2] The refusal property is unit-tested at the pure seam — `ResetGateTest` asserts `permits()` is false for `full` without `confirmed` (the command consults exactly this before the executor). Verified end-to-end live (quickstart §3): `wp corex reset --hard` refuses with zero changes. _(A WP_CLI-static-mock command test was deliberately avoided — it would test WP-CLI plumbing, not Corex logic, against test-guard.)_

**Checkpoint**: the full reset is impossible without the typed safeguard; with it, it restores the fresh starter.

## Phase 5: User Story 3 — Preview a reset before committing (P2)

**Goal**: `--dry-run` lists the planned actions for either mode and changes nothing.
**Independent test**: quickstart §2.

- [x] T014 [US3] Extend `ResetCommand` for `--dry-run`: build the plan, print `ResetPlan::summary()` (and, for full, that a DB wipe would occur), and perform no executor calls.
- [x] T015 [US3] Dry-run uses the same `ResetPlanner` plan as a real run (the planner ignores `dryRun`; only the command branches on it to skip the executor). Verified live (quickstart §2): both `--dry-run` modes print the plan and change nothing (plugins still active, options intact). _(Covered by the planner unit tests + live verification; the dry-run branch is a single early return in the command.)_

## Phase 6: Polish & cross-cutting

- [x] T016 Run the Guard Gate on the diff: `clean-code-guard` (planner/gate/command/value objects) + `wp-guard` (executor: deactivate/delete/wipe, capability + WP-CLI confirm) + `test-guard` (the new Pest tests); fix any violation.
- [x] T017 [P] Document `wp corex reset` in `packages/cli/README.md` (both modes, the safeguard, dry-run) per `contracts/reset-cli-contract.md`.
- [x] T018 [P] Verify live on `./wp`: `wp corex reset --dry-run`, the `--hard` refusal, and a soft reset (then confirm non-Corex content untouched). Record results.
- [x] T019 Update `PROGRESS.md` + add a `DECISIONS.md` entry (the safety-gate design); end with NEXT STEP.

## Dependencies

- Phase 2 (planner + gate) blocks all stories. US1 (soft) is the MVP; US2 (full+gate) and US3 (dry-run) build on
  the same command + executor. US1 → US2 → US3 in sequence (shared `ResetCommand`), but each is independently
  testable at its checkpoint.

## Implementation strategy

MVP = US1 (soft reset), fully usable on its own. US2 adds the gated destructive mode; US3 adds preview. The
pure planner + gate (Phase 2) are written test-first so the safety property is proven before any WP code.

## Parallel opportunities

- T002 (value objects) is [P] within Phase 2 before the planner/gate use them.
- T017 (README) and T018 (live verify) are [P] in polish.
