---
description: "Dependency-ordered tasks for Spec 057 Brand Tokens and Logo System"
---

# Tasks: Brand Tokens and Logo System

**Input**: Design documents from `specs/057-brand-tokens-logo-system/`

**Prerequisites**: `spec.md`, `plan.md`, `research.md`, `data-model.md`, `contracts/`, and `quickstart.md`

**Tests**: Required. Write and run the named contract/regression tests before the corresponding implementation task,
record the expected RED result, then make the smallest implementation change that turns it GREEN.

**Asset gates**: Font integration is blocked until authoritative files and provenance are approved. Logo integration
is blocked until the owner-approved vector package and provenance manifest exist. Gate tasks may run and record
`BLOCKED`; blocked implementation tasks must not be represented as completed.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: May run in parallel because it owns different files and has no unmet dependency.
- **[Story]**: Maps work to the independently testable user story from `spec.md`.
- Every task names its owned path. Update ownership before editing overlapping files.

## Phase 1: Setup and Repository Inventory

**Purpose**: Establish the reviewable baseline before tests or implementation change any token consumer.

- [ ] T001 Run `.specify/scripts/powershell/check-prerequisites.ps1 -Json`, confirm WordPress recognition/readiness for later implementation, and record actual PASS/BLOCKED/ENVIRONMENT-GATED baseline evidence in `specs/057-brand-tokens-logo-system/inventories/baseline.md`
- [ ] T002 Create the inventory field/schema guide from `data-model.md` in `specs/057-brand-tokens-logo-system/inventories/README.md`
- [ ] T003 [P] Inventory all definitions and JSON paths in `theme/theme.json` in `specs/057-brand-tokens-logo-system/inventories/definitions.json`
- [ ] T004 [P] Inventory every palette/font/setting override in `theme/styles/dark.json` and `theme/styles/editorial.json` in `specs/057-brand-tokens-logo-system/inventories/variations.json`
- [ ] T005 [P] Inventory exact WordPress-generated preset/custom-property names for every definition in `specs/057-brand-tokens-logo-system/inventories/generated-properties.json`
- [ ] T006 [P] Inventory CSS/SCSS/JSON/PHP/JS consumers under `theme/`, `plugins/`, `addons/`, and `packages/` in `specs/057-brand-tokens-logo-system/inventories/consumers.json`
- [ ] T007 [P] Inventory documentation examples, current `brand.json` shapes, and existing theme/brand fixtures in `specs/057-brand-tokens-logo-system/inventories/docs-and-brand.json`
- [ ] T008 [P] Inventory wp-admin fallback literals, repeated fallback chains, scope selectors, enqueue owners, and legacy alias references in `specs/057-brand-tokens-logo-system/inventories/admin-and-aliases.json`
- [ ] T009 Reconcile T003–T008 into retained/added/aliased/migrated/deprecated classifications, owner batches, compatibility windows, and font/logo blocker states in `specs/057-brand-tokens-logo-system/inventories/classifications.json`

**Checkpoint**: The baseline inventory is complete and reviewed. No token value or consumer has changed.

---

## Phase 2: Foundational Contract Tests

**Purpose**: Add failing, deterministic gates before any implementation. This phase blocks every user story.

**⚠️ CRITICAL**: Run each focused test and record its expected RED evidence in
`specs/057-brand-tokens-logo-system/inventories/baseline.md` before starting Phase 3.

