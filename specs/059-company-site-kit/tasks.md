---
description: "Task list for Spec 059 — Company Site Kit v1"
---

# Tasks: Company Site Kit v1 — Structure and Page Coverage

**Input**: design docs in `specs/059-company-site-kit/`. **Tests**: REQUIRED (Corex Definition of Done).

**Foundation reused (not rebuilt):** `corex-core` `Corex\Provisioning\*` (preview/apply, `PageDisposition`) and the
`corex-kit-company` addon (`CompanyBlueprint`, `BlueprintKitProvisioner`).

## Format: `[ID] [P?] [Story] Description`

## Phase 1: Setup

- [ ] T001 Confirm `corex-ui` registered patterns used by M4 exist (hero/features/cta/testimonial/contact/faq/news/
  stats/content-split/section-header); record the M5 block gaps (services/team/logo-cloud/case-study/locations) in
  research.md (done) — no new blocks built here.

## Phase 2: Foundational

- [ ] T002 [US1] Extend the `Blueprint` base with default-safe optional hooks: `demoLevels()` (default
  `['minimal','standard','full']`) and per-page `seo` support, without breaking existing kits (Portfolio/Woo).
- [ ] T003 [US1] Pest `CompanyBlueprintTest` (RED first): asserts the full v1 page set, one `front`, unique slugs,
  registered-pattern-only references, and no raw hex/px/font in page markup.

## Phase 3: US1 — core pages, applied safely (P1)

- [ ] T004 [US1] Extend `CompanyBlueprint::pages()` core set: Home (front), About, Services, Contact + the legal
  pages (Privacy, Terms, Cookie), composing confirmed patterns + `section-header` + core blocks; token-only, i18n.
- [ ] T005 [US1] Verify the universal templates already cover system surfaces (404/search/single/archive/index);
  add none as pages. Confirm via the blueprint `templates()` list + a test assertion.
- [ ] T006 [US1] Make T003 GREEN for the core set; reuse existing provisioning tests for preview/apply/conflict.
- [ ] T007 [P] [US1] Playwright `company-kit-core` (ENV-gated): landmarks/heading order, RTL, 320px/200% — record
  ENVIRONMENT-GATED if no browser.

## Phase 4: US2 — full v1 page coverage (P2)

- [ ] T008 [US2] Add the remaining v1 pages: Single Service, Case Studies/Work, Single Case Study, Industries, FAQ,
  Blog/News, Team, Testimonials, Locations/Branches, Maintenance — compose patterns + core blocks; record M5 gaps in
  page comments where a dedicated block is missing.
- [ ] T009 [US2] Extend `CompanyBlueprintTest` to assert full coverage + token-only + registered patterns for all
  pages; GREEN.

## Phase 5: US3 — demo levels, brand setup, SEO (P3)

- [ ] T010 [US3] Implement `pages($level)` so `minimal`/`standard`/`full` produce identical structure with differing
  content depth; `standard` default. Pest: structure parity across levels.
- [ ] T011 [US3] Add per-page SEO starter fields (title/description/OG) as editable, plugin-compatible defaults; wire
  through the provisioner where needed. Pest: SEO fields well-formed; no plugin dependency.
- [ ] T012 [US3] Confirm brand-aware setup maps to M2 tokens/`brand.json` via the existing setup wizard; do not
  hardcode a client brand. Pest/assertion on the mapping path.

## Phase 6: Polish & Final Gate

- [ ] T013 [P] Docs: docs-app company-kit page (page set, demo levels, apply/conflict, SEO, a11y/RTL) + guide link;
  docs-guard.
- [ ] T014 Full gate: `composer test`, `npm run test:js`, `npm run build`, docs-app build, `lint:css`; ENV-gate
  wp-env/Playwright honestly.
- [ ] T015 Whole-diff Guard Gate (wp/clean-code/test/docs) clean.
- [ ] T016 Update PROGRESS/ROADMAP/CHANGELOG; DECISIONS entry (reuse-provisioning + demo-level + SEO decisions).
- [ ] T017 Re-verify `wp corex make:site` with the richer kit; update `make-site-verification.md` (note the
  visual-foundation inheritance gap resolution path).

## Dependencies

- Setup (T001) → Foundational (T002-T003) → US1 (T004-T007) → US2 (T008-T009) → US3 (T010-T012) → Polish
  (T013-T017). Same-file: `CompanyBlueprint.php` edits (T004→T008→T010→T011) sequential.

## Notes

- RED→GREEN per test. Reuse provisioning; do not rebuild it. Record M5 gaps; do not build new section blocks here.
- ENVIRONMENT-GATED steps recorded honestly. No builder/Portfolio/Woo/Pro scope.
