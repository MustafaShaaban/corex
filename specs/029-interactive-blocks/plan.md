# Implementation Plan: Interactive, inline-editable blocks (029)

**Branch**: `feature/029-interactive-blocks` | **Date**: 2026-06-12 | **Spec**: [spec.md](./spec.md)

## Summary

Re-architect the four Corex component blocks (stat, testimonial, pricing, accordion) so their text is edited
**inline on the canvas** with `RichText`, while staying **dynamic** (the hybrid: `edit` writes attributes,
`save: () => null`, the PHP renderer reads the attributes). Replace the form block's free-text `formSlug` with a
**select populated from the registered forms**, fed by a new cap-gated read-only REST route. New editor JS is
Jest-tested and built through `@wordpress/scripts`.

## Technical Context

**Language/Version**: PHP 8.3 + block JS (built by `@wordpress/scripts`). **Primary Dependencies**: spec-009/027
blocks, spec-018 build, the forms `FormRegistry` (spec 007). **Testing**: Jest (editor JS) + Pest (renderer
`wp_kses_post`, REST cap). **Project Type**: WP add-on/plugin blocks. **Constraints**: blocks stay dynamic
(Principle VI); rich attributes via `wp_kses_post`, plain via `esc_*`; token-only/RTL preserved; the form list
is cap-gated and exposes only slug+label.

## Constitution Check (v1.2.1)

- [x] **VI (dynamic blocks)** — PASS. Blocks remain `save:()=>null` + server-rendered; inline editing writes
  attributes the renderer reads. No static save markup.
- [x] **VII (security)** — PASS. Rich text → `wp_kses_post`; plain → `esc_html`/`esc_url`. The form-list REST
  route is read-only, `edit_posts`-gated, returns only slug+label (no submissions/secrets).
- [x] **V/VIII (tokens/RTL/i18n)** — PASS. Styling unchanged (token-only, logical CSS); RichText placeholders
  translatable.
- [x] **III/IV (layering/DI)** — PASS. The form-list provider is a thin REST controller over the existing
  `FormRegistry`; no `new` of services in methods.
- [x] **X (spec)** — implements spec 029.
- [x] **Guard Gate/DoD** — planned: wp-guard (kses/escaping, REST cap) + clean-code + test-guard. Jest for the
  editor components; Pest for the renderer + REST. Docs + docs-app updated.

**Gate**: PASS.

## Design

### Inline editing (stat / testimonial / pricing / accordion)
- `edit`: render `RichText` for each text field (`tagName`, `value={attributes.x}`,
  `onChange={(x)=>setAttributes({x})}`, translatable `placeholder`). Keep `InspectorControls` only for
  non-text options (e.g. accordion is structured — see below). Wrap in `useBlockProps()`.
- `save: () => null` (unchanged — dynamic).
- **Renderers**: output rich fields with `wp_kses_post`, plain fields with `esc_html`/`esc_url`. Accordion items
  move from a single delimited string to an `items` array attribute (`[{title, content}]`) edited via repeatable
  RichText rows (InnerBlocks-free, attribute-array); the renderer iterates the array. Backwards: the old
  delimited-string parse is kept as a fallback so already-placed accordions still render (FR-008).

### Form selector (corex/form)
- New REST route `corex/v1/forms` (GET, `edit_posts`) → `[{slug,label}]` from `FormRegistry`.
- `edit`: a `SelectControl` (or `ComboboxControl`) in `InspectorControls` populated via `apiFetch`/`useSelect`;
  empty state when none. Keeps `ServerSideRender` preview (the form is data, not inline text).
- Attribute stays `formSlug` (no rename → no migration); only the control changes.

## FR → component map

| FR | Built in |
|---|---|
| FR-001/002/004 inline + dynamic | `addons/corex-ui/src/Blocks/{stat,testimonial,pricing,accordion}/index.js` (RichText) + their renderers |
| FR-003 safe rich render | the four `*Renderer.php` (`wp_kses_post` for rich, `esc_*` for plain) |
| FR-005/006 form selector + source | `plugins/corex-forms/src/Block/blocks/corex-form/index.js` (SelectControl) + a new `FormsListController` REST route over `FormRegistry` |
| FR-007 Jest + build | `*/index.test.js` for each changed block; `npm run build` |
| FR-008 backwards data | accordion renderer keeps the delimited-string fallback |

## Project Structure (changed/new)

```text
addons/corex-ui/src/Blocks/{stat,testimonial,pricing,accordion}/index.js     (rewritten: RichText)
addons/corex-ui/src/Blocks/{Stat,Testimonial,Pricing,Accordion}Renderer.php  (wp_kses_post for rich fields)
addons/corex-ui/src/Blocks/{stat,testimonial,pricing,accordion}/index.test.js (Jest)
plugins/corex-forms/src/Block/blocks/corex-form/index.js                      (form SelectControl)
plugins/corex-forms/src/Submission/FormsListController.php                    (GET corex/v1/forms, cap-gated)
plugins/corex-forms/src/FormsServiceProvider.php                              (register the route)
tests/Unit/Ui/ComponentBlocksTest.php                                         (renderer kses assertions)
tests/Unit/Forms/FormsListControllerTest.php                                  (cap + shape)
```

## Phase 0/1 artifacts
- `research.md` — the dynamic-and-inline hybrid; kses vs esc; the form-list source choice.
- `data-model.md` — the block attributes (incl. accordion `items` array) + the form-option shape.
- `contracts/` — the `corex/v1/forms` route + the block edit/render contract.
- `quickstart.md` — Jest + build + a real-WP REST/route check.

## Complexity Tracking
No unjustified violations. The accordion's string→array attribute is the only data-shape change; a fallback
keeps old content rendering. Browser-visual confirmation of inline editing is env-gated.