- [ ] T010 [P] Add canonical-authority and duplicate-definition tests for `theme/theme.json` and inventory records in `tests/Unit/Theme/TokenInventoryTest.php`
- [ ] T011 [P] Add undefined WordPress custom-property reference and unknown-classification tests across repository consumers in `tests/Unit/Theme/TokenConsumerContractTest.php`
- [ ] T012 Add raw design-value tests with explicit centralized-admin and functional-layout allowances in `tests/Unit/Theme/TokenConsumerContractTest.php`
- [ ] T013 [P] Add required semantic-group coverage tests for surfaces, text, borders, accent, status, overlay, selection, focus, radius, spacing, shadow/elevation, motion, and z in `tests/Unit/Theme/ModeMappingTest.php`
- [ ] T014 Add complete default/light, dark, and editorial mapping tests, including complete palette/font replacement lists, in `tests/Unit/Theme/ModeMappingTest.php`
- [ ] T015 [P] Extend recursive associative-merge and wholesale list-replacement regression tests in `tests/Unit/Theme/BrandResolverTest.php`
- [ ] T016 [P] Add complete/incomplete palette and font-list, malformed-file, and missing-file fixtures under `tests/Fixtures/Theme/brand/`
- [ ] T017 Add safe-default/reporting tests for T016 fixtures without merge-by-slug behavior in `tests/Unit/Theme/BrandOverrideCompatibilityTest.php`
- [ ] T018 [P] Add alias replacement, consumer-count, one-minor-release deprecation-window, and rollback tests in `tests/Unit/Theme/TokenCompatibilityTest.php`
- [ ] T019 [P] Add shared-handle registration, CoreX-screen-only enqueue, scope-leak, and no-client-authority tests for `--corex-admin-*` in `tests/Unit/Config/AdminTokenAdapterTest.php`
- [ ] T020 [P] Add maximum-four-WOFF2, no-CDN, role/weight/subset, provenance, `font-display: swap`, fallback, and evidence-gated preload tests in `tests/Unit/Theme/FontAssetContractTest.php`
- [ ] T021 [P] Add SVG-only, provenance-manifest, optimization, variant, accessible-usage, and owner-approval gate tests in `tests/Unit/Config/LogoAssetContractTest.php`
- [ ] T022 [P] Add the light/dark semantic contrast and focus-pair fixture schema in `tests/Fixtures/Theme/contrast-focus-matrix.json`
- [ ] T023 Add 4.5:1 normal-text and 3:1 large-text/non-text/focus threshold tests for T022 in `tests/Unit/Theme/ContrastMatrixTest.php`
- [ ] T024 [P] Add Arabic-only, English-only, mixed-script, numerals, punctuation, code, badges, long-content, and nested-direction fixture definitions in `tests/Fixtures/Theme/direction-matrix.json`
- [ ] T025 Run T010–T024 focused tests, confirm intended failures precede implementation, and append exact RED commands/results to `specs/057-brand-tokens-logo-system/inventories/baseline.md`

**Checkpoint**: Inventory and failing contract tests define the implementation boundary. User-story work may begin.

---

## Phase 3: User Story 1 — One Coherent Visual Foundation (Priority: P1) 🎯 MVP

**Goal**: Establish one canonical, mode-complete token contract and align first-party front-end consumers without
redesigning components.

**Independent Test**: Token authority/consumer/mode tests pass; every first-party reference resolves to a canonical
definition or active alias; root build succeeds; component layout/behavior remains unchanged.

### Tests for User Story 1

- [ ] T026 [US1] Run `tests/Unit/Theme/TokenInventoryTest.php`, `TokenConsumerContractTest.php`, and `ModeMappingTest.php` and preserve the RED evidence from Phase 2 before editing `theme/theme.json`
- [ ] T027 [P] [US1] Add deterministic inventory regeneration/drift tests for `scripts/generate-token-inventory.mjs` in `tests/token-inventory.test.js`

### Implementation for User Story 1

