# Tasks: Interactive, inline-editable blocks (029)

**Forward spec, TDD-ordered.** Blocks stay dynamic (`save:()=>null`); inline editing writes attributes the
renderer reads. Rich → `wp_kses_post`, plain → `esc_*`. Browser-visual is env-gated; registration/render/REST/
Jest are headless. FR→component map in `plan.md`.

## Phase 1: Setup
- [x] T001 Confirm the spec-018 build + the existing block tests (`ComponentBlocksTest`, `UiBlocksTest`, the block `index.test.js` pattern) are the model.

## Phase 2: Foundational — the form-list data source (US2 base)
- [x] T002 Write `tests/Unit/Forms/FormsListControllerTest.php` (RED): returns `[{slug,label}]` from a stub `FormRegistry`; refuses without `edit_posts`.
- [x] T003 Implement `plugins/corex-forms/src/Submission/FormsListController.php` (GET `corex/v1/forms`, `edit_posts`, slug+label only) + register it in `FormsServiceProvider`.

## Phase 3: US1 — inline-editable component blocks (P1) 🎯 MVP
- [x] T004 [US1] Update renderers (RED first via `ComponentBlocksTest`): `StatRenderer`/`TestimonialRenderer`/`PricingRenderer`/`AccordionRenderer` output rich fields with `wp_kses_post`, plain with `esc_*`; accordion iterates an `items` array with the legacy-string fallback.
- [x] T005 [US1] Rewrite `stat/index.js` to edit `value`/`label`/`description` inline via `RichText`; `save:()=>null`. Add `stat/index.test.js` (Jest).
- [x] T006 [US1] Rewrite `testimonial/index.js` (quote/author/role RichText) + `testimonial/index.test.js`.
- [x] T007 [US1] Rewrite `pricing/index.js` (plan/price/period/ctaText RichText; `features` repeatable RichText rows; `ctaUrl` in InspectorControls) + `pricing/index.test.js`.
- [x] T008 [US1] Rewrite `accordion/index.js` (repeatable `{title,content}` RichText rows) + `accordion/index.test.js`.

## Phase 4: US2 — form selector (P1)
- [x] T009 [US2] Rewrite `corex-form/index.js`: replace the free-text `formSlug` with a `SelectControl` populated from `corex/v1/forms` (apiFetch), empty-state when none; keep `ServerSideRender` preview. Add/extend its Jest test.

## Phase 5: Polish & cross-cutting
- [x] T010 Run the Guard Gate: `wp-guard` (kses/escaping + REST cap), `clean-code-guard`, `test-guard`; fix violations.
- [x] T011 [P] `npm run build` — all blocks compile; `npm run test:js` + `composer test` green. Record.
- [x] T012 [P] Verify on `./wp`: blocks register (dynamic, Corex category) + `corex/v1/forms` returns forms.
- [x] T013 Update docs: `addons/corex-ui/README.md` (inline editing) + **docs-app** blocks guide (inline + form selector); update `PROGRESS.md` + `DECISIONS.md`; NEXT STEP.

## Dependencies
- T002–T003 (form source) before T009. T004 (renderers) before/with T005–T008. Each block (T005–T008) is
  independent. US1 is the MVP; US2 adds the selector.

## Parallel opportunities
- T005–T008 (four blocks) are independent files. T011/T012 are [P] in polish.
