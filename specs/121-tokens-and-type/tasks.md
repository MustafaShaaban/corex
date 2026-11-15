# Tasks: Tokens and type

**Input**: [spec.md](spec.md), [plan.md](plan.md)
**Tests**: requested by every story's acceptance criteria (unit, Jest and browser).

## Phase 1: Setup

- [x] T001 Scratch WordPress on 127.0.0.1:8921 (database corex_w121), bundles built, quality seed applied
- [x] T002 Before screenshots of every template at 1280 and 375px and of the login in specs/121-tokens-and-type/evidence/before/ (capture script specs/121-tokens-and-type/evidence/capture-templates.js)

## Phase 2: Foundational

- [x] T003 Brand contract in design/tokens/brand-contract.json, values from plugins/corex-core/assets/css/corex-admin-tokens.css (#460)

## Phase 3: User Story 1 - The theme, the admin and the emails agree (US-BRD-02, P1)

- [x] T004 [US1] Light and System-light blocks identical (shadows, overlay, backdrop, login card and field) in plugins/corex-core/assets/css/corex-admin-tokens.css (#461)
- [x] T005 [US1] RTL font swap covers body.corex-admin-screen in plugins/corex-core/assets/css/corex-admin-tokens.css
- [x] T006 [US1] tests/Unit/Theme/BrandContractTest.php: admin dark/light, email palette, EmailBrand constants and theme radius/motion against the contract, naming the token; Light vs System parity
- [x] T007 [US1] Browser check that Light and System-light draw the same overlay, shadow and login card, and that wp-admin loads no theme stylesheet, in tests/e2e/tokens-and-type.spec.js

## Phase 4: User Story 2 - The theme renders its own scales (US-BRD-03, P1)

- [x] T008 [US2] theme/theme.json: default sizes off, spacing slug 25, font sizes by role with 3xl and display, fluid range, h1–h6 elements, radius xs–lg/full, motion instant–slow, button radius sm (#462)
- [x] T009 [US2] Admin motion scale and spin token in plugins/corex-core/assets/css/corex-admin-tokens.css, corex-admin-shell.css, corex-admin-login.css
- [x] T010 [US2] tests/Unit/Theme/ThemeScalesTest.php and the existing DesignTokensTest/ThemeJsonTest updated
- [x] T011 [US2] Browser check of computed spacing presets, heading sizes, radius and transition duration in tests/e2e/tokens-and-type.spec.js
- [x] T012 [US2] Regenerate the token inventory (node scripts/generate-token-inventory.mjs --write) and fix the consumers it flags
- [x] T013 [US2] After screenshots in specs/121-tokens-and-type/evidence/after/ and the visible changes in specs/121-tokens-and-type/evidence/README.md and CHANGELOG.md

## Phase 5: User Story 3 - Arabic face on the public site (US-BRD-04, P1)

- [x] T014 [US3] theme/theme.json and theme/styles/*.json: Arabic faces with unicodeRange; styles.css :lang rules for body 1.8 and headings 1.3 (#463)
- [x] T015 [US3] Admin RTL line heights in plugins/corex-core/assets/css/corex-admin-tokens.css
- [x] T016 [US3] Browser checks: Arabic page computes Plex Arabic 1.8/1.3 and loads the files; English page loads none; mixed text keeps Latin in the Latin face, in tests/e2e/tokens-and-type.spec.js

## Phase 6: User Story 4 - One typeface plan everywhere (US-BRD-09, P2)

- [x] T017 [US4] --corex-admin-font-ui and its use on buttons, tabs, navigation and labels in the admin shell, settings tabs and login CSS (#464)
- [x] T018 [US4] theme.json: button element, navigation block and labels in the heading family
- [x] T019 [US4] tests/Unit/Theme/FontAssetContractTest.php counts both font folders and requires identical copies
- [x] T020 [US4] Browser check of paragraph/button/heading fonts on the front page, an admin screen and the login; email body font stays the system stack (unit)

## Phase 7: User Story 5 - Old logo retired (US-BRD-08, P2)

- [x] T021 [US5] Delete plugins/corex-config/assets/corex-logo.svg; update logo-manifest.json, plugins/corex-config/README.md, tests/Unit/Config/BrandingTest.php, specs/111-generator-tag/contracts/detection-rule.md (#465)

## Phase 8: User Story 6 - Proven in real Arabic (US-NFR-02, P1)

- [x] T022 [US6] scripts/i18n-arabic.mjs: compile .mo, check placeholders/markup, report untranslated template strings, write script-translation JSON per built bundle; wired into npm run build (#466)
- [x] T023 [US6] plugins/corex-core/src/I18n/ScriptTranslations.php registered by CoreServiceProvider; tests/Unit/I18n/ScriptTranslationsTest.php
- [x] T024 [US6] Arabic catalogue plugins/corex-core/languages/corex-ar.po and corex-ar.mo for every admin, account, form and email string
- [x] T025 [US6] tests/e2e/fixtures/corex-e2e-arabic.php and tests/e2e/quality/arabic-proof.spec.js; CI quality job installs the Arabic core pack (#467)
- [x] T026 [US6] specs/121-tokens-and-type/arabic-review.md: the reader's checklist and where the review is recorded
- [x] T027 [US6] tests/i18n-arabic.test.js (Jest) for the catalogue tools

## Phase 9: User Story 7 - Foundations page (US-DOC-07, P2)

- [x] T028 [US7] scripts/foundations-tokens.mjs and generated tables in docs-app/src/content/docs/design-system/foundations.md; docs-app prebuild runs --check (#468)
- [x] T029 [US7] tests/foundations-tokens.test.js

## Phase 10: Polish

- [x] T030 DECISIONS #460–#468, CHANGELOG [Unreleased] with Client impact (Muva, Perego, Mizzey), PROGRESS In flight
- [x] T031 Quality known failures: remove entries this change fixes, fix what it introduces
- [x] T032 Guards: clean-code-guard, wp-guard, test-guard, docs-guard
- [ ] T033 Merge origin/main, push, open the pull request, CI green

## Dependencies

T003 before T006. T008 before T011–T013. T022 before T024 and T025. Stories 1–5 and 7 are independent of story 6.