- [ ] T028 [US1] Implement deterministic definition/variation/generated-name/consumer scanning in `scripts/generate-token-inventory.mjs` and regenerate `specs/057-brand-tokens-logo-system/inventories/definitions.json`, `variations.json`, `generated-properties.json`, `consumers.json`, `docs-and-brand.json`, `admin-and-aliases.json`, and `classifications.json`
- [ ] T029 [US1] Add only approved missing semantic roles and compatibility aliases while retaining stable slugs in `theme/theme.json`
- [ ] T030 [US1] Complete default/light semantic mappings, element mappings, border/focus/radius/spacing/shadow/motion/z roles, and WordPress-generated property coverage in `theme/theme.json`
- [ ] T031 [US1] Replace the partial palette/font lists with a complete dark-first mapping in `theme/styles/dark.json`
- [ ] T032 [US1] Preserve editorial compatibility and complete required replacement arrays in `theme/styles/editorial.json`
- [ ] T033 [P] [US1] Migrate undefined or semantically incorrect references in `plugins/corex-blocks/src/blocks/entity-field/style.scss`, `plugins/corex-forms/src/Block/blocks/corex-form/style.scss`, and `plugins/corex-core/assets/css/corex-runtime.css`
- [ ] T034 [P] [US1] Migrate canonical references without layout changes in `addons/corex-ui/assets/block-styles.css` and `addons/corex-ui/src/Blocks/accordion/style.scss`, `alert/style.scss`, `badge/style.scss`, `breadcrumbs/style.scss`, `copyright/style.scss`, `cta/style.scss`, `gallery/style.scss`, `hero/style.scss`, `modal/style.scss`, `posts/style.scss`, `pricing/style.scss`, `stat/style.scss`, `tabs/style.scss`, `team/style.scss`, and `testimonial/style.scss`
- [ ] T035 [P] [US1] Migrate canonical references without layout changes in `addons/corex-careers/blocks/jobs/style.scss` and `addons/corex-kit-portfolio/src/Blocks/projects/style.scss`
- [ ] T036 [US1] Regenerate `specs/057-brand-tokens-logo-system/inventories/definitions.json`, `generated-properties.json`, `consumers.json`, and `classifications.json` and prove zero undefined production references, duplicate authorities, or unknown classifications
- [ ] T037 [US1] Verify every migrated block keeps its existing `block.json`/registered conditional asset behavior and record evidence in `specs/057-brand-tokens-logo-system/inventories/consumer-migration.md`
- [ ] T038 [US1] Run focused Pest token/mode/consumer tests, `npm.cmd run test:js -- --runInBand`, `npm.cmd run build`, and `npm.cmd run lint:css`; record exact GREEN results in `specs/057-brand-tokens-logo-system/inventories/baseline.md`
- [ ] T039 [US1] Run `clean-code-guard`, `wp-guard`, and `test-guard` on the US1 diff, resolve all blocking findings, and record guard outcomes in `specs/057-brand-tokens-logo-system/inventories/baseline.md`

**Checkpoint**: The canonical token foundation is independently testable. Font/logo/admin/brand compatibility work
is not required for this MVP checkpoint.

---

## Phase 4: User Story 2 — Accessible Modes, Typography, and RTL (Priority: P1)

**Goal**: Prove complete accessible light/dark behavior, focus visibility, typography roles, font performance, and
LTR/RTL mixed-script behavior.

**Independent Test**: Contrast/focus and direction fixtures pass headlessly; font contract passes after approved
assets exist; browser/manual checks are either executed with evidence or explicitly `ENVIRONMENT-GATED`.

### Tests for User Story 2

- [ ] T040 [US2] Run `tests/Unit/Theme/ContrastMatrixTest.php` and preserve RED pair evidence before changing semantic color values
- [ ] T041 [P] [US2] Add reduced-motion, forced-colors, 200% zoom, text-resize, and focus-surface evidence requirements in `tests/Unit/Theme/VisualEvidenceContractTest.php`
- [ ] T042 [P] [US2] Add direction-matrix schema/bidi-isolation coverage tests for `tests/Fixtures/Theme/direction-matrix.json` in `tests/Unit/Theme/DirectionFixtureTest.php`
- [ ] T043 [P] [US2] Run `tests/Unit/Theme/FontAssetContractTest.php` and record RED or `BLOCKED` evidence before any font file is added

### Implementation for User Story 2

