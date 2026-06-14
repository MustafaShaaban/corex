# Research: Corex Full DLS (054) — the gap analysis

Phase 0. This is the **evidence base** the spec requires: the real inventory, the token-gap findings, and a
per-candidate decision for every UI element — so the build list is justified, not invented. Decision vocabulary
(fixed): **exists-good · polish · document-core · new-block · block-style · pattern · theme · deferred.**

## D0 — Inventory (verified against the code, 2026-06-14)

- **corex/* blocks (16):** alert, badge, breadcrumbs, copyright · hero, cta, stat, testimonial, pricing,
  accordion, tabs, team, gallery, posts · entity-field (corex-blocks), corex-form (corex-forms), jobs (careers),
  projects (portfolio).
- **Patterns (5):** hero, features, cta, testimonial, contact.
- **Templates (9):** front-page, page, single, archive, search, 404, index, archive-project, single-project ·
  **Parts (2):** header, footer · **Style variations (2):** dark, editorial.
- **Tokens (theme.json):** color (13), typography (families+sizes), spacing, shadow (sm/md/lg), **radius
  (sm/md/lg/full)**, **layout (contentSize 768 / wideSize 1200)**, `elements.link`, `elements.button`.
- **Form controls (corex-forms FieldRenderer):** text/email/number/tel/url/password/date/file/textarea/select/
  radio/checkbox-group/checkbox/toggle.

## D1 — Foundations gap (corrects the spec's assumption)

- **Decision:** radius and grid/layout **already exist** — the spec listed them as gaps, but the evidence shows
  only **motion, focus, and z-index** are actually missing. Add those three token groups to `theme.json`
  `settings.custom`; **document all** groups (existing + new) in a Foundations guide.
  - `custom.motion`: `duration.{fast,base,slow}` + `easing.{standard,emphasized}` (e.g. 150/250/400ms; cubic-béziers).
  - `custom.focus`: `width`, `color` (→ accent), `offset` — for a single consistent focus ring.
  - `custom.z`: `base/dropdown/sticky/overlay/modal/toast` scale (e.g. 0/1000/1100/1200/1300/1400).
- **Rationale:** components must consume a token, never hardcode (Principle V); the modal (overlay/z), focus
  rings, and any transition need tokens that don't exist yet. radius/layout just need documenting.
- **Alternatives:** inventing a parallel radius/layout set (rejected — duplicates existing tokens, causes drift).

## D2 — Component gap analysis (the candidate list → decisions)

| Candidate | Decision | Location | Why |
|---|---|---|---|
| **alert** | exists-good | corex-ui | `corex/alert` registered (051). |
| **badge** | exists-good | corex-ui | `corex/badge` registered (051). |
| **breadcrumbs** | exists-good | corex-ui | `corex/breadcrumbs` registered. |
| **accordion** | exists-good | corex-ui | `corex/accordion` (native `<details>`, accessible, no JS). |
| **tabs** | exists-good | corex-ui | `corex/tabs` (CSS-only, accessible). |
| **card** | block-style | corex-ui | A style on `core/group` (`is-style-corex-card`): surface bg, border, radius, shadow tokens. No data/behavior → no block. |
| **section** | block-style | corex-ui | A style on `core/group` (`is-style-corex-section`): vertical rhythm + container. Layout, not a block. |
| **table/list** | document-core + block-style | docs + corex-ui | `core/table`/`core/list` exist; add a striped/bordered `core/table` block style; document. No custom block. |
| **button** | document-core + block-style | docs + corex-ui | `core/button` exists (theme.json already styles it). Add variant block styles (secondary/ghost/link); document when-to-use. |
| **link** | document-core | docs | `elements.link` already styled in theme.json; document. |
| **search** | document-core | docs | `core/search` exists; document with a Corex style note. |
| **dropdown / menu** | document-core | docs | `core/navigation` submenus; document. No custom block. |
| **pagination** | document-core | docs | `core/query-pagination` for content; the Data screen's own pagination is internal (053). Document. |
| **image / media+text / columns / gallery** | document-core (+ gallery exists) | docs | core blocks; `corex/gallery` already exists for the agency grid. Document. |
| **empty state** | block-style/pattern | corex-ui | A token-only `is-style-corex-empty` + an empty-state **pattern**; reused by Data/listing. No block. |
| **loading / skeleton / spinner** | block-style (CSS util) | corex-ui | A token-only **skeleton** shimmer class + document the `@wordpress/components` Spinner already used (053). No block. |
| **toast / notification** | document (runtime) | docs (corex-core) | The spec-043 `window.Corex.notices` is the transient-notification mechanism; document it. Not a placed block. |
| **tooltip** | block-style/CSS | corex-ui | A CSS-only tooltip treatment on an element + document; a full JS tooltip is **deferred**. |
| **modal / dialog** | **new-block** | corex-ui | **`corex/modal`** — JUSTIFIED: native `<dialog>` + focus-trap/ESC/`inert` is reusable **accessibility behavior** core has no block for; degrades without JS. |
| **drawer (off-canvas)** | deferred | — | Builds on the modal pattern; lower priority. Named follow-up. |
| **popover** | deferred | — | Core Interactivity popover is complex; not agency-critical yet. |
| **dropdown (custom select)** | deferred | — | Native `<select>` (forms) covers it; a styled custom one is a forms concern. |
| **stepper** | deferred | — | Niche; revisit if a multi-step form/checkout need appears. |
| **validation summary** | deferred (corex-forms) | — | Forms already show per-field errors; a summary region is a corex-forms polish, out of DLS-core scope. |
| **upload** | exists-good (forms) | corex-forms | The `file` field in `FieldRenderer`; document, don't rebuild. |
| **form controls** | exists-good (forms) | corex-forms | `FieldRenderer` covers all input types; the DLS documents + references them. |

**Net new build:** **1 block** (`corex/modal`) · **5 block styles** (button-secondary, button-ghost, card,
section, table-striped) + an **empty-state** style + a **skeleton** utility · everything else is docs or exists.
This is the deliberate "don't custom-block everything" outcome the brief demanded.

## D3 — `corex/modal` design

- **Decision:** a dynamic `corex/modal` block — a trigger button + a native `<dialog>` with a labelled heading +
  inner blocks; opens via a small **Interactivity API**/vanilla `view.js` (`dialog.showModal()`), traps focus
  (native `<dialog>` does), closes on ESC + backdrop + a close button, returns focus to the trigger. Token-only,
  `aria-labelledby`, `role` from `<dialog>`. **Without JS** the trigger is a same-page anchor to the content
  (degrades; content still readable).
