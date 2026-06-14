# Tasks: Asset manager & environments

**Feature**: 047-asset-manager · **Branch**: `feature/047-asset-manager`
**Input**: [spec.md](./spec.md) · [plan.md](./plan.md)

**Tests**: REQUIRED (Pest; live enqueue/source-map env-gated).

**Story legend**: US1 = url/path/version helpers (P1, MVP) · US2 = environment strategy (P1) · US3 = manifest (P2) ·
US4 = assets:doctor/cache:clear (P2).

---

## Phase 2: Foundational pure cores (block the stories)

- [x] T001 [P] Pest `tests/Unit/Assets/AssetEnvironmentTest.php` — config value → local/staging/production; unset → production-safe.
- [x] T002 Implement `Corex\Assets\AssetEnvironment` (pure) until T001 green.
- [x] T003 [P] Pest `tests/Unit/Assets/BuildManifestTest.php` — lookup(source) → {file,hash}; malformed/absent → null; read once.
- [x] T004 Implement `Corex\Assets\BuildManifest` (pure) until T003 green.

## Phase 3: US1 + US2 — version helpers + environment strategy (P1, MVP)

- [x] T005 [P] [US1] Pest `tests/Unit/Assets/AssetVersionTest.php` — filemtime in local; manifest hash in prod; framework/site-version fallback; missing asset → fallback; `../` traversal rejected.
- [x] T006 [US1] Implement `Corex\Assets\AssetVersion` (pure: version(relative, mtime, ?manifestHash, env, fallback) + traversal guard) until T005 green.
- [x] T007 [US1] Implement `Corex\Assets\AssetManager` (boundary: `url()`/`path()`/`version()` via `plugins_url` + the spec-040 normalisation + filemtime + the pure cores) + `AssetsServiceProvider` (bind; wire env/manifest from Config). Wire into Boot.

## Phase 4: US3 — manifest resolution (P2)

- [x] T008 [US3] `AssetManager::url()`/`version()` resolve a built asset to its hashed file + hash via `BuildManifest`; fall back to plain file + filemtime/version with no manifest. Pest on the resolution dispatch (manifest injected).

## Phase 5: US4 — assets:doctor + cache:clear (P2)

- [x] T009 [P] [US4] Pest `tests/Unit/Assets/AssetReportTest.php` — reports env, manifest presence, sample resolutions, source-map exposure; no secret.
- [x] T010 [US4] Implement `Corex\Assets\AssetReport` (pure) + `AssetsCommand` (`assets:doctor` + `cache:clear`, WP-CLI-gated) until T009 green; wire into CliServiceProvider.

## Phase 6: Polish

- [x] T011 [P] Docs: docs-app `guides/assets.md` (the helpers, environments, cache-busting, manifest); corex-core README.
- [x] T012 Guard Gate (clean-code, wp-guard — traversal guard/escaped URLs/gated CLI/no-secret, test-guard, docs-guard).
- [x] T013 Suites green (`composer test`); record counts. Live enqueue/source-map env-gated.
- [ ] T014 Update `PROGRESS.md` + `DECISIONS.md` #81; NEXT STEP. Commit → PR → CI → merge.

---

## Dependencies & order

- Foundational (T001–T004) block the stories. **MVP = US1+US2** (helpers + environment strategy). US3 (manifest)
  builds on them; US4 (commands) on the report. Polish last.
- TDD: T001→T002, T003→T004, T005→T006, T009→T010.
- **Parallel**: T001/T003/T005/T009 (`[P]`), docs T011.