- [ ] T044 [US2] Finalize only semantic color values that make the complete contrast matrix pass in `theme/theme.json`, `theme/styles/dark.json`, and `theme/styles/editorial.json`
- [ ] T045 [US2] Align focus-ring mappings for base, raised, status, overlay, and admin contexts in `theme/theme.json` and `tests/Fixtures/Theme/contrast-focus-matrix.json`
- [ ] T046 [US2] Define system Latin body, Space Grotesk heading, JetBrains Mono technical, and IBM Plex Sans Arabic roles/fallbacks without adding asset files in `theme/theme.json`
- [ ] T047 [US2] BLOCKED until authoritative font files/provenance are approved: create checksummed upstream/license/subset records in `theme/assets/fonts/manifest.json`
- [ ] T048 [US2] BLOCKED on T047: add no more than four approved WOFF2 files under `theme/assets/fonts/` and map Space Grotesk 500–700, JetBrains Mono 400–600, and IBM Plex Sans Arabic 400/600 with `font-display: swap` in `theme/theme.json`
- [ ] T049 [US2] Prove no external font CDN, no fifth font file, readable fallbacks, and no preload without a measured evidence record in `tests/Unit/Theme/FontAssetContractTest.php`
- [ ] T050 [US2] Add or update the rendered fixture page used by browser tests for dark/light, focus, forced-colors, zoom, and LTR/RTL mixed-script coverage in `tests/e2e/fixtures/brand-foundation.html`
- [ ] T051 [US2] Add Playwright assertions for modes, focus visibility, overflow, bidi ordering, Arabic shaping hooks, reduced motion, forced colors, and 200% zoom in `tests/e2e/brand-foundation.spec.js`
- [ ] T052 [US2] Run T040–T051 headless checks and record contrast/focus/direction/font evidence in `specs/057-brand-tokens-logo-system/inventories/accessibility-evidence.md`
- [ ] T053 [US2] Run wp-env/Playwright evidence when available; otherwise record each unavailable browser/wp-env item as `ENVIRONMENT-GATED`, never PASS, in `specs/057-brand-tokens-logo-system/inventories/accessibility-evidence.md`
- [ ] T054 [US2] Inspect built/network output for font count, fallback/swap behavior, conditional assets, and unused-preload warnings; record PASS/FAIL/ENVIRONMENT-GATED evidence in `specs/057-brand-tokens-logo-system/inventories/font-evidence.md`
- [ ] T055 [US2] Run `clean-code-guard`, `wp-guard`, `test-guard`, and accessibility/RTL review on the US2 diff, resolve all non-environment blocking findings, and record results in `specs/057-brand-tokens-logo-system/inventories/accessibility-evidence.md`

**Checkpoint**: Modes, focus, typography roles, and RTL are independently evidenced. Font integration remains
honestly blocked if approved assets/provenance are unavailable.

---

## Phase 5: User Story 3 — Consistent CoreX Logo System (Priority: P2)

**Goal**: Integrate the approved Core X product mark consistently without forcing it into client-site identity.

**Independent Test**: Provenance and SVG contract tests pass; CoreX-owned admin/login uses the correct approved
variant/accessibility behavior; client overrides remain independent; rendered evidence is recorded or gated.

### Tests for User Story 3

- [ ] T056 [US3] Run `tests/Unit/Config/LogoAssetContractTest.php` and record `BLOCKED` while the owner-approved vector package/provenance is absent
- [ ] T057 [P] [US3] Add default/custom/client-separation regression cases for `BrandingService` in `tests/Unit/Config/BrandingTest.php`
- [ ] T058 [P] [US3] Add decorative, named-image, linked-brand, minimum-size, and contrast-variant fixture expectations in `tests/Fixtures/Branding/logo-usage.json`

### Implementation for User Story 3

