# Tasks: Corex Full DLS (054)

**Feature**: `specs/054-corex-full-dls` · **Branch**: `feature/054-corex-full-dls`
**Inputs**: plan.md · spec.md (US1–US4) · research.md (the gap analysis, D0–D8) · data-model.md · contracts/
{catalog, foundations-tokens, modal-block, block-styles}.md · quickstart.md

**Story legend**: US1 = inventory + gap analysis + expanded drift-checked catalog (P1, MVP) · US2 = foundations
completion (motion/focus/z tokens + guides) (P1) · US3 = the justified components (corex/modal + block styles +
skeleton), native-first (P2) · US4 = patterns + templates + the docs-app design-system section (P2).

**Conventions**: `[P]` = parallelizable. Tests REQUIRED (constitution DoD): Pest (catalog/drift, renderer,
block-styles, pattern-accuracy), Jest (modal editor reg), Playwright-052 (env-gated). Each story ends with a
Guard Gate. Stories are independent; recommended order = priority. **Build list is the gap analysis (research
D2) — no element is built that core already covers.**

---

## Phase 1: Setup

- [ ] T001 Confirm baseline green: `vendor/bin/pest`, `npm run test:js`, `cd docs-app && npm run build`. Record
  counts in the PR. No code change.
- [ ] T002 [P] Re-read the gap analysis (research.md D2) + the existing `DesignSystemCatalog`, `PatternLibrary`,
  and `theme/theme.json` `settings.custom` so every task references real names. No code change.

## Phase 2: Foundational (blocking prerequisites)

- [ ] T003 Confirm no new infrastructure is needed (no new plugin/route/schema). The corex-ui block-style
  registrar (`BlockStyles`) is introduced in US3; the catalog already exists. Minimal.

---

## Phase 3: User Story 1 — Inventory, gap analysis, expanded catalog (P1) 🎯 MVP

**Goal**: the catalog enumerates the full taxonomy (drift-checked, with a `mechanism` field) and the gap
analysis is published.
**Independent test**: `DesignSystemCatalogTest` green (six categories; `blockNames()` ⊆ registered `corex/*`;
block-style/core/deferred entries `block:null`); a gap-analysis doc classifies every candidate.

### Tests first (TDD)

- [X] T004 [P] [US1] Extend `tests/Unit/Ui/DesignSystemCatalogTest.php`: assert all six categories non-empty;
  `blockNames()` ⊆ the registered `corex/*` UI blocks **and** every registered corex-ui UI block is catalogued;
  `mechanism` present; block-style/core/runtime/deferred entries carry `block:null`. (contracts/catalog.md)

### Implementation

- [X] T005 [US1] Expand `addons/corex-ui/src/DesignSystemCatalog.php`: add the `mechanism` field; enumerate
  Foundations (color/type/spacing/shadow/radius/layout/motion/focus/z), Components (atoms incl. the modal +
  block-style entries tagged by mechanism), Blocks (the section blocks), Patterns, Templates, Guidelines; keep
  drift-safe (corex-block entries only for registered blocks). (FR-001, FR-003)
- [X] T006 [P] [US1] Publish the gap analysis as `docs-app/src/content/docs/design-system/gap-analysis.md`
  (the research D2 table: candidate → decision → location → rationale; every candidate classified). (FR-002)
- [X] T007 [US1] **Guard Gate (US1)**: `clean-code-guard` + `test-guard` (the catalog test) + `docs-guard` (the
  gap-analysis page). Fix findings. (FR-017)

**Checkpoint**: the catalog is the full, drift-checked spine; the build list is evidence-published.

---

## Phase 4: User Story 2 — Foundations completion (P1)

**Goal**: add the genuinely-missing token groups (motion/focus/z) and document **all** foundations.
**Independent test**: theme.json valid + exposes `custom.motion/focus/z`; a Foundations doc documents every group;
(env-gated) a brand.json override of a new token flows.

