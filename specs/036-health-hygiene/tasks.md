# Tasks: Health-check, versioning, i18n & OSS hygiene (036)

**Forward, TDD-ordered.** `HealthReport` + `VersionPlan` are the headless cores (Pest); Site Health + WP-CLI are
thin boundaries. FR→component map in `plan.md`.

## Phase 1: Setup
- [x] T001 Confirm the CLI gate (`class_exists('WP_CLI')`) + provider wiring points (CliServiceProvider, CoreServiceProvider::boot) and the current plugin/theme version headers (the drift to fix).

## Phase 2: US1 — Health check (P1) 🎯 MVP
- [x] T002 [US1] Write `tests/Unit/Health/HealthReportTest.php` (RED): aggregates probe results; `overall()` returns the worst status (critical > recommended > good); empty → good.
- [x] T003 [US1] Implement `Health/{HealthProbe,ProbeResult,HealthReport,HealthStatus}.php` to pass T002.
- [x] T004 [US1] Write `tests/Unit/Health/ProbesTest.php` (RED) + implement `Health/Probes/{PhpVersion,WpVersion,ThemeActive,BrandPresent,UploadsWritable}Probe.php` (injected/guarded WP reads).
- [x] T005 [US1] `Health/HealthModule.php` (registers `site_status_tests`) + `Commands/DoctorCommand.php` (render + non-zero exit on critical); wire in CoreServiceProvider::boot + CliServiceProvider.

## Phase 3: US2 — Versioning alignment (P1)
- [x] T006 [US2] Write `tests/Unit/Release/VersionPlanTest.php` (RED): valid semver → per-file header/constant edits; invalid version rejected; idempotent when already aligned.
- [x] T007 [US2] Implement `packages/cli/src/Release/VersionPlan.php` (pure) + `Commands/VersionCommand.php` (apply/`--dry-run`); wire in CliServiceProvider.

## Phase 4: US3 — i18n (P2)
- [x] T008 [US3] Add a `composer i18n:pot` script (wp-cli i18n make-pot) + a `languages/` dir; load + use the consistent `corex` text domain (on `init`).

## Phase 5: US4 — OSS hygiene (P2)
- [x] T009 [US4] Add `LICENSE` (GPL-2.0-or-later), `CODE_OF_CONDUCT.md`, `SECURITY.md`, `.editorconfig`, `.github/ISSUE_TEMPLATE/{bug_report,feature_request}.md`, `.github/PULL_REQUEST_TEMPLATE.md`; keep `CONTRIBUTING.md` accurate.

## Phase 6: Polish
- [x] T010 Guard Gate: clean-code + wp-guard (Site Health registration, file writes) + test-guard; fix.
- [x] T011 [P] `composer test` green (350); composer valid; verify live (env-gated): `wp corex doctor`, `wp corex version --dry-run`.
- [x] T012 Docs: a health + release + i18n guide (docs-app `guides/cli`); corex-core/CLI README; PROGRESS + DECISIONS #70; NEXT STEP.