- **Rationale:** native `<dialog>` gives focus-trap + `::backdrop` + ESC for free (less JS, better a11y); the one
  justified custom block (FR-004 — reusable a11y behavior core lacks).
- **Alternatives:** a pattern of core blocks (rejected — no focus management); a heavy JS modal lib (rejected —
  Principle IX/VI).

## D4 — Block styles vs blocks (the native-first rule, applied)

- **Decision:** card/section/table-striped/button-variants/empty-state ship via `register_block_style()` (a
  `corex-ui` `BlockStyles` registrar) with token-only SCSS, **not** new blocks.
- **Rationale:** they add *appearance* to existing core/corex blocks — no data, no behavior — so a block style is
  the lightest correct mechanism (FR-009). Keeps the block count honest.
- **Alternatives:** new wrapper blocks (rejected — duplicates core/group + core/table; inserter clutter).

## D5 — Catalog expansion + drift discipline

- **Decision:** extend `DesignSystemCatalog` so each taxonomy layer is fully enumerated: Foundations (the token
  groups), Components (atoms incl. the new modal + the block-style entries, tagged by mechanism), Blocks (the
  section blocks), Patterns, Templates, Guidelines. Keep the existing **drift test** (every listed `corex/*`
  block is registered) and extend it to the new block; add a flag/field so block-style and core-backed entries
  are not mistaken for registered blocks (they have `block: null` or a `mechanism` field).
- **Rationale:** FR-003 — the catalog must never list a block that doesn't exist, even as it grows.
- **Alternatives:** a separate catalog for styles (rejected — one registry is the 051 decision).

## D6 — Patterns & templates to add (US4)

- **Patterns (new, justified):** section-header, content-split (on `core/media-text`), stats (composes
  `corex/stat`), FAQ (composes `corex/accordion`), posts/news (composes `corex/posts`). Already exist: hero,
  features, cta, testimonial, contact.
- **Templates (new, justified):** `page-landing` (landing), `page-contact` (contact), `page-form` (form page).
  Already cover the rest: front-page=homepage, page=inner, archive=listing, single=detail.
- **Rationale:** patterns compose **real** blocks (pattern-accuracy test guards drift); templates are FSE
  presentation (Principle I). Both are the lightest mechanism for "a page type".
- **Alternatives:** blocks for sections (rejected — sections are compositions = patterns).

## D7 — Documentation (US4) + the docs honesty rule

- **Decision:** a docs-app **design-system section** — Foundations (one page per token group + grid/icons/motion/
  focus/RTL/a11y guidelines), Components (a page each, with attributes + when-to-use / when-not-to-use), Patterns,
  Templates. The gap analysis (this file's D2 table) is published as the "what exists / what's core / what's
  deferred" reference. Applied in the same PR (§D.5).
- **Rationale:** FR-014 — the system is only usable if documented; honesty about core-backed vs custom matters.
- **Alternatives:** docs-only-later (rejected — violates §D.5).

## D8 — Verification & env-gating

- **Decision:** Pest for the catalog/drift, the modal renderer, block-style registration, and pattern-accuracy;
  Jest for the modal editor registration; the spec-052 Playwright/console sweep for the modal's focus/ESC/RTL and
  console-clean — **execution env-gated** (needs wp-env + a browser), recorded honestly, not skipped.
- **Rationale:** the headless tests fully cover structure/registration/drift; only the interactive modal behavior
  needs a browser (the standing env-gate).
