---
description: "Task list for corex-core Foundation"
---

# Tasks: corex-core Foundation

**Input**: Design documents from `specs/001-corex-core-foundation/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/foundation-contracts.md, quickstart.md

**Tests**: REQUIRED (constitution Definition of Done). Every implementation task is preceded by a
failing test task (TDD). Headless unit tests use Pest + Brain Monkey; a thin integration suite boots
the real `./wp` install.

**Guard Gate (per task)**: before any task's diff is presented/committed, run `clean-code-guard` +
`wp-guard` (production code), `test-guard` (test code), or `docs-guard` (docs). No diff ships dirty.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: parallelizable (different files, no incomplete dependency)
- **[Story]**: US1–US4 (user-story tasks only)
- All paths are repo-relative from `C:\wamp64\www\corex`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: dependencies, test harness, directory scaffolding.

- [X] T001 Add runtime deps to `plugins/corex-core/composer.json` and root `composer.json` (`psr/container`, `league/container`, `vlucas/phpdotenv`); run `composer update` and confirm the root autoloader resolves them. ✅ installed league/container 4.x, phpdotenv 5.6.3.
- [X] T002 [P] Add dev deps `pestphp/pest` + `brain/monkey` to root `composer.json`; create `tests/Pest.php`, `phpunit.xml.dist`, and a `composer test` script (groups: `unit`, `integration`). ✅ pest 2.36.1; scripts test / test:unit / test:integration; allow-plugins pest-plugin.
- [X] T003 [P] Create source subdirectories under `plugins/corex-core/src/`: `Foundation/`, `Hooks/`, `Http/`, `Container/Exceptions/`, `Support/Config/Sources/`, `Support/Facades/` (replace `.gitkeep` only where real files will land). ✅
- [X] T004 [P] Create test harness. *(Best-practice deviation: PHPUnit allows one global bootstrap, so per-suite setup uses base TestCases instead of two `bootstrap.php` files.)* `tests/bootstrap.php` (autoload), `tests/Unit/TestCase.php` (Brain Monkey), `tests/Integration/TestCase.php` (loads `./wp`, skips if absent), `tests/Pest.php` wiring; `tests/Unit/Foundation/` created. ✅ unit suite runs green.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: the one cross-cutting service every story's error path needs.

**⚠️ CRITICAL**: completes before user-story phases.

- [X] T005 [P] Write failing unit test `tests/Unit/Foundation/BootLoggerTest.php`: always writes to `error_log`; queues a single `admin_notices` message ONLY when `WP_DEBUG` is true; never throws (FR-023, SC-008). ✅ 6 tests, red-first confirmed.
- [X] T006 Implement `plugins/corex-core/src/Support/BootLogger.php` to pass T005 — debug-log always + `WP_DEBUG`-gated dismissible admin notice (escaped, `__()`-translatable, `manage_options`-gated); PSR-3-shaped `warning()`/`error()`. ✅ 6 passed; wp-guard + clean-code-guard + test-guard clean; ABSPATH guard convention added (DECISIONS #20).

**Checkpoint**: shared logging ready; user stories can begin.

---

## Phase 3: User Story 1 — Plugin boots itself & exposes a container (Priority: P1) 🎯 MVP

**Goal**: corex-core self-boots once on `plugins_loaded` in every context and exposes a PSR-11
container with `bind`/`singleton`/`make` autowiring and a provider register→boot lifecycle.

**Independent Test**: from WP-CLI and a front-end request, `Corex\Boot::app()` returns a booted app;
a `singleton` resolves to the same instance twice and a `bind` to new instances, with ctor deps
auto-injected; booting twice yields one set of registrations.

### Tests for User Story 1 (write first, MUST FAIL)

- [X] T007 [P] [US1] `tests/Unit/Foundation/ContainerTest.php`: `singleton` same vs `bind` new instance; `make` autowires ctor type-hints; unbound interface hint → `BindingResolutionException` (names interface, FR-007a); unhinted scalar → `BindingResolutionException` (names class+param, FR-009); A→B→A → `CircularDependencyException` (FR-010). ✅ 11 tests, red-first; + params override, optional default, has(), get()/PSR-11.
- [X] T008 [P] [US1] `tests/Unit/Foundation/ApplicationTest.php`: provider lifecycle runs `register()` for all then `boot()` for all; duplicate provider deduped; a provider throwing is caught and logged (FR-023), boot continues; `Boot` is idempotent (FR-002, SC-006).
- [X] T009 [P] [US1] `tests/Integration/BootContextsTest.php`: framework boots with no fatals in front-end, admin, REST, WP-CLI, cron with no theme/optional plugins active (FR-001, FR-003, FR-004, SC-001).

### Implementation for User Story 1

- [X] T010 [P] [US1] `plugins/corex-core/src/Container/ContainerInterface.php` (extends `Psr\Container\ContainerInterface` with `bind`/`singleton`/`instance`/`make`) + `Container/Exceptions/{ContainerException,BindingResolutionException,CircularDependencyException,EntryNotFoundException}.php`. ✅ (`tag`/`tagged` dropped — YAGNI, DECISIONS #21.)
- [X] T011 [US1] `plugins/corex-core/src/Container/Container.php` — **custom** PSR-11 container (reflection autowiring, shared/transient bindings, resolving-stack cycle detection); `league/container` reversed (DECISIONS #21). ✅ 11 tests green; clean-code-guard clean.
- [X] T012 [P] [US1] `plugins/corex-core/src/Foundation/ServiceProvider.php` — abstract base: `register()`, `boot()`, `subscribers()`, `controllerPaths()` (per contracts C3).
- [X] T013 [US1] `plugins/corex-core/src/Foundation/ProviderRepository.php` — collect providers from core list + `config('app.providers')` + Composer `extra.corex.providers` (read root `vendor/composer/installed.json`); dedupe by class-string; two-pass register/boot (depends on T012).
- [X] T014 [US1] `plugins/corex-core/src/Foundation/Application.php` — build `Container`, register core bindings (BootLogger, self), run `ProviderRepository`; wrap each provider call in try/catch → `BootLogger` (depends on T011, T012, T013).
- [X] T015 [US1] `plugins/corex-core/src/Boot.php` — `init()` hooks `boot()` on `plugins_loaded`; static idempotency guard; `app()` accessor; wire `Corex\Boot::init()` into `plugins/corex-core/corex-core.php` (depends on T014).
- [X] T016 [P] [US1] `plugins/corex-core/src/Support/Facades/Corex.php` — bounded global accessor `Corex::make()` reading `Boot::app()` (FR-008a; docblock states framework-boundary-only).
- [X] T017 [US1] Run guard gate (clean-code-guard + wp-guard) on the US1 diff; validate quickstart Scenarios 1 & 2; fix until green.

**Checkpoint**: MVP — the framework boots and resolves dependencies. STOP and validate.

---

## Phase 4: User Story 2 — Layered configuration (Priority: P1)

**Goal**: `Config::get(key, default)` resolves `.env` → WP options → defaults, with a non-fatal
missing-key fallback and a malformed/absent `.env` that never crashes boot.

**Independent Test**: a key defined in defaults, then options, then `.env` changes its resolved value
to follow precedence; an unknown key returns the fallback; a malformed `.env` logs + falls back.

> Engine tasks T018–T022 are independent of US1 and may proceed in parallel; T023 (binding into the
> container) depends on US1.

### Tests for User Story 2 (write first, MUST FAIL)

- [X] T018 [P] [US2] `tests/Unit/Foundation/ConfigTest.php`: precedence env>option>default; missing→fallback (FR-012); absent `.env` ok (FR-013); malformed `.env` → source empty + `BootLogger` called, lower layers serve (FR-014); SC-003 four combinations.

### Implementation for User Story 2

- [X] T019 [P] [US2] `plugins/corex-core/src/Support/Config/ConfigInterface.php` (`get`/`has`) + `Support/Config/Source.php` (`has`/`get`).
- [X] T020 [P] [US2] `plugins/corex-core/src/Support/Config/Sources/{DefaultsSource,OptionsSource,DotenvSource}.php`; `DotenvSource` uses `vlucas/phpdotenv` safe/immutable load and catches malformed input → `BootLogger` (depends on T019, T006).
- [X] T021 [US2] `plugins/corex-core/src/Support/Config/Repository.php` — ordered-source precedence engine implementing `ConfigInterface` (depends on T019, T020).
- [X] T022 [P] [US2] `plugins/corex-core/src/Support/Facades/Config.php` — `Config::get()/has()` over the bound `ConfigInterface`.
- [X] T023 [US2] `plugins/corex-core/src/Foundation/CoreServiceProvider.php` — bind `ConfigInterface` (Repository + Sources) as a singleton, ship `plugins/corex-core/config/app.php` defaults (incl. `app.providers`, `app.env`), and register this provider in the Application's core list (depends on US1, T021).
- [X] T024 [US2] Guard gate on the US2 diff; validate quickstart Scenarios 3 & 4.

**Checkpoint**: config-driven boot; providers can now be configured.

---

## Phase 5: User Story 3 — Declarative hooks (Priority: P2)

**Goal**: a class declares `hooks()` and the framework wires its actions/filters (container-resolved,
dedupe-safe).

**Independent Test**: a subscriber mapping one action + one filter (with priority/args) fires the
mapped methods; the subscriber is container-resolved; double registration does not double-fire.

> Depends on US1 (container).

### Tests for User Story 3 (write first, MUST FAIL)

- [X] T025 [P] [US3] `tests/Unit/Foundation/HookRegistryTest.php`: mapped action runs its method; filter runs at declared priority with declared args (FR-015); subscriber resolved from container (FR-016); same subscriber wired twice → single registration (FR-017, SC-004).

### Implementation for User Story 3

- [X] T026 [P] [US3] `plugins/corex-core/src/Hooks/SubscribesToHooks.php` — interface `hooks(): array` (contract C4 shape).
- [X] T027 [US3] `plugins/corex-core/src/Hooks/HookRegistry.php` — resolve subscriber from container, wire `add_action`/`add_filter` per entry (default priority 10 / args 1), dedupe by `FQCN::method@hook` (depends on US1, T026).
- [X] T028 [US3] Wire `ServiceProvider::subscribers()` into `HookRegistry` during the Application boot pass (depends on T027, T014).
- [X] T029 [US3] Guard gate on the US3 diff; validate quickstart Scenario 5.

**Checkpoint**: modules can attach behavior declaratively.

---

## Phase 6: User Story 4 — Controller auto-discovery (Priority: P3)

**Goal**: placing a controller in a module's `Controllers/` directory makes it discovered and
container-resolvable with zero registry edits.

**Independent Test**: a class in `src/Controllers/` is resolvable after boot; a non-controller file is
ignored; an empty `Controllers/` still boots.

> Depends on US1 (container).

### Tests for User Story 4 (write first, MUST FAIL)

- [X] T030 [P] [US4] `tests/Unit/Foundation/ControllerMapTest.php`: instantiable class in `Controllers/` discovered + bound; abstract/interface/trait/non-class skipped (FR-019); empty set boots (FR-020, SC-005).

### Implementation for User Story 4

- [X] T031 [US4] `plugins/corex-core/src/Http/ControllerMap.php` — PSR-4 scan of `namespace→dir` maps; keep only instantiable classes; register each as a container binding (depends on US1).
- [X] T032 [US4] Wire `ServiceProvider::controllerPaths()` into `ControllerMap` during the Application boot pass; add core's own `Corex\Controllers\` → `src/Controllers/` map to `CoreServiceProvider` (depends on T031, T023).
- [X] T033 [US4] Add a sample `plugins/corex-core/src/Controllers/PingController.php` proving discovery (no logic); guard gate; validate quickstart Scenario 6.

**Checkpoint**: all four stories independently functional.

---

## Phase 7: Polish & Cross-Cutting

- [ ] T034 [P] Write `plugins/corex-core/README.md` + `docs/foundation.md`: provider-authoring guide, container/config/hooks usage, the `extra.corex.providers` add-on seam (run docs-guard).
- [ ] T035 [P] Add `.env.example` entries for the `app.*` keys the foundation reads; confirm `.env` is gitignored.
- [ ] T036 Run full `quickstart.md` validation (all 6 scenarios) against `./wp`; confirm SC-001/SC-006/SC-008 in a real install.
- [ ] T037 [P] Final guard pass (clean-code-guard + wp-guard + test-guard) on the entire feature diff; confirm the headless unit suite is green with all optional plugins absent (SC-007).
- [ ] T038 Update `PROGRESS.md` (Phase 5 done) and `DECISIONS.md` (any new choices); verify the Definition-of-Done checklist for the feature.

---

## Dependencies & Execution Order

### Phase order
- **Setup (P1)** → **Foundational (P2, BootLogger)** → **US1 (P3)** → US2/US3/US4 → **Polish (P7)**.

### Story dependencies (this is a layered foundation — dependencies are real)
- **US1 (P1)**: depends only on Setup + Foundational. The MVP.
- **US2 (P1)**: config *engine* (T018–T022) is independent and can run parallel to US1; *binding it in*
  (T023) depends on US1.
- **US3 (P2)**: depends on US1 (container resolves subscribers).
- **US4 (P3)**: depends on US1 (container holds controller bindings); T032 also touches `CoreServiceProvider` (US2's T023).

### Within each story
- The failing test task precedes its implementation task(s).
- Container interface/exceptions (T010) before the Container (T011); Container before Application (T014); Application before Boot (T015).
- Config interface/sources (T019/T020) before Repository (T021) before the provider binding (T023).

### Parallel opportunities
- Setup: T002, T003, T004 in parallel after T001.
- US1: T007/T008/T009 (tests) in parallel; T010, T012, T016 in parallel.
- US2 engine: T019, T020, T022 in parallel; the whole engine can overlap US1.
- US3/US4 test tasks (T025, T030) and interface tasks (T026) are parallelizable once US1 is done.

---

## Parallel Example: User Story 1

```text
# Tests first (parallel), confirm they FAIL:
Task: ContainerTest.php (T007)
Task: ApplicationTest.php (T008)
Task: BootContextsTest.php (T009)

# Then parallel scaffolding:
Task: ContainerInterface + exceptions (T010)
Task: ServiceProvider base (T012)
Task: Corex facade (T016)
```

---

## Implementation Strategy

### MVP first
1. Phase 1 Setup → 2. Phase 2 Foundational → 3. Phase 3 US1 → **STOP & validate** (Scenarios 1–2).
   US1 alone is a usable, demonstrable bootstrap + container.

### Incremental delivery
US1 (boot+container) → add US2 (config) → add US3 (hooks) → add US4 (discovery). Each is an
independently testable increment; run its guard gate + quickstart scenario before moving on.

---

## Notes
- One task at a time (constitution Next Step Rule): after each implementation task, run guards, keep
  tests green, update PROGRESS/DECISIONS, then stop for review.
- `[P]` = different files, no incomplete dependency.
- Verify each test FAILS before implementing it.
- The Service Provider seam (T012/T013) is the load-bearing scalability mechanism — keep it generic;
  no module/add-on specifics leak into core.