- [ ] T059 [US3] BLOCKED until owner approval: record source, author/owner, rights, approval date, variants, viewBoxes, filenames, and accessible usage in `plugins/corex-config/assets/brand/logo-manifest.json`
- [ ] T060 [US3] BLOCKED on T059: validate/optimize only the approved symbol, wordmark, lockup, monochrome, and contrast SVGs under `plugins/corex-config/assets/brand/`
- [ ] T061 [US3] BLOCKED on T060: point the default CoreX product branding to the approved lockup while preserving custom `brand.logo_url` behavior in `plugins/corex-config/src/ConfigServiceProvider.php` and `plugins/corex-config/src/Branding/BrandingService.php`
- [ ] T062 [US3] BLOCKED on T060: apply documented decorative/named logo behavior without redesigning screens in `plugins/corex-config/src/Settings/AdminDashboard.php` and `plugins/corex-config/src/Branding/AdminBranding.php`
- [ ] T063 [US3] BLOCKED on T060: retain the old navy/cyan SVG only as documented rollback/migration evidence and verify no unapproved raster, script, external URL, or font-text dependency in `tests/Unit/Config/LogoAssetContractTest.php`
- [ ] T064 [US3] BLOCKED on T062: run focused Branding/Logo tests and browser minimum-size/contrast/accessibility checks; record PASS/FAIL/ENVIRONMENT-GATED evidence in `specs/057-brand-tokens-logo-system/inventories/logo-evidence.md`
- [ ] T065 [US3] Run `clean-code-guard`, `wp-guard`, `test-guard`, and `docs-guard` on the completed US3 diff, record results in `specs/057-brand-tokens-logo-system/inventories/logo-evidence.md`, and keep the story incomplete if T059–T064 remain blocked

**Checkpoint**: This story cannot complete until the owner-approved production vector package and provenance exist.

---

## Phase 6: User Story 4 — Compatibility, Consumer Migration, and Admin Adapter (Priority: P2)

**Goal**: Preserve existing blocks, client overrides, and admin screens while migrating legacy names and repeated
fallbacks through reversible compatibility boundaries.

**Independent Test**: Existing stable slugs and merge semantics remain passing; aliases satisfy the version window;
invalid client replacement lists report safely; the admin adapter appears only on CoreX screens.

### Tests for User Story 4

- [ ] T066 [US4] Run `TokenCompatibilityTest.php`, `BrandOverrideCompatibilityTest.php`, and `AdminTokenAdapterTest.php` and preserve RED evidence before compatibility/admin implementation
- [ ] T067 [P] [US4] Add shared admin style registration and conditional dependency assertions for `plugins/corex-core/src/Foundation/HttpServiceProvider.php` in `tests/Unit/Foundation/HttpServiceProviderTest.php`
- [ ] T068 [P] [US4] Add existing-client override fixtures and rollback snapshots under `tests/Fixtures/Theme/brand/existing/`

### Implementation for User Story 4

- [ ] T069 [US4] Implement the minimum compatibility aliases and deprecation metadata from `inventories/classifications.json` in `theme/theme.json` without removing any stable slug
- [ ] T070 [US4] Add pure required-slug validation/reporting while preserving associative merge, list replacement, and missing/malformed defaults in `plugins/corex-core/src/Theme/BrandOverrideValidator.php`, `plugins/corex-core/src/Theme/BrandResolver.php`, and `plugins/corex-core/src/Theme/ThemeServiceProvider.php`
- [ ] T071 [US4] Add complete-list/incomplete-list/rollback compatibility coverage and make T015–T018/T068 GREEN in `tests/Unit/Theme/BrandOverrideCompatibilityTest.php` and `tests/Unit/Theme/TokenCompatibilityTest.php`
- [ ] T072 [US4] Create the scoped semantic adapter in `plugins/corex-core/assets/css/corex-admin-tokens.css` and register, but never globally enqueue, handle `corex-admin-tokens` in `plugins/corex-core/src/Foundation/HttpServiceProvider.php`
- [ ] T073 [P] [US4] Add the shared adapter dependency and migrate repeated fallbacks in `plugins/corex-config/assets/control-panel.css` and `plugins/corex-config/src/Settings/AdminDashboard.php`
- [ ] T074 [P] [US4] Add the shared adapter dependency and migrate repeated fallbacks in `plugins/corex-config/assets/data.css` and `plugins/corex-config/src/Data/DataAdminScreen.php`
- [ ] T075 [P] [US4] Add the shared adapter dependency and migrate repeated fallbacks in `plugins/corex-config/assets/insights.css` and `plugins/corex-config/src/Insights/InsightsScreen.php`
- [ ] T076 [P] [US4] Add the shared adapter dependency and migrate repeated fallbacks in `addons/corex-captcha/assets/captcha-admin.css` and `addons/corex-captcha/src/CaptchaServiceProvider.php`
- [ ] T077 [US4] Prove the adapter is absent from unrelated wp-admin screens, ignores client brand overrides, and centralizes all approved fallbacks in `tests/Unit/Config/AdminTokenAdapterTest.php`
- [ ] T078 [US4] Regenerate `specs/057-brand-tokens-logo-system/inventories/consumers.json`, `admin-and-aliases.json`, and `classifications.json`; verify zero unplanned raw values/undefined references and retain aliases with documented removal eligibility
- [ ] T079 [US4] Run focused compatibility/admin tests, full CSS lint/build, `clean-code-guard`, `wp-guard`, and `test-guard`; resolve all blocking findings and record results in `specs/057-brand-tokens-logo-system/inventories/consumer-migration.md` before the US4 checkpoint

