# Tasks: Team ops & distribution

**Feature**: 050-team-ops-distribution · **Branch**: `feature/050-team-ops-distribution`

**Story legend**: US1 = package:update (P1, MVP) · US2 = compliance:check (P1) · US3 = docs commands (P2) ·
US4 = deployment docs (P2).

- [x] T001 [P] Pest `tests/Unit/Release/ReleasePackagePlanTest.php` — includes framework src, excludes tests/specs/node_modules/client; manifest in spec-034 format, no secret.
- [x] T002 Implement `Corex\Cli\Release\ReleasePackagePlan` (pure) until T001 green.
- [x] T003 [P] Pest `tests/Unit/Release/ComplianceCheckTest.php` — passes client/docs/specs; fails (names) a framework-path change by prefix (not substring); override allows.
- [x] T004 Implement `Corex\Cli\Release\ComplianceCheck` (pure) until T003 green.
- [x] T005 [US1/US2/US3] Wire `package:update` + `compliance:check` + `docs:sync`/`docs:serve`/`docs:open` into CliServiceProvider (WP-CLI-gated, thin boundaries over the pure cores).
- [x] T006 [US4] Docs: docs-app `guides/deployment.md` (Azure DevOps per-site repo + App Service + branch policies + compliance) + CLI README.
- [x] T007 Guard Gate + suites green; DECISIONS #84 + PROGRESS; commit → PR → CI → merge.
