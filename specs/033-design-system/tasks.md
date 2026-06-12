# Tasks: Design system overhaul (033)

**Forward, TDD-ordered.** Token presence + JSON validity + token-only scans are the headless tests; the visual
result is env-gated. FR→component map in `plan.md`.

## Phase 1: Setup
- [x] T001 Confirm spec-006 theme tokens + the existing token-only scans (ThemeTemplatesTest / pattern scans) are the model; note the existing slugs to preserve.

## Phase 2: US1 — richer tokens (P1) 🎯 MVP
- [x] T002 Write `tests/Unit/Theme/DesignTokensTest.php` (RED): theme.json is valid JSON; palette includes the new + existing slugs; type scale + spacing expanded; `shadow.presets` + `custom.radius` present.
- [x] T003 Expand `theme/theme.json` settings additively: palette (+ surface-alt/border/ink-soft + success/warning/error/info), fontSizes (+ xs/base/xl/2xl), spacingSizes (+ 10/20/40/60/70), `shadow.presets` (sm/md/lg), `custom.radius` (sm/md/lg/full). Keep all existing slugs.

## Phase 3: US2 — polished styles + block depth (P1)
- [x] T004 [US2] Add `theme.json` `styles.elements` (button/link/heading) using token colors + radius + spacing.
- [x] T005 [US2] Update the block SCSS (posts/stat/testimonial/pricing/accordion + the form) to use `--wp--preset--shadow--md` + `--wp--custom--radius--md` (rounded, elevated) — token-only, logical CSS.

## Phase 4: US3 — style variation (P2)
- [x] T006 [US3] Add `theme/styles/editorial.json` (a token-only variation alongside dark).

## Phase 5: Polish
- [x] T007 Guard Gate: clean-code (SCSS), test-guard; token-only scans stay green; all JSON valid.
- [x] T008 [P] `composer test` green; `npm run build` compiles the updated SCSS; record.
- [x] T009 Docs: theme README / **docs-app** branding+theme note (the richer design system + variations); PROGRESS + DECISIONS; NEXT STEP.