**Checkpoint**: Existing consumers and client overrides remain compatible, and admin alignment is scoped and
reversible without an admin redesign.

---

## Phase 7: Documentation, Release State, and Final Guard Gate

**Purpose**: Keep documentation honest, verify the selected implementation scope, and prepare review without
inventing release claims.

- [ ] T080 [P] Update canonical token groups, mode mappings, admin adapter, contrast/focus evidence, and RTL guidance in `docs-app/src/content/docs/design-system/foundations.md`
- [ ] T081 [P] Update complete-list `brand.json` examples, merge semantics, validation behavior, aliases, migration, rollback, and client brandability in `docs-app/src/content/docs/guides/branding.md`
- [ ] T082 [P] Document Space Grotesk, JetBrains Mono, IBM Plex Sans Arabic roles, four-file limit, fallbacks, provenance, swap, and preload policy in `docs-app/src/content/docs/design-system/foundations.md`
- [ ] T083 [P] BLOCKED until owner logo approval: document approved logo variants, clear space, minimum size, backgrounds, accessibility, provenance, and client-brand separation in `plugins/corex-config/README.md`
- [ ] T084 Document retained/added/aliased/migrated/deprecated mappings and the one-minor-release deprecation window in `specs/057-brand-tokens-logo-system/inventories/consumer-migration.md`
- [ ] T085 Update `PROGRESS.md` and `ROADMAP.md` with actual completed tasks, real checks, remaining asset blockers, and environment gates; do not update `CHANGELOG.md` or release metadata unless implementation produced a user-facing change and repository policy requires it
- [ ] T086 Run `.specify/scripts/powershell/check-prerequisites.ps1 -Json`, validate task/spec/plan traceability, and run `git diff --check`
- [ ] T087 Run focused Pest suites for Theme/Brand/Token/Contrast/Config contracts, then run full `composer test`; record exact counts/results in `PROGRESS.md`
- [ ] T088 Run `npm.cmd run lint:css`, `npm.cmd run test:js -- --runInBand`, `npm.cmd run build`, docs-app build, and `npm.cmd run verify:dependencies`; record exact results in `PROGRESS.md`
- [ ] T089 Run wp-env/Playwright/manual evidence when supported; otherwise record each unavailable browser, Docker, WordPress, forced-colors, zoom, RTL, font-network, and logo-render check as `ENVIRONMENT-GATED` or `BLOCKED`, never PASS, in `PROGRESS.md`
- [ ] T090 Run `clean-code-guard`, `wp-guard`, `test-guard`, and `docs-guard` on the complete implementation diff, record final guard results in `PROGRESS.md`, and resolve all blocking findings before requesting PR #54 review

**Checkpoint**: Spec 057 may be marked implementation-complete only when all non-gated tasks pass, environment
gates are honest, and blocked font/logo tasks are either completed with approved assets or explicitly prevent full
feature closure.

