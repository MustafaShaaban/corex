# Tasks: Self-update mechanism + distribution (034)

**Forward, TDD-ordered.** The pure UpdateChecker is the headless core; the WP hooks + remote fetch are the
boundary (live update-from-admin env-gated). FR→component map in `plan.md`.

## Phase 1: Setup
- [x] T001 Confirm spec-001 Config (`updates.endpoint`) + WP's plugin update API (`pre_set_site_transient_update_plugins`, `plugins_api`, `Update URI`) are the integration points.

## Phase 2: US1 — the update checker + service (P1) 🎯 MVP
- [x] T002 Write `tests/Unit/Update/UpdateCheckerTest.php` (RED): returns update info iff the manifest version is newer (semver); null when equal/older/missing.
- [x] T003 Implement `plugins/corex-core/src/Update/UpdateChecker.php` (pure) to pass T002.
- [x] T004 Write `tests/Unit/Update/UpdateServiceTest.php` (RED): with a stubbed manifest, `checkUpdates()` injects the update into the transient; a missing/malformed manifest is a no-op (fail-safe).
- [x] T005 Implement `Update/UpdateService.php` (hooks + `wp_remote_get` fetch + inject + plugins_api details, fail-safe) + bind/boot in `CoreServiceProvider`.
- [x] T006 Add the `Update URI` header to `plugins/corex-core/corex-core.php` + `updates.endpoint` default (empty) to `config/app.php`.

## Phase 3: US2+US3 — safe boundary + distribution (P1/P2)
- [x] T007 [US2/US3] Author `docs/en/05-deployment/updates-and-distribution.md`: how to publish a manifest + package (GitHub Releases / static host), configure `updates.endpoint`, and the **safe-edit boundary** (framework updates vs `corex-app/`/`brand.json`/content/data which are never touched). Link from the deployment index.

## Phase 4: Polish
- [x] T008 Guard Gate: wp-guard (remote get, transient, no secret), clean-code, test-guard; fix.
- [x] T009 [P] `composer test` green; verify live: the update hooks register; a stubbed newer manifest surfaces an update object; an empty endpoint is a no-op.
- [x] T010 Docs: corex-core README + **docs-app** (self-update + distribution + safe-edit boundary); PROGRESS + DECISIONS; NEXT STEP.
