# Tasks: Kits that build a real site (031)

**Forward, TDD-ordered.** Pure page planner is headless-tested; page creation + reset removal are the WP
boundary. FR→component map in `plan.md`.

## Phase 1: Setup
- [x] T001 Confirm the spec-024 BlueprintActivator/SetupWizard, the spec-009 patterns, and the spec-025 soft reset are the integration points.

## Phase 2: Foundational — the pure page planner
- [x] T002 Write `tests/Unit/Kit/KitPagePlannerTest.php` (RED): `toCreate` skips existing slugs; empty when all exist; returns all when none exist.
- [x] T003 Implement `addons/corex-kit-company/src/KitPagePlanner.php` (pure) to pass T002.

## Phase 3: US1 — declare + seed pages (P1) 🎯 MVP
- [x] T004 [US1] Add `Blueprint::pages(): array` (default []) + declare real pages in `CompanyBlueprint` (home/about/contact) and `PortfolioBlueprint` (home/projects), composing existing corex/* patterns.
- [x] T005 [US1] Extend `BlueprintActivator` with `seedPages()` (plan via KitPagePlanner → wp_insert_post publish + `_corex_kit_page` marker + record `corex_kit_seeded_pages` + set front page); call it from `apply()` with the plan's pages.
- [x] T006 [US1] Add `pages` to `SetupWizard::plan()` so the wizard carries them.

## Phase 4: US2 — idempotent + reversible (P1)
- [x] T007 [US2] Idempotency is the planner (T002/T003). Extend the soft reset (spec 025) to trash the `corex_kit_seeded_pages` ids + clear the option (kit pages removed exactly).

## Phase 5: Polish
- [x] T008 Guard Gate: wp-guard (insert/meta/options), clean-code, test-guard; fix.
- [x] T009 [P] `composer test` green; verify live: applying the company kit creates home/about/contact + sets front; re-apply no dup; reset removes them.
- [x] T010 Docs: kit READMEs + **docs-app** (kits create pages); PROGRESS + DECISIONS; NEXT STEP.
