---
description: "Task list for Theme + Design Tokens (spec 006)"
---

# Tasks: Theme + Design Tokens

**Input**: Design documents from `specs/006-theme-tokens/`

**Tests**: REQUIRED (constitution). The BrandResolver is unit-tested headlessly; theme.json + the
variation are validated as artifacts.

**Guard Gate (per story)**: `clean-code-guard` + `wp-guard` (production), `test-guard` (tests),
`docs-guard` (docs). ABSPATH guard on src class files; theme styling token-only / RTL / WCAG AA.

## Format: `[ID] [P?] [Story] Description` — resolver under `plugins/corex-core/src/Theme`; tokens in `theme/`.

---

## Phase 1: Setup

- [ ] T001 [P] Create `plugins/corex-core/config/theme.php` (`['brand_path' => '']`, ABSPATH guard); create `tests/Unit/Theme/`.

## Phase 2: User Story 2 — brand.json deep-merge resolver (Priority: P1) 🎯 MVP

- [ ] T002 [P] [US2] Write failing `tests/Unit/Theme/BrandResolverTest.php`: `merge` (no override → defaults; one nested override changes only that key, siblings intact; unknown path added; list replaced); `read` (missing → [], malformed JSON → [] + logged) (FR-004, FR-005, SC-003, SC-004).
- [ ] T003 [US2] Implement `plugins/corex-core/src/Theme/BrandResolver.php` (recursive merge; read+decode with BootLogger on malformed).
- [ ] T004 [US2] Guard gate.

## Phase 3: User Story 1 — theme.json token source (Priority: P1)

- [ ] T005 [US1] Ensure `theme/theme.json` (v3) defines color/typography(fontSizes)/spacing(spacingSizes)/layout token palettes (extend the existing file as needed); styling stays token-only.
- [ ] T006 [US1] Write `tests/Unit/Theme/ThemeJsonTest.php`: `theme/theme.json` is valid JSON with `version` (v3) and defines the token palettes (FR-001, FR-003, SC-002).
- [ ] T007 [US1] Guard gate (no hardcoded values in token-consuming styling).

## Phase 4: User Story 3 — style variation (Priority: P2)

- [ ] T008 [P] [US3] Create `theme/styles/dark.json` — a v3 style variation (token-only, dark palette overriding theme.json colors).
- [ ] T009 [US3] Extend the validity test: `theme/styles/dark.json` is valid v3 JSON and token-only (FR-008, FR-009).

## Phase 5: User Story 4 — apply overrides + skin discipline (Priority: P2)

- [ ] T010 [US4] Implement `plugins/corex-core/src/Theme/ThemeServiceProvider.php` — bind BrandResolver; `boot()` adds the `wp_theme_json_data_theme` filter reading brand.json (path from `config('theme.brand_path')` or the active theme root) and merging it; add to `Boot`'s provider list.
- [ ] T011 [P] [US4] Write `tests/Unit/Theme/SkinDisciplineTest.php`: the `theme/` tree contains no `register_post_type`/`register_taxonomy`/plugin bootstrap (grep-style assertion over theme PHP, if any) — the theme is a skin (FR-010, SC-005).
- [ ] T012 [US4] Guard gate.

## Phase 6: Polish

- [ ] T013 [P] Update `plugins/corex-core/README.md` with a theme/tokens + brand.json section; docs-guard.
- [ ] T014 Final guard pass; confirm the headless suite passes with no optional plugins (SC-006); site still boots; brand.json override visible on the live site (manual/wp eval).
- [ ] T015 Update `PROGRESS.md` + `DECISIONS.md`; verify Definition of Done.

---

## Dependencies & Execution Order

Setup → US2 BrandResolver (headless MVP) → US1 theme.json validity → US3 variation → US4
ThemeServiceProvider (filter) + skin test → Polish.

## Notes
- The resolver is pure/headless; only `ThemeServiceProvider` touches WordPress (the
  `wp_theme_json_data_theme` filter). The theme stays logic-free (resolver in corex-core). Token-only
  styling, logical CSS/RTL, WCAG AA. One task at a time; guards before each commit.
