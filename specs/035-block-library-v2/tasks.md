# Tasks: Block library expansion v2 (035)

**Forward, TDD-ordered.** Renderers are the headless core (Pest); the JS edit shapes get Jest. Each block is an
independent increment. FR→component map in `plan.md`.

## Phase 1: Setup
- [x] T001 Confirm the corex-blocks engine auto-discovers `addons/corex-ui/src/Blocks/*/block.json` and the spec-018 build picks up new dirs (no config change). Confirm the `corex` inserter category exists.

## Phase 2: US1 — Hero (P1) 🎯 MVP
- [x] T002 [US1] Write `tests/Unit/Ui/ComponentBlocksV2Test.php` hero cases (RED): renders a section with heading + escaped fields; CTA gated on text+url; background `<img>` with alt + esc_url; empty title → ''.
- [x] T003 [US1] `Blocks/hero/{block.json,index.js,style.scss}` + `HeroRenderer.php` to pass T002.

## Phase 3: US2 — CTA (P1)
- [x] T004 [US2] Add CTA cases to the V2 test (RED): banner with heading; button gated on text+url; empty → ''.
- [x] T005 [US2] `Blocks/cta/{block.json,index.js,style.scss}` + `CtaRenderer.php`.

## Phase 4: US3 — Team (P2)
- [x] T006 [US3] Add Team cases (RED): grid of figures from `members[]`; photo `<img>` esc_url/alt/lazy; nameless skipped; empty → ''.
- [x] T007 [US3] `Blocks/team/{block.json,index.js,style.scss}` + `TeamRenderer.php`.

## Phase 5: US4 — Gallery (P2)
- [x] T008 [US4] Add Gallery cases (RED): CSS grid of figures from `images[]`; url-less skipped; caption RichText; empty → ''.
- [x] T009 [US4] `Blocks/gallery/{block.json,index.js,style.scss}` + `GalleryRenderer.php`.

## Phase 6: US5 — Tabs (P2)
- [x] T010 [US5] Add Tabs cases (RED): CSS-only radio/label tabs from `tabs[]`; label-less skipped; first tab checked; empty → ''.
- [x] T011 [US5] `Blocks/tabs/{block.json,index.js,style.scss}` (no viewScript) + `TabsRenderer.php`.

## Phase 7: Jest + build
- [x] T012 [P] Jest edit-shape tests for each block (`*/index.test.js`): RichText regions present; add/remove mutate the array attr; media select stores `{id,url,alt}`.
- [x] T013 `npm run build` (corex-ui) compiles all 5 blocks to `build/blocks/*`; confirm assets emitted.

## Phase 8: Polish
- [x] T014 Guard Gate: wp-guard (escaping per field, esc_url media, lazy img, no inline px), clean-code, test-guard; fix.
- [x] T015 [P] `composer test` + Jest green; token-only scan clean across all new renderers.
- [x] T016 Docs: blocks cookbook/guide lists the new set + the inline/media/no-JS notes; corex-ui README; **docs-app** guides/blocks; PROGRESS + DECISIONS #69; NEXT STEP.
