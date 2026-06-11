# Tasks: Add-on manager admin screen (026)

**Forward spec** — TDD-ordered (Corex DoD). Pure registry + manager are foundational; the screen + activator
are the WP boundary, gated by the shared `AdminGuard`. FR→component map is in `plan.md`; the screen surface is
in `contracts/addons-screen-contract.md`.

## Phase 1: Setup

- [x] T001 Create `plugins/corex-config/src/Addons/` and confirm the established admin pattern (AdminDashboard / SetupWizardScreen + `Corex\Security\Admin\AdminGuard`) is the model.

## Phase 2: Foundational (pure value objects + registry + manager — blocks all stories)

- [x] T002 [P] Create value objects in `plugins/corex-config/src/Addons/`: `Addon` (slug/pluginFile/label/flag/requires), `AddonState` (activeSlugs/enabledFlags + `isActive`/`flagOn`), `AddonView` (addon + installed/active/flagOn/blockedReason) per `data-model.md`.
- [x] T003 Write `tests/Unit/Config/AddonRegistryTest.php` (RED): the registry lists the known add-ons with correct plugin files and the kit→`corex-ui` `requires` edges.
- [x] T004 Implement `plugins/corex-config/src/Addons/AddonRegistry.php` (`all()`/`find()`) to pass T003 (pure).
- [x] T005 Write `tests/Unit/Config/AddonManagerTest.php` (RED): `canDisable` false when an active add-on requires the target (with `blockingDependents`); `canEnable` false when a required dep is inactive (with `missingDependencies`); `views()` carries the right state + `blockedReason`.
- [x] T006 Implement `plugins/corex-config/src/Addons/AddonManager.php` to pass T005 (pure; no WP).

**Checkpoint**: the dependency-aware toggle rules + view model are proven headlessly.

## Phase 3: User Story 1 — See and toggle add-ons from one screen (P1) 🎯 MVP

**Goal**: list every add-on with state; enable/disable toggles plugin + flag together, nonce + cap gated.
**Independent test**: quickstart §2 (activator) + §3 (registration) + §4 (browser).

- [x] T007 [US1] Write `tests/Integration/Config/AddonActivatorTest.php` (RED): on `./wp`, `AddonActivator::enable()`/`disable()` set/clear `corex_features_<flag>` for a flagged add-on (and toggle a plugin reversibly).
- [x] T008 [US1] Implement `plugins/corex-config/src/Addons/AddonActivator.php` (`enable`/`disable` → `activate_plugins`/`deactivate_plugins` + set/clear flag option) to pass T007.
- [x] T009 [US1] Implement `plugins/corex-config/src/Addons/AddonsScreen.php`: submenu under `corex-settings`; `render()` (AdminGuard authorized) lists `AddonView`s with per-add-on enable/disable forms (nonce + slug + action); all output escaped + i18n + RTL.
- [x] T010 [US1] Register the screen in `plugins/corex-config/src/ConfigServiceProvider.php` (like `AdminDashboard`); bind registry/manager/activator.

**Checkpoint**: the screen lists add-ons and toggles plugin + flag together, gated.

## Phase 4: User Story 2 — Dependency protection (P1)

**Goal**: refuse a toggle that would break a dependency; explain why; surface the constraint in the list.
**Independent test**: quickstart §1 (manager rules) + §4 (browser refusal).

- [x] T011 [US2] Implement `AddonsScreen::maybeToggle()`: `AdminGuard::verifiedPost` → resolve add-on → for enable, gate on `AddonManager::canEnable` (else notice naming the missing dep); for disable, gate on `canDisable` (else notice naming the dependent); call the activator only when allowed (FR-004).
- [x] T012 [US2] In `render()`, show each blocked add-on's `blockedReason` so the constraint is visible before acting (FR-004); show "Not installed" + no toggle for an uninstalled add-on (FR-005).

**Checkpoint**: it is impossible to break a dependency from the screen; the reasons are visible.

## Phase 5: Polish & cross-cutting

- [x] T013 Run the Guard Gate: `clean-code-guard` (registry/manager/screen) + `wp-guard` (screen escaping + nonce/cap via AdminGuard, activator plugin/option writes) + `test-guard`; fix any violation.
- [x] T014 [P] Document the Add-ons screen in `plugins/corex-config/README.md` (per `contracts/addons-screen-contract.md`).
- [x] T015 [P] Verify on `./wp`: the submenu registers and the page lists add-ons (`wp eval do_action('admin_menu')`); record. (Full browser toggle is the Apache-gated smoke.)
- [x] T016 Update `PROGRESS.md` + add a `DECISIONS.md` entry (dependency policy: refuse + explain); end with NEXT STEP.

## Dependencies

- Phase 2 (registry + manager) blocks all stories. US1 (list + toggle) is the MVP; US2 (dependency protection)
  reuses the same manager rules in the apply path. US1 → US2 (shared `AddonsScreen`).

## Implementation strategy

MVP = US1 (list + toggle), usable on its own; US2 hardens it with the dependency rules (already in the pure
manager from Phase 2, surfaced in the screen). The pure registry + manager are written test-first so the
dependency property is proven before any WP code.

## Parallel opportunities

- T002 (value objects) is [P] within Phase 2.
- T014 (README) and T015 (verify) are [P] in polish.
