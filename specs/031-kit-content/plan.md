# Implementation Plan: Kits that build a real site (031)
**Branch**: `feature/031-kit-content` | **Date**: 2026-06-12 | **Spec**: [spec.md](./spec.md)

## Summary
`Blueprint::pages()` declares a kit's pages (title/slug/content/front), composing existing corex/* patterns.
A pure `KitPagePlanner` decides which to create (skip existing slugs). `BlueprintActivator` creates them
(`wp_insert_post`, a `corex_kit_page` marker, records ids in `corex_kit_seeded_pages`), sets the front page,
and runs alongside flags+modules. The soft reset (spec 025) removes exactly the tracked pages.

## Technical Context
PHP 8.3. Deps: spec-010/023 blueprints, spec-024 BlueprintActivator/SetupWizard, spec-025 reset, spec-009
patterns. Tests: Pest (planner pure; activator integration). Constraints: idempotent by slug; reversible by
marker; only existing patterns; page creation sanitized at the WP boundary.

## Constitution Check (v1.2.1)
- [x] III/IV — `KitPagePlanner` pure; `BlueprintActivator` the WP boundary; blueprints declare data.
- [x] VII — page creation via wp_insert_post (WP sanitizes); reset removal gated (admin/CLI).
- [x] IX — kits stay optional; pages only seeded when applied.
- [x] X — implements spec 031.
- [x] Guard Gate/DoD — wp-guard (insert/options), clean-code, test-guard; Pest planner + integration.

**Gate**: PASS.

## Design
- `Blueprint::pages(): array` (default []). `CompanyBlueprint`/`PortfolioBlueprint` declare real pages.
- `Corex\Kit\KitPagePlanner` (pure): `toCreate(list<page> $declared, list<string> $existingSlugs): list<page>`.
- `BlueprintActivator` (extended): `seedPages($pages)` — for each planned page, `wp_insert_post` (publish) +
  set `_corex_kit_page` meta + collect id; set front page if `front`; append ids to `corex_kit_seeded_pages`.
  Replaces the single seedDemoHome with the general seeder (the wizard's plan now carries `pages`).
- `SetupWizard::plan(name)` → include `pages` from the blueprint.
- Reset: the soft reset's demo removal extends to the `corex_kit_seeded_pages` ids (the reset already clears
  corex_* options; add a page-removal that trashes those ids + clears the option).

## FR → component map
| FR | Built in |
|---|---|
| FR-001/005 declared pages | `Blueprint::pages()` + `Company/Portfolio` blueprints |
| FR-002/004 create+track | `BlueprintActivator::seedPages()` (+ `corex_kit_seeded_pages`) |
| FR-003 planner | `Kit\KitPagePlanner` |
| FR-004 reset removal | extend spec-025 soft reset (trash tracked ids) |

## Project Structure
```text
addons/corex-kit-company/src/{Blueprint.php (pages()), KitPagePlanner.php, BlueprintActivator.php (seedPages)}
addons/corex-kit-company/src/Company/CompanyBlueprint.php (pages())
addons/corex-kit-portfolio/src/PortfolioBlueprint.php (pages())
packages/cli/src/Reset/... (kit-page removal) OR a shared marker the reset reads
tests/Unit/Kit/KitPagePlannerTest.php
```

## Complexity Tracking
The planner + tracked marker are justified (idempotent + reversible). Visual confirmation env-gated.
