# Implementation Plan: Shared form validation schema & flexible form builder (020)

**Branch**: `020-forms-validation-builder` (uncommitted on `develop`) | **Date**: 2026-06-11 | **Spec**: [spec.md](./spec.md)

> Retrospective plan — maps each FR to the file that already satisfies it and flags drift. No new architecture.

## Summary

Two additions to the spec-007 forms engine: (1) a pure `SchemaExporter` + a JS validator that mirrors the PHP
rules, embedded by the block so client and server validate one schema; (2) an extended `FieldSchema` +
dedicated `FieldRenderer` (SRP) that renders every input type with full presentation control, accessibly and
token-only. The submit lifecycle (secured REST route, nonce/throttle/sanitize, listeners) is unchanged.

## Technical Context

**Language/Version**: PHP 8.3 + JS (ESnext, built by `@wordpress/scripts`). **Primary Dependencies**: the
spec-007 forms engine (`Validator`, `RuleRegistry`, `SchemaResolver`, `FieldSchema`), the spec-018 block build.
**Testing**: Pest (PHP) + Jest (`@wordpress/scripts test-unit-js`). **Project Type**: WP plugin (`corex-forms`).
**Constraints**: pure exporter (no WP/encoding); rendered markup token-only + RTL + escaped + i18n; `attrs`
whitelisted; server authoritative.

## Constitution Check (v1.2.0)

- [x] **III/IV (layering + DI)** — PASS. `SchemaExporter` + `FieldRenderer` are pure, injected; `FormBlockRenderer`
  thin, delegates. No `new` of a service in a method.
- [x] **V/VIII (tokens + RTL)** — PASS. `FieldRenderer` markup is token-only (FormBlockRenderTest token scan) +
  logical CSS in `style.scss` (built to `style-index.css` + `-rtl.css`).
- [x] **VI (dynamic blocks)** — PASS. Server-rendered block; conditional view script.
- [x] **VII (security)** — PASS. All output escaped; `attrs` whitelist drops reserved + `on*`; server
  re-validates (authoritative); the unchanged REST route keeps nonce/throttle/sanitize.
- [x] **VIII (i18n)** — PASS. Labels/messages translatable; `wp_set_script_translations` wired for view + JS.
- [x] **IX (optional dep)** — N/A (no optional plugin dependency added).
- [x] **X (spec)** — reconciled by this retrospective spec.
- [x] **Guard Gate / DoD** — PARTIAL. wp-guard run on item 4's diff; a formal full re-run (wp + clean-code +
  test + docs) is **P2**. Tests: SchemaExporterTest (3) + FieldRendererTest (7) + FormBlockRenderTest
  additions + validation.test.js (8 Jest), green.

**Gate**: PASS (P2 formal guard re-run tracked).

## FR → implementation map

| FR | Satisfied by |
|---|---|
| FR-001 exporter | `plugins/corex-forms/src/Schema/SchemaExporter.php` (`toArray`, pure) |
| FR-002 embed schema + error regions | `plugins/corex-forms/src/Block/FormBlockRenderer.php` (`data-corex-schema`, `role="alert"`, `aria-describedby`) |
| FR-003 client mirror + AJAX handler | `…/blocks/corex-form/{validation.js, view.js}` (mirror rules; client-validate → POST → render server errors) |
| FR-004 server authoritative | spec-007 `Submission/{SubmitController,FormSubmissionService}` re-validating via `Validation/Validator` |
| FR-005 presentation knobs | `plugins/corex-forms/src/Schema/FieldSchema.php` (`options/labelMode/width/cssClass/attrs`, defaulted) + `SchemaResolver` |
| FR-006 field renderer | `plugins/corex-forms/src/Block/FieldRenderer.php` (input/textarea/select/checkbox/group; fieldset+legend; `name[]`) |
| FR-007 attr safety | `FieldRenderer::extraAttrs()` (whitelist; drops reserved + `on*`; escaped) |
| FR-008 thin block | `FormBlockRenderer` delegates per-field to `FieldRenderer` |

**Drift found:** none material. Clean-code audit noted `FieldSchema`'s 10-parameter constructor (value-object
exception applies; documented or move to a presentation config object → tracked **P3**, finding #5).

## Project Structure (already implemented)

```text
plugins/corex-forms/src/
├── Schema/{FieldSchema,SchemaResolver,SchemaExporter}.php
├── Block/{FormBlockRenderer,FieldRenderer}.php
└── Block/blocks/corex-form/{block.json,index.js,view.js,validation.js,validation.test.js,style.scss}
tests/Unit/Forms/{SchemaExporterTest,FieldRendererTest,FormBlockRenderTest}.php
```

## Complexity Tracking

No unjustified violations. P2 (guards) and P3 (`FieldSchema` ctor / presentation config object) are remediation,
not new complexity. Deferred (documented): multi-section fieldset grouping; checkbox-group array server sanitize.
