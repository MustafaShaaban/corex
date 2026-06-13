# Tasks: Data management pro

**Feature**: 045-data-management-pro · **Branch**: `feature/045-data-management-pro`
**Input**: [spec.md](./spec.md) · [plan.md](./plan.md) · [data-model.md](./data-model.md) ·
[contracts/data-api.md](./contracts/data-api.md) · [quickstart.md](./quickstart.md)

**Tests**: REQUIRED (Pest + Jest; Playwright smoke env-gated).

**Story legend**: US1 = search/filter/sort/paginate (P1, MVP) · US2 = CSV export (P1) · US3 = readable detail (P2) ·
US4 = SubmissionStore seam (P2).

---

## Phase 2: Foundational (pure cores — block the stories)

- [x] T001 [P] Pest `tests/Unit/Data/DataQueryTest.php` — clamps page ≥ 1, per_page ≤ max; carries search/filters/sort/dir.
- [x] T002 Implement `Corex\Config\Data\DataQuery` (pure VO) until T001 green.
- [x] T003 [P] Pest `tests/Unit/Data/CsvWriterTest.php` — header + escaped rows (comma/quote/newline quoted, quotes doubled); zero rows → header only; only declared columns.
- [x] T004 Implement `Corex\Config\Data\CsvWriter` (pure, RFC-4180) until T003 green.

## Phase 3: US1 — search/filter/sort/paginate (P1, MVP)

- [x] T005 [US1] Extend the `DataSource` contract: `rows(DataQuery)` + `total(DataQuery)` + `record(int): ?array` (keep columns/key/label/delete).
- [x] T006 [US1] Update `SubmissionsSource` + its reader to answer `DataQuery` (substring search, form filter, date sort) — prepared/bounded; Pest on the shaping (reader injected).
- [ ] T007 [US1] Update `TableDataSource` + `WpTableDataReader` to answer `DataQuery` (prepared `%i`/`%s`/`%d`, bounded); keep existing tests green.
- [x] T008 [US1] `DataController` accepts query params (sanitised → `DataQuery`); responses already envelope-shaped (043). Pest for the param→query mapping.
- [ ] T009 [US1] React Data app (`src/admin/index.js`): search box + form filter + sortable headers + pagination via `window.Corex.api`; rebuild.

## Phase 4: US2 — CSV export (P1)

- [x] T010 [US2] `DataExportController` — `GET corex/v1/data/{source}/export`, AdminGuard cap+nonce, streams `CsvWriter` output for the current query, bounded to a documented cap; no secret. Pest for the CSV body + a no-secret assertion.
- [ ] T011 [US2] Export button in the Data app (downloads the filtered CSV).

## Phase 5: US3 — readable detail (P2)

- [x] T012 [US3] `record(int)` returns label→value fields (form labels where known, else key); Pest on the shaping.
- [ ] T013 [US3] Detail view in the Data app (open a row → label→value fields + form + date); graceful empty values.

## Phase 6: US4 — SubmissionStore seam (P2)

- [x] T014 [P] [US4] Pest `tests/Unit/Forms/PostMetaSubmissionStoreTest.php` — save/query/find/delete shaping over the corex_submission storage (reader injected).
- [x] T015 [US4] Define `Corex\Forms\Submission\SubmissionStore` (interface) + `PostMetaSubmissionStore` (default driver) until T014 green; bind it.
- [x] T016 [US4] Refactor `StoreSubmissionListener` (and the Data submissions reader) to go through the seam — behavior unchanged; existing submission tests stay green.

## Phase 7: Polish

- [x] T017 [P] Docs: docs-app queries/data guide — the query/export/detail + the store-seam boundary (post-meta now, custom-table later, out of scope). corex-config README.
- [x] T018 Guard Gate (clean-code, wp-guard — prepared search/cap+nonce/escaped/no-secret/bounded export, test-guard, docs-guard). Fix findings.
- [x] T019 Suites green (`composer test` + `npm run test:js`); record counts. Playwright smoke env-gated.
- [ ] T020 Update `PROGRESS.md` + `DECISIONS.md` #79; NEXT STEP. Commit → PR → CI → merge.

---

## Dependencies & order

- Foundational (T001–T004) block the stories. **MVP = Foundational + US1.** US2/US3 build on US1's query/record;
  US4 (seam) is independent and can land in parallel. Polish last.
- TDD: T001→T002, T003→T004, and the source/store shaping tests precede their implementations.
- **Parallel**: T001/T003 (`[P]`), T014 (`[P]`), docs T017.
