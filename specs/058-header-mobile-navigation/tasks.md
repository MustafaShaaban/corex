---
description: "Task list for Spec 058 — Header, Mobile Navigation, Mega Menu, and Footer System"
---

# Tasks: Header, Mobile Navigation, Mega Menu, and Footer System

**Input**: Design documents from `specs/058-header-mobile-navigation/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/

**Tests**: REQUIRED (Corex Definition of Done — Pest/Jest/Playwright, Guard Gate, i18n, RTL, WCAG 2.2 AA).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: can run in parallel (different files, no dependency).
- **[Story]**: US1 (P1), US2 (P2 mega), US3 (P2 footer), US4 (P3 variants/behavior/slots).

---

## Phase 1: Setup (Shared Infrastructure)

- [ ] T001 Create `theme/patterns/` and `theme/assets/{css,js}/` directories with `.gitkeep` placeholders where empty.
- [ ] T002 Add the three layout-only custom tokens (`custom.header.height`, `custom.header.heightCompact`,
  `custom.nav.breakpoint`) to `theme/theme.json` per contracts/token-consumption.md (no other token changes).
- [ ] T003 [P] Add a raw-literal scan (no hex/`rgb(`/`hsl(`/hard-coded font) for `theme/assets/css/corex-navigation.css`
  and `theme/patterns/*.php` to the existing CSS-lint/scan tooling (extend `scripts/` or stylelint config).

---

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ Must complete before user-story work.**

- [ ] T004 [US1] Create `Corex\Theme\NavigationServiceProvider` in `plugins/corex-core/src/Theme/` skeleton:
  register the `corex` block-pattern category on `init`; register (not enqueue) the `corex-navigation` style + script
  handles. Wire it into the corex-core provider list. (contracts/pattern-registration.md C1, C3)
- [ ] T005 [US1] Pest: `NavigationServiceProviderTest` — RED first — asserts the `corex` category is registered and
  the `corex-navigation` style/script handles are registered but NOT globally enqueued.
- [ ] T006 [P] Create empty token/markup contract test scaffolding: `tests/.../Navigation/` Pest dir + a Jest
  `corex-navigation` spec file that imports the (not-yet-written) behavior module (RED).

**Checkpoint**: provider + token base ready; user stories can begin.

---

## Phase 3: User Story 1 — Accessible header + mobile menu (Priority: P1) 🎯 MVP

**Goal**: a branded, accessible header (brand + primary nav + CTA) that collapses to an accessible mobile menu.

**Independent Test**: activate the header part; verify brand/nav/CTA render, mobile menu opens/traps focus/closes on
Escape+outside-click+control and restores focus, visible focus, RTL mirrors, no horizontal scroll.

### Tests for US1 (write first, ensure FAIL)

- [ ] T007 [P] [US1] Pest: `HeaderSimplePatternTest` — `corex/header-simple` registered, parses as valid blocks,
  contains site-logo/title + `core/navigation` (overlayMenu) + a CTA, no raw hex/font literals.
- [ ] T008 [P] [US1] Pest: `ThemeTokensTest` — `theme.json` has the three new custom tokens with documented defaults.
- [ ] T009 [P] [US1] Pest: `NavigationAssetEnqueueTest` — nav CSS attaches to `core/navigation` rendering, absent
  when no navigation renders (Principle VI).

### Implementation for US1

- [ ] T010 [US1] Add `theme/patterns/header-simple.php` (brand slot via M2 logo + `core/navigation` `overlayMenu` +
  primary CTA button), CoreX+core `header` categories, i18n strings. (data-model: Header part; contracts: C2/C4)
- [ ] T011 [US1] Update `theme/parts/header.html` to render the simple-company composition consistently with the
  pattern (brand + nav + CTA).
- [ ] T012 [US1] Author `theme/assets/css/corex-navigation.css` header section: token-driven header layout (height,
  spacing, logical properties), visible focus ring from `--wp--custom--focus--*`, no raw literals.
- [ ] T013 [US1] Implement conditional enqueue in `NavigationServiceProvider` via `wp_enqueue_block_style('core/navigation', …)`.
- [ ] T014 [US1] Make T005/T007/T008/T009 GREEN; `wp-guard` (PHP/theme), `clean-code-guard`, `test-guard` clean.
- [ ] T015 [P] [US1] Playwright `navigation-header.spec` (ENV-gated): keyboard reach, focus visible, Escape/outside-
  click close + focus return, RTL mirror, 320px/200% no horizontal scroll. Record ENVIRONMENT-GATED if no browser.

**Checkpoint**: P1 MVP — accessible header shippable on its own.

---

## Phase 4: User Story 2 — Mega menu (Priority: P2)

**Goal**: rich, accessible mega-menu layouts that degrade to link lists / mobile accordion.

### Tests for US2 (write first, ensure FAIL)

- [ ] T016 [P] [US2] Pest: `MegaMenuPatternsTest` — the four `corex/megamenu-*` patterns registered, valid markup,
  disclosure button with `aria-expanded`/`aria-controls`, item anatomy present, no raw literals.
- [ ] T017 [P] [US2] Jest: `corex-navigation` mega/accordion — toggles `aria-expanded`, Escape closes + restores
  focus, no animation under mocked `prefers-reduced-motion: reduce`, listeners removed on teardown.

### Implementation for US2

- [ ] T018 [P] [US2] `theme/patterns/megamenu-simple.php`, `megamenu-services.php`, `megamenu-product.php`,
  `megamenu-docs.php` (disclosure button + multi-column panel + item anatomy: icon/title/description/badge/link/
  featured card/CTA), i18n.
- [ ] T019 [US2] Author `theme/assets/js/corex-navigation.js` mega-menu/accordion module (disclosure toggle, Escape,
  outside-click, focus return; mobile accordion below breakpoint; reduced-motion guard; teardown).
- [ ] T020 [US2] Extend `corex-navigation.css` mega-menu/accordion styling (panel at `--wp--custom--z--dropdown`,
  columns, logical props, reduced-motion-gated transitions).
- [ ] T021 [US2] Render-scoped enqueue of the behavior JS where a CoreX mega-menu header renders (Principle VI).
- [ ] T022 [US2] Make T016/T017 GREEN; guards clean.

**Checkpoint**: US1 + US2 work independently.

---

## Phase 5: User Story 3 — Footer variants (Priority: P2)

**Goal**: composable footer variants in a contentinfo landmark with accessible reflow + RTL.

### Tests for US3 (write first, ensure FAIL)

- [ ] T023 [P] [US3] Pest: `FooterPatternsTest` — the six `corex/footer-*` patterns registered, valid markup,
  contentinfo landmark + heading semantics + legal row, no raw literals.

### Implementation for US3

- [ ] T024 [P] [US3] `theme/patterns/footer-simple.php`, `footer-corporate.php`, `footer-saas.php`,
  `footer-newsletter.php`, `footer-locations.php`, `footer-legal.php` (column/region groups + legal row), i18n.
- [ ] T025 [US3] Update `theme/parts/footer.html` to the simple footer composition (regions + legal row, reusing
  `corex/copyright`).
- [ ] T026 [US3] Extend `corex-navigation.css` footer section (multi-column→stacked reflow via logical props/grid).
- [ ] T027 [US3] Make T023 GREEN; guards clean.

**Checkpoint**: US1+US2+US3 independent.

---

## Phase 6: User Story 4 — Header variants, sticky/transparent, slots (Priority: P3)

**Goal**: remaining header variants, sticky/transparent behavior, action-slot placeholders.

### Tests for US4 (write first, ensure FAIL)

- [ ] T028 [P] [US4] Pest: `HeaderVariantsPatternsTest` — `corex/header-{corporate,saas,docs,transparent,minimal}`
  registered, valid markup, action slots accessible/labelled, no raw literals.
- [ ] T029 [P] [US4] Jest: `corex-navigation` header-state — toggles `data-corex-header-state` at scroll threshold
  (rAF/IntersectionObserver mock), reduced-motion guard, teardown removes listeners.

### Implementation for US4

- [ ] T030 [P] [US4] `theme/patterns/header-corporate.php` (top utility bar), `header-saas.php` (mega menu +
  secondary CTA), `header-docs.php` (search slot), `header-transparent.php`, `header-minimal.php`, with optional
  action slots (search/language/cta/account/cart) as labelled placeholders, i18n.
- [ ] T031 [US4] Add header scroll-state module to `corex-navigation.js` (passive rAF/IO; transparent→solid flip on
  scroll/menu-open; reduced-motion-gated).
- [ ] T032 [US4] Extend `corex-navigation.css`: sticky (`position: sticky` at `--wp--custom--z--sticky`),
  transparent→solid token-driven state, action-slot styling, compact height on scroll.
- [ ] T033 [US4] Make T028/T029 GREEN; guards clean.
- [ ] T034 [P] [US4] Playwright `navigation-variants.spec` (ENV-gated): sticky/transparent contrast, slots, RTL.

**Checkpoint**: all user stories independently functional.

---

## Phase 7: Polish, Docs & Final Gate

- [ ] T035 [P] Docs: add docs-app navigation/footer foundations + patterns pages (variants, behavior, a11y, RTL,
  token usage, slot guidance); run `docs-guard`.
- [ ] T036 [P] RTL example coverage: ensure each header/footer variant has a documented RTL example/screenshot hook.
- [ ] T037 Run full gate: `composer test`, `npm run test:js`, `npm run lint:css`, `npm run build`, docs-app build,
  `verify:dependencies`; record results; ENVIRONMENT-GATE wp-env/Playwright honestly.
- [ ] T038 Whole-diff Guard Gate: `wp-guard` + `clean-code-guard` + `test-guard` + `docs-guard` clean on the full diff.
- [ ] T039 Update `PROGRESS.md` (resume entry), `ROADMAP.md` (M3 status), `CHANGELOG.md` `[Unreleased]`; log
  non-trivial decisions in `DECISIONS.md` (e.g. nav asset owner, disclosure-vs-menubar, breakpoint token).
- [ ] T040 Run `quickstart.md` validation end-to-end; finalize the PR for review.

---

## Dependencies & Execution Order

- **Setup (P1: T001-T003)** → **Foundational (P2: T004-T006)** blocks all stories.
- **US1 (T007-T015)** is the MVP and should land first. **US2 (T016-T022)**, **US3 (T023-T027)**, **US4
  (T028-T034)** each depend only on Foundational and may proceed after US1; US2/US4 share `corex-navigation.js`
  (sequence those JS tasks: T019 before T031) and `corex-navigation.css` (T012→T020→T026→T032 are same-file, run
  sequentially, not [P]).
- **Polish (T035-T040)** after the targeted stories are complete.

### Same-file sequencing (not parallel)

- `corex-navigation.css`: T012 → T020 → T026 → T032.
- `corex-navigation.js`: T019 → T021 → T031.
- `NavigationServiceProvider`: T004 → T013 → T021.
- `theme.json`: T002 only.

### Parallel opportunities

- Pattern files in different `theme/patterns/*.php` are [P] within a story (T018, T024, T030 groups).
- Test-authoring tasks marked [P] across stories can be written in parallel before their implementations.

---

## Implementation Strategy

1. Setup + Foundational.
2. **US1 MVP** → validate independently → checkpoint/PR.
3. US2 → US3 → US4 incrementally, each independently testable.
4. Polish + final gate + docs + PROGRESS/ROADMAP/CHANGELOG, then PR review.

## Notes

- Verify each test FAILS before implementing (RED → GREEN).
- Commit after each task or logical group; keep PR #-scoped to Spec 058.
- ENVIRONMENT-GATED steps (wp-env, Playwright) recorded honestly, never PASS.
- No builders, commerce business logic, M4/M5/M9, or Pro scope (FR-021).
