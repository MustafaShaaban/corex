# Tasks: Design Language System

**Feature**: 051-design-language-system · **Branch**: `feature/051-design-language-system`

**Story legend**: US1 = catalog/taxonomy (P1, MVP) · US2 = alert/badge components (P1) · US3 = docs (P2) ·
US4 = corex-ui home (P2).

- [x] T001 [P] Pest `tests/Unit/Ui/DesignSystemCatalogTest.php` — five categories; blockNames() drift-checked against the on-disk corex/* block.json (no invented/stale entry).
- [x] T002 Implement `Corex\Ui\DesignSystemCatalog` (pure) until T001 green.
- [x] T003 [US2] Add the `corex/alert` block (block.json + index.js + style.scss + AlertRenderer — accessible role, info/success/warning/error variant, token-only, RTL) + Pest on the renderer.
- [x] T004 [US2] Add the `corex/badge` block (block.json + index.js + style.scss + BadgeRenderer — labelled token span) + Pest on the renderer.
- [x] T005 [US3/US4] Docs: docs-app `guides/design-system.md` (taxonomy + catalog + guidelines; corex-ui is the home). Build the blocks.
- [x] T006 Guard Gate + suites green; DECISIONS #85 + PROGRESS; commit → PR → CI → merge.
