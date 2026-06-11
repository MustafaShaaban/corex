# Tasks: Block library expansion (027)

**Forward spec** — TDD-ordered (Corex DoD). Four new server-rendered `corex/*` blocks drop into `corex-ui`
(no engine change). Renderers are pure (attributes → escaped HTML), unit-tested headlessly. FR→block map is in
`plan.md`; the contract is in `contracts/blocks-contract.md`.

## Phase 1: Setup

- [x] T001 Confirm the corex-ui dynamic-block contract (block.json → container-resolved `BlockRenderer`, auto-discovered by `BlockMap`; `UiBlocksTest` stubs WP escaping via Brain Monkey) is the model.

## Phase 2: Foundational (the test scaffold — shared by all blocks)

- [x] T002 Create `tests/Unit/Ui/ComponentBlocksTest.php` with the WP-escaping stubs (`esc_html`/`esc_html__`/`esc_attr`/`esc_url` → returnArg) — the headless harness every renderer assertion uses.

## Phase 3: User Story 1 + 2 — the four component blocks (P1) 🎯 MVP

Each block is one increment: a failing renderer test, then the renderer, then the block assets (block.json +
index.js + style.scss). Built server-rendered + accessible + token-only per `data-model.md`.

### corex/stat
- [x] T003 [US2] Add a `ComponentBlocksTest` case (RED) for `StatRenderer`: value + label render; empty value+label → empty; output escaped.
- [x] T004 [US1] Implement `addons/corex-ui/src/Blocks/StatRenderer.php` + `Blocks/stat/{block.json,index.js,style.scss}` (token-only, RTL).

### corex/testimonial
- [x] T005 [US2] Add a test case (RED) for `TestimonialRenderer`: `<figure>/<blockquote>/<figcaption>`; empty quote → empty.
- [x] T006 [US1] Implement `TestimonialRenderer.php` + `Blocks/testimonial/{block.json,index.js,style.scss}`.

### corex/pricing
- [x] T007 [US2] Add a test case (RED) for `PricingRenderer`: plan/price/period, newline `features` → `<li>`s, CTA only when text+url set.
- [x] T008 [US1] Implement `PricingRenderer.php` + `Blocks/pricing/{block.json,index.js,style.scss}`.

### corex/accordion
- [x] T009 [US2] Add a test case (RED) for `AccordionRenderer`: `Title | Content` lines → native `<details><summary>`; empty items → empty.
- [x] T010 [US1] Implement `AccordionRenderer.php` + `Blocks/accordion/{block.json,index.js,style.scss}` (accessible disclosure, no JS).

### shared
- [x] T011 [US1] Add a token-only scan case asserting no new block's rendered markup contains a hardcoded hex color or px size (per the existing pattern scans).

**Checkpoint**: four new blocks render accessible, escaped, token-only markup, all unit-tested headlessly.

## Phase 4: Polish & cross-cutting

- [x] T012 Run the Guard Gate: `clean-code-guard` (the four renderers) + `wp-guard` (escaping/`esc_url`) + `test-guard` (the renderer tests); fix any violation.
- [x] T013 Verify the `UiManifest` enumerates the four new blocks (extend `UiManifestTest`); confirm `BlockMap` discovers them with no engine change.
- [x] T014 [P] Build: `npm run build` compiles each new block's style (+ RTL) + index.js; record. Register `addons/corex-ui` as it already is an npm workspace.
- [x] T015 [P] Verify live on `./wp`: each `corex/<slug>` registers (quickstart §4); record. (Editor visual is the Apache-gated smoke.)
- [x] T016 Document the new blocks in `addons/corex-ui/README.md`; update `PROGRESS.md` + add a `DECISIONS.md` entry; end with NEXT STEP.

## Dependencies

- Phase 2 (the test harness) precedes the blocks. Each block (T003–T010) is an independent increment — they
  touch separate files and can be built in any order / parallel. T011/T013 depend on all four existing.

## Implementation strategy

MVP = the four blocks, each delivered test-first (renderer test → renderer → assets). They reuse the existing
auto-discovery + `ServerSideRender` contract, so no registration engine change is needed. JS tabs + a
media-repeater gallery are an explicit later increment (Interactivity API), out of scope here.

## Parallel opportunities

- T003–T010 (the four blocks) are independent (separate dirs + renderer classes).
- T014 (build) and T015 (live verify) are [P] in polish.
