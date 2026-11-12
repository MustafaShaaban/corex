# Tasks: A CoreX site says it runs CoreX

**Spec**: [spec.md](spec.md) · **Plan**: [plan.md](plan.md) · **Branch**: `spec/111-generator-tag`

Tests are written first and seen failing (constitution, Definition of Done). The Guard Gate runs on
the diff before it is presented.

## Phase 1 — The mark (User Story 1, P1)

- [x] T001 [US1] Unit tests `tests/Unit/Theme/GeneratorTagTest.php`: markup is the generator meta
  naming CoreX; holds no digit; filter off gives `''`; value goes through `esc_attr`. See them fail.
- [x] T002 [US1] `plugins/corex-core/src/Theme/GeneratorTag.php` and its binding and hook in
  `ThemeServiceProvider`. T001 passes.
- [x] T003 [US1] Integration test `tests/Integration/Theme/GeneratorTagTest.php` on real `./wp`:
  `wp_head` prints exactly one mark; still one with `wp_generator` removed; `print` is hooked on
  `wp_head` and on no other hook.

## Phase 2 — Turned off (User Story 2, P2)

- [x] T004 [US2] Integration: with a `corex_generator_tag` callback answering false, `wp_head`
  prints no mark; removed, the mark is back.

## Phase 3 — Coming soon (User Story 3, P2)

- [x] T005 [US3] `ComingSoonHeadTest` "ordinary page" asserts CoreX's mark; run the file and see "prints
  none of them on the coming-soon page" fail.
- [x] T006 [US3] `ComingSoonHead::quiet()` adds the filter at `PHP_INT_MAX`. T005 passes.
- [x] T007 [US3] `tests/e2e/coming-soon.spec.js`: the administrator's real site in the mode carries
  CoreX's mark (the visitor's page already asserts none).

## Phase 4 — Rule and docs (User Story 4, P3)

- [x] T008 [US4] `contracts/detection-rule.md` holds the rule, the lists, the ten-site requirement.
- [x] T009 Docs: `docs/en/03-operations/security.md` section; `CHANGELOG.md` Unreleased.
- [x] T010 E2E: a plain front-end page carries exactly one CoreX mark (SC-001).

## Phase 5 — Close

- [x] T011 Guards: `clean-code-guard`, `wp-guard` (production), `test-guard` (tests), `docs-guard`
  (docs).
- [x] T012 `PROGRESS.md`, `DECISIONS.md` #372; open the pull request.
