# Tasks: Shared form validation schema & flexible form builder (020)

**Retrospective spec** — the implementation exists, is unit-tested (Pest + Jest), and verified on real WP (the
contact block embeds the exact schema + field hooks + error regions). These are **reconciliation/verification**
tasks: confirm each FR against the mapped file/behaviour (most already satisfied, marked `[x]`), plus the
tracked debts (a formal Guard Gate re-run, remediation **P2**; the `FieldSchema` constructor / presentation
config object, remediation **P3**). The FR→file map is in `plan.md`.

**No new implementation work** beyond the tracked P2/P3 debts — flag any mismatch found as a defect rather
than scope.

## Phase 1: Setup (verification context)

- [x] T001 Confirm the forms engine base (spec 007) is present: `Validation/{Validator,RuleRegistry,Rules/*}`, `Schema/{SchemaResolver,FieldSchema}`, `Submission/{SubmitController,FormSubmissionService}`.
- [x] T002 Confirm the block builds: `Block/blocks/corex-form/{index.js,view.js,validation.js,style.scss}` present and `npm run build` emits `view.js` + compiled styles (spec 018).

## Phase 2: Foundational (the shared definition — blocks both stories)

- [x] T003 Verify `FieldSchema` is immutable and carries name/type/label/rules/required + presentation knobs (`options`, `labelMode`, `width`, `cssClass`, `attrs`) with defaults, plus `isChoice()`.

## Phase 3: User Story 1 — One validation schema, front + back (P1) 🎯 MVP

**Goal**: the PHP schema is the single source of truth; the block embeds it; client + server validate against it.
**Independent test**: render the block (embeds `data-corex-schema` + error regions); PHP `Validator` and `validation.js` agree per field.

- [x] T004 [US1] Verify FR-001: `SchemaExporter::toArray()` serializes name/type/label/required/rules into a JSON-able list and does no encoding (`tests/Unit/Forms/SchemaExporterTest.php` — exports each field; JSON-serializable round-trip; empty list).
- [x] T005 [US1] Verify FR-002: `FormBlockRenderer` embeds `data-corex-schema` (`esc_attr(wp_json_encode(...))`) + a per-field `role="alert"` region wired with `aria-describedby` (`tests/Unit/Forms/FormBlockRenderTest.php` — "embeds the exported schema and accessible error regions").
- [x] T006 [US1] Verify FR-003 + SC-001: `validation.js` mirrors the PHP rules (required/email/max/min/numeric, bail per field) and `view.js` client-validates → POSTs valid data to the secured REST route → renders server errors (`validation.test.js` — 8 cases parallel to `tests/Unit/Forms/ValidatorTest.php`).
- [x] T007 [US1] Verify FR-004 + SC-005: the server re-validates every submission via `Validation/Validator` and is authoritative regardless of client (`tests/Unit/Forms/FormSubmissionServiceTest.php`).

## Phase 4: User Story 2 — A field can be any input type with full control (P1)

**Goal**: every supported type renders an accessible, token-only, customizable control.
**Independent test**: resolve a field per type with presentation options; `FieldRenderer` emits the right control, honors label mode/width/class, drops unsafe attrs.

- [x] T008 [US2] Verify FR-005: `SchemaResolver` populates the presentation knobs from a field definition and a minimal `['rules'=>[...]]` still resolves (`tests/Unit/Forms/SchemaResolverTest.php`).
- [x] T009 [US2] Verify FR-006: `FieldRenderer` renders text-like inputs, textarea, select-with-options, radio/checkbox groups as `<fieldset><legend>` with `name[]` arrays, single checkbox + toggle (`tests/Unit/Forms/FieldRendererTest.php` — first five cases).
- [x] T010 [US2] Verify FR-006 (presentation) + SC-003: label modes, column width, and a custom control class render on the 12-col token grid; no hardcoded color/size in the markup (FieldRendererTest — "applies label modes, column width…"; FormBlockRenderTest — "token-only").
- [x] T011 [US2] Verify FR-007 + SC-004: `extraAttrs()` emits whitelisted attributes and drops reserved + `on*` handlers, all escaped (FieldRendererTest — "emits whitelisted extra attributes but drops reserved and event-handler ones").
- [x] T012 [US2] Verify FR-008: `FormBlockRenderer` is thin and delegates per-field markup to `FieldRenderer`.

## Phase 5: Polish & cross-cutting

- [ ] T013 [P] **(P2)** Run the Guard Gate formally on this feature's diff: `wp-guard` (block render/escaping/attr whitelist) + `clean-code-guard` (exporter/renderer) + `test-guard` (the Pest+Jest additions) + `docs-guard` (`plugins/corex-forms/README.md`); fix any reported violation. _Tracked as remediation P2._
- [x] T014 **(P3 — DONE 2026-06-11)** Documented the `FieldSchema` 10-parameter constructor as an explicit, justified value-object exception (immutable, independent, fully-defaulted presentation attributes; named-arg construction) per Clean-Code Ch.3's documented-exception clause. DECISIONS #57. _(audit finding #5)_
- [x] T015 Confirm docs: `plugins/corex-forms/README.md` covers "One schema, front + back", "Adding a validated form", and the "Field definition reference"; DECISIONS #45 (schema) + #46 (builder) record the approach; PROGRESS reflects completion.

## Dependencies

- Phase 2 (`FieldSchema`) precedes both stories. US1 (shared validation) and US2 (field renderer) are
  independent — different files — and independently verifiable.
- T013 (P2) and T014 (P3) are the only **open** tasks; both are already tracked as remediation items.

## Implementation strategy

This spec is retrospective: US1 (one schema) and US2 (full field renderer) are already delivered, unit-tested
(SchemaExporter 3 + FieldRenderer 7 + FormBlockRender additions + validation.js 8 Jest), and verified on real
WP. The remaining work is the two tracked debts (T013 → P2; T014 → P3) — **not** new feature work. Deferred and
documented: multi-section fieldset grouping; checkbox-group array server sanitize.

## Parallel opportunities

- T013 [P] (guard run) is independent of T014 (constructor tidy).
- US1 verification (Schema/SchemaExporter + validation.js) and US2 verification (FieldRenderer) touch different
  files and can be verified in parallel.