---

## Dependencies and Execution Order

### Phase dependencies

1. **Phase 1 — Inventory**: starts immediately; changes planning/inventory artifacts only.
2. **Phase 2 — Foundational tests**: depends on Phase 1 and blocks every implementation phase.
3. **Phase 3 — US1 canonical foundation**: depends on Phase 2; recommended MVP.
4. **Phase 4 — US2 accessibility/typography/RTL**: depends on US1 canonical mode vocabulary. Font tasks T047–T049
   additionally depend on approved font files/provenance.
5. **Phase 5 — US3 logo**: depends on Phase 2 and owner-approved logo package/provenance; otherwise remains blocked.
6. **Phase 6 — US4 compatibility/admin**: depends on US1 canonical names/aliases; may proceed in parallel with US2
   after US1.
7. **Phase 7 — final documentation/gates**: follows the selected completed stories; blocked documentation remains
   blocked when its asset story is blocked.

### User story dependencies

- **US1 (P1)**: independent after foundational tests; establishes the vocabulary consumed by US2 and US4.
- **US2 (P1)**: depends on US1 mode/token contract; browser evidence may be environment-gated; font integration has
  its own provenance gate.
- **US3 (P2)**: independent of token implementation after foundation, but entirely gated by owner logo approval.
- **US4 (P2)**: depends on US1 canonical/alias classifications; admin and brand work is independent of US2/US3.

### TDD order within each story

1. Run/create the named test and capture RED or an honest asset/environment gate.
2. Implement the smallest owned-file batch.
3. Run focused GREEN tests and inventory drift checks.
4. Run package build/lint and applicable guards.
5. Update evidence before moving to the next batch.

## Parallel Opportunities

- T003–T008 inventory separate artifacts and can run concurrently after T002.
- T010–T024 tests mostly own separate files; tasks sharing a test file remain sequential.
- T033–T035 migrate different owning packages in parallel after canonical definitions exist.
- T041–T043 prepare independent evidence/font contracts in parallel.
- US3 may begin only after its owner asset gate opens; it does not block US4.
- T073–T076 migrate four independently enqueued admin styles in parallel after T072 registers the shared handle.
- T080–T083 update different documentation surfaces in parallel, subject to the logo gate.

## Parallel Example: User Story 1

```text
Task T033: migrate core blocks/forms/runtime consumers.
Task T034: migrate corex-ui consumers.
Task T035: migrate careers/portfolio consumers.
```

## Parallel Example: User Story 4

```text
After T072 registers corex-admin-tokens:
Task T073: settings/control-panel screen.
Task T074: data screen.
Task T075: insights screen.
Task T076: captcha settings control.
```

## Implementation Strategy

### MVP first

1. Complete inventory and foundational RED contracts (T001–T025).
2. Complete US1 only (T026–T039).
3. Stop and verify the canonical token vocabulary, full mode mappings, consumer resolution, build, and guards.
4. Do not pull fonts, logos, admin redesign, or later milestones into the MVP.

### Incremental delivery

1. US1: canonical token/mode foundation.
2. US4: compatibility aliases, client override validation, and admin adapter may proceed once US1 is stable.
3. US2: accessibility/RTL evidence proceeds; font integration waits for provenance-approved files.
4. US3: logo integration begins only after owner approval.
5. Final documentation and guard gate reports actual completed/gated state.

## Notes

- `[P]` means different owned files and no unmet dependency; do not parallelize overlapping `theme.json` tasks.
- `BLOCKED` and `ENVIRONMENT-GATED` are valid evidence states, never synonyms for PASS.
- No task authorizes Specs 058/059, M3/M4, full admin/forms/docs/marketing redesign, Pro UI, or front-office editor work.
- Do not modify `CHANGELOG.md` or version metadata during task generation. During implementation, update them only
  when a real user-facing change and repository release policy require it.
- Commit after each reviewed logical batch; preserve rollback aliases and complete client replacement arrays.
