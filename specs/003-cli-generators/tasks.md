---
description: "Task list for CLI Generators (spec 003)"
---

# Tasks: CLI Generators (`wp corex make:*`)

**Input**: Design documents from `specs/003-cli-generators/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/cli-contracts.md, quickstart.md

**Tests**: REQUIRED (constitution). Every implementation task is preceded by a failing test (TDD). The
engine/renderer/naming/generators are unit-tested headlessly with a temp dir (no WP-CLI); command
registration is integration-tested.

**Guard Gate (per story)**: `clean-code-guard` + `wp-guard` (production), `test-guard` (tests),
`docs-guard` (docs). ABSPATH guard on every src class file. **Generated stub output must itself pass
clean-code + wp-guard (FR-007).**

## Format: `[ID] [P?] [Story] Description`

- All paths repo-relative from `C:\wamp64\www\corex`. Code under `packages/cli/src` (`Corex\Cli`).

---

## Phase 1: Setup

- [X] T001 [P] Add `namespace`, `prefix`, `path` defaults to `plugins/corex-core/config/app.php`; create `tests/Unit/Cli/` and `tests/Integration/Cli/`; confirm `Corex\Cli` → `packages/cli/src` autoload resolves.

---

## Phase 2: Foundational (shared by all stories)

- [X] T002 [P] Write failing `tests/Unit/Cli/StubRendererTest.php`: replaces every `{{ token }}`; a leftover `{{ … }}` raises `UnresolvedPlaceholderException` (FR-001, FR-003).
- [X] T003 Implement `packages/cli/src/Generators/StubRenderer.php` (+ `UnresolvedPlaceholderException`).
- [X] T004 [P] Write failing `tests/Unit/Cli/NamingTest.php`: normalize + apply suffix; strip existing suffix; reject empty/illegal/reserved names with `InvalidNameException`; `postTypeFor` snake-cases (FR-009, FR-010).
- [X] T005 Implement `packages/cli/src/Support/Naming.php` (+ `InvalidNameException`).
- [X] T006 [P] Implement `packages/cli/src/Generators/GeneratorContext.php` (basePath/namespace/prefix value object).

**Checkpoint**: render + naming + context ready.

---

## Phase 3: User Story 1 — Stub engine + make:model (Priority: P1) 🎯 MVP

**Goal**: render a stub and write a constitution-compliant Model file, idempotently.

**Independent Test**: run the Model generator into a temp dir; a `Models/<Name>.php` is written with all
placeholders replaced and no leftover tokens; re-running skips, `--force` overwrites.

- [X] T007 [P] [US1] Write failing `tests/Unit/Cli/GeneratorEngineTest.php`: `generate()` returns `created` and writes the file (dir created); existing file + no force → `skipped` (unchanged); + force → overwritten; a stub with an unprovided token → error/exception, no file (FR-002, FR-003, FR-008).
- [X] T008 [US1] Implement `packages/cli/src/Generators/{Generator.php (abstract), GeneratorResult.php}`.
- [X] T009 [US1] Implement `packages/cli/src/Generators/GeneratorEngine.php` (resolve path from context+subPath, render via StubRenderer, write idempotently with force) (depends on T003, T005, T006, T008).
- [X] T010 [P] [US1] Implement `packages/cli/src/Generators/ModelGenerator.php` + `packages/cli/stubs/model.stub` — a read-only `Corex\Models\Model` subclass (postType/fields/casts), ABSPATH guard, i18n-ready.
- [X] T011 [US1] Write failing `tests/Unit/Cli/ModelGeneratorTest.php`: `make:model Career` into a temp dir produces a valid Model at `Models/Career.php`, zero leftover `{{ }}`, namespace/prefix from the context (SC-001). Make it pass.
- [X] T012 [US1] Guard gate (incl. running `clean-code-guard` + `wp-guard` on the *generated* Career.php); validate quickstart Scenario 1.

**Checkpoint**: MVP — scaffolds a Model. STOP and validate.

---

## Phase 4: User Story 2 — The four-generator set (Priority: P1)

**Goal**: `make:repository`/`make:controller`/`make:service` scaffold their artifacts in the right
shape.

**Independent Test**: each generator writes the correct artifact (base class/contract, sub-path,
suffix); each generated file passes the guards unedited.

- [X] T013 [P] [US2] `packages/cli/src/Generators/RepositoryGenerator.php` + `stubs/repository.stub` — extends `Corex\Repositories\PostRepository`, declares `model()`.
- [X] T014 [P] [US2] `packages/cli/src/Generators/ControllerGenerator.php` + `stubs/controller.stub` — a thin controller (constructor-injected service, routes/validates only).
- [X] T015 [P] [US2] `packages/cli/src/Generators/ServiceGenerator.php` + `stubs/service.stub` — a service (constructor-injected repository, holds logic).
- [X] T016 [US2] Write failing `tests/Unit/Cli/GeneratorSetTest.php`: each of the four generators yields the correct `stub/suffix/subPath/placeholders` and renders a valid file in a temp dir (FR-005, FR-006). Make it pass.
- [X] T017 [US2] Guard gate on the four generated outputs (clean-code + wp-guard must pass unedited); validate quickstart Scenario 2 (SC-002).

**Checkpoint**: all four MVC artifacts scaffold.

---

## Phase 5: User Story 3 — Safe & ergonomic generation (Priority: P2)

**Goal**: no overwrite without `--force`; invalid names rejected with no write; clear outcomes.

**Independent Test**: re-run → skipped; `--force` → overwritten; invalid name → rejected, no file.

- [X] T018 [P] [US3] Write `tests/Unit/Cli/SafetyTest.php`: idempotent skip, force overwrite, and invalid-name rejection (no file written) across the engine + Naming (FR-008, FR-009, SC-003, SC-004).
- [X] T019 [US3] Harden any gap the safety tests expose; ensure `GeneratorResult` carries a clear message for each outcome (FR-011); guard gate; validate quickstart Scenario 3.

**Checkpoint**: generators are safe to re-run.

---

## Phase 6: User Story 4 — WP-CLI optional (Priority: P2)

**Goal**: `wp corex make:*` registers only when WP-CLI is present; the framework loads without it.

**Independent Test**: with WP-CLI present the commands register; with it absent the framework loads with
no error; the engine works headlessly.

- [ ] T020 [P] [US4] `packages/cli/src/Commands/MakeCommand.php` — parse the name arg + `--force`, pick the generator for the subcommand, call the engine, report via `WP_CLI::success/warning/error`.
- [ ] T021 [US4] `packages/cli/src/CliServiceProvider.php` — bind StubRenderer/Naming/GeneratorEngine/the four generators/`GeneratorContext` (from Config); `boot()` registers `wp corex make:*` only when `class_exists('WP_CLI')`; add `CliServiceProvider::class` to `Boot`'s core provider list (depends on US1–US3).
- [ ] T022 [US4] Write `tests/Integration/Cli/CommandRegistrationTest.php`: in WP-CLI context the `corex make:model` command is registered; the data-layer/foundation boot still clean (FR-012, SC-005). (Skips gracefully if WP-CLI not loaded in the test runtime.)
- [ ] T023 [US4] Guard gate; validate quickstart Scenario 4.

**Checkpoint**: all four stories functional.

---

## Phase 7: Wiring & Polish

- [ ] T024 [P] Update `plugins/corex-core/README.md` (or a CLI doc) with a `wp corex make:*` section; run docs-guard.
- [ ] T025 Run full `quickstart.md` validation against `./wp`; final guard pass (clean-code + wp-guard + test-guard) incl. the generated outputs; confirm the headless unit suite passes with no WP-CLI and no optional plugins (SC-006).
- [ ] T026 Update `PROGRESS.md` (spec 003 done) + `DECISIONS.md` (any new choices); verify Definition of Done.

---

## Dependencies & Execution Order

- **Setup** → **Foundational (renderer/naming/context)** → **US1 (engine + make:model)** → **US2 (set)**
  → **US3 (safety)** → **US4 (WP-CLI)** → **Polish**.
- US1 depends on the foundational renderer/naming/context. US2 depends on US1's engine + Generator
  abstract. US3 mostly tests behavior already in the engine/naming (hardens if needed). US4 wraps the
  generators in the WP-CLI command + provider.

### Parallel opportunities
- Foundational: StubRenderer (T002/T003) ∥ Naming (T004/T005) ∥ GeneratorContext (T006).
- US2: the three generators + stubs (T013/T014/T015) in parallel.

---

## Implementation Strategy

### MVP
Setup + Foundational + **US1** = a working `make:model` engine. Validate (Scenario 1), then add the
rest of the set (US2), safety (US3), and the WP-CLI surface (US4).

### Notes
- The generator engine never references `WP_CLI`; only `MakeCommand`/`CliServiceProvider` do, behind a
  `class_exists('WP_CLI')` gate (Principle IX).
- Each stub is authored so its rendered output passes `clean-code-guard` + `wp-guard` unedited (FR-007).
- One task at a time; guards before each commit.