### Implementation

- [X] T008 [US2] Add `settings.custom.motion` (duration fast/base/slow + easing standard/emphasized),
  `settings.custom.focus` (width/color→accent/offset), and `settings.custom.z` (base/dropdown/sticky/overlay/
  modal/toast) to `theme/theme.json` — runtime CSS custom properties, no build-time tokens. (FR-005,
  contracts/foundations-tokens.md)
- [X] T009 [P] [US2] Author `docs-app/src/content/docs/design-system/foundations.md`: every token group (existing
  color/type/spacing/shadow/radius/layout + new motion/focus/z) with its CSS variable, allowed values, usage
  rule; plus grid/layout, icon guidance, motion guidance, focus states, RTL, accessibility guideline pages
  (FR-006, FR-007).
- [X] T010 [US2] Apply a focus/motion token in one existing block's SCSS as proof of consumption. **DEFERRED to
  US3: `corex/modal` consumes the focus + z-index tokens (its natural proof) — avoids rebuilding an existing
  block just for a token swap.** (FR-011)
- [X] T011 [US2] **Guard Gate (US2)**: `wp-guard` (token-only, no hardcoded values) + `docs-guard` (foundations
  pages) + theme.json validity. Fix findings. (FR-017)

**Checkpoint**: foundations whole + documented; components can now consume motion/focus/z.

---

## Phase 5: User Story 3 — The justified components, native-first (P2)

**Goal**: build only what the gap analysis justified — `corex/modal` (1 block) + block styles + a skeleton
utility; document the core-backed ones.
**Independent test**: `ModalRendererTest` + `BlockStylesTest` + modal Jest green; the block styles register on
their blocks; documented-core components have **no** new block.

### Tests first (TDD)

- [X] T012 [P] [US3] Pest `tests/Unit/Ui/ModalRendererTest.php`: renders trigger + `<dialog aria-labelledby>` +
  close; escapes `title`/`triggerLabel`; token-only (no hardcoded color/size). (contracts/modal-block.md)
- [X] T013 [P] [US3] Pest `tests/Unit/Ui/BlockStylesTest.php`: each of card/section/striped-table/button-
  secondary/button-ghost/empty-state is registered on its core/corex block with the `corex-…` name.
  (contracts/block-styles.md)
- [X] T014 [P] [US3] Jest `addons/corex-ui/src/Blocks/modal/index.test.js`: `registerBlockType(metadata.name)`,
  `save()===null`, `edit()` previews via `<ServerSideRender>`.

### Implementation

- [X] T015 [US3] Build `corex/modal` in `addons/corex-ui/src/Blocks/modal/` (block.json apiVersion 3, category
  corex, editorScript + viewScript + style, `corex.renderer`) + `ModalRenderer.php` (native `<dialog>`,
  `aria-labelledby`, trigger + close, escaped, token-only incl. `--wp--custom--z--modal`) + `view.js`
  (showModal/close, ESC/backdrop, focus return; degrades without JS). (FR-008, FR-010, D3)
- [X] T016 [US3] Add a `BlockStyles` registrar in `addons/corex-ui` (`register_block_style` for card/section/
  striped-table/button-secondary/button-ghost/empty-state) + token-only SCSS, conditionally enqueued. (FR-009,
  D4)
- [X] T017 [P] [US3] Add the token-only `.corex-skeleton` loading utility (motion + surface-alt tokens), RTL,
  documented. (FR-009)
- [X] T018 [US3] Register `corex/modal` in the corex-ui provider/discovery; add it to the catalog (mechanism
  `corex-block`) — drift test (T004) stays green; `npm run build`. (FR-003, FR-008)
- [X] T019 [US3] **Guard Gate (US3)**: `wp-guard` (escaped, conditional assets, ARIA, no-secret) + `clean-code` +
  `test-guard`. Fix findings. (FR-017)

**Checkpoint**: the component layer is complete to the justified set; core-backed atoms documented, not rebuilt.

---

## Phase 6: User Story 4 — Patterns, templates, docs (P2)

**Goal**: add the justified patterns + page templates and the docs-app design-system section.
**Independent test**: `PatternLibraryTest` (real-blocks-only) green; the new templates are valid FSE; the
docs-app design-system section builds with no broken links.

### Tests first (TDD)

- [ ] T020 [P] [US4] Extend `tests/Unit/Ui/PatternLibraryTest.php`: the new patterns (section-header, content-
  split, stats, FAQ, posts-news) compose only registered blocks/parts (pattern-accuracy). (FR-012)

### Implementation

- [ ] T021 [US4] Add the patterns to `addons/corex-ui/src/Patterns/PatternLibrary.php` (section-header, content-
  split on core/media-text, stats on corex/stat, FAQ on corex/accordion, posts-news on corex/posts) — token-only,
  RTL. (FR-012, D6)
- [ ] T022 [P] [US4] Add the page-type templates to `theme/templates/` (`page-landing.html`, `page-contact.html`,
  `page-form.html`) — valid FSE, parts/patterns only, no logic. (FR-013)
- [ ] T023 [US4] Author the docs-app **design-system section** (`docs-app/src/content/docs/design-system/`):
  index, components/* (a page per component with attributes + when-to-use / when-not-to-use), patterns,
  templates, guidelines; link to the catalog entries; sidebar wiring. (FR-014)
- [ ] T024 [US4] **Guard Gate (US4)**: `docs-guard` (the section) + `test-guard` (pattern-accuracy) + `wp-guard`
  (patterns/templates token-only/RTL). `cd docs-app && npm run build` green, no broken links. (FR-017)

**Checkpoint**: composition + documentation complete; the DLS is navigable end to end.

---

## Phase 7: Polish & cross-cutting

- [ ] T025 [P] Update `addons/corex-ui/README.md` (the full DLS overview + the catalog) and the existing
  `docs-app/.../guides/design-system.md` (link into the new section), per §D.5. `docs-guard` clean.
- [ ] T026 Reuse the spec-052 Playwright + console sweep for the modal (open/ESC/backdrop/focus-return, RTL,
  console-clean) and the new patterns/templates. Execute under wp-env if available; else record env-gated.
- [ ] T027 Update `PROGRESS.md` (054 entry) + `DECISIONS.md` #88 (full DLS — native-first, the modal-only new
  block, the token gaps). NEXT STEP.
- [ ] T028 Full-suite verification: `vendor/bin/pest` + `npm run test:js` + docs build green; record counts.
  Commit per story → push → PR into `develop` → CI green.

---

## Dependencies & ordering

- Setup (T001–T002) → Foundational (T003) precede the stories.
- **US1 → US2 → US3 → US4** recommended; US2's tokens are a prerequisite for US3's modal/skeleton (motion/z/focus).
  US1 and the docs are otherwise independent.
- Within a story: tests before implementation (T004→T005; T012–T014→T015–T018; T020→T021).
- Polish (T025–T028) last.

## Parallel opportunities

- US1: T006 (gap doc) ∥ T004 (test).
- US3: T012, T013, T014 (tests) in parallel; T017 (skeleton) ∥ T015 (modal).
- US4: T020 (test) ∥ T022 (templates); T023 (docs) ∥ the block work.

## MVP scope

**US1 (catalog + gap analysis)** alone makes the system navigable + evidence-based — shippable independently.
**US2 (foundations)** is the next-cheapest, highest-leverage increment (tokens + docs, headless). US3 (the modal +
styles) and US4 (patterns/templates/docs) complete the system; the modal's visual a11y is the env-gated tail.

## Format validation

All tasks use `- [ ] [TaskID] [P?] [Story?] description + file path`; Setup/Foundational/Polish carry no story
label; US1–US4 carry theirs; tests precede implementation per story.
