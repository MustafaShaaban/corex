# Data Model: Corex Full DLS (054)

No persistent storage. These are the **catalog/registry and presentation shapes** the feature operates on.

## 1. CatalogEntry (expanded — `DesignSystemCatalog`)

| Field | Type | Notes |
|---|---|---|
| `name` | string | display name (e.g. "Modal", "Card", "Radius") |
| `category` | enum | `foundation` · `component` · `block` · `pattern` · `template` · `guideline` |
| `block` | string\|null | the `corex/*` block name when the entry **is** a registered block, else null |
| `mechanism` | enum | `corex-block` · `block-style` · `core-block` · `pattern` · `template` · `token` · `runtime` · `deferred` — how the entry is delivered |

**Rules / drift:** every entry with `mechanism = corex-block` MUST have a non-null `block` that is **registered**
(the drift test fails otherwise); entries with `block-style`/`core-block`/`token`/`runtime`/`deferred` have
`block = null` and are **not** subject to the registered-block check (so they can't be mistaken for blocks).

## 2. GapRecord (research artifact, published in docs)

| Field | Type | Values |
|---|---|---|
| `candidate` | string | the UI element (button, modal, toast, …) |
| `decision` | enum | `exists-good` · `polish` · `document-core` · `new-block` · `block-style` · `pattern` · `theme` · `deferred` |
| `location` | enum | `corex-ui` · `corex-forms` · `theme` · `docs-only` · `generated-client-site` · `deferred` |
| `rationale` | string | one line |
| `tests_needed`, `docs_needed` | string | what each item requires |

**Rule:** every candidate carries exactly one `decision` (no unclassified candidate — SC-002).

## 3. TokenGroup (theme.json `settings.custom` + presets)

| Group | Status | Tokens |
|---|---|---|
| color, typography, spacing, shadow | exists | document only |
| radius, layout | exists | document only (the spec mis-listed these as gaps) |
| **motion** | new | `duration.{fast,base,slow}`, `easing.{standard,emphasized}` → `--wp--custom--motion--*` |
| **focus** | new | `width`, `color`, `offset` → `--wp--custom--focus--*` |
| **z (z-index)** | new | `base,dropdown,sticky,overlay,modal,toast` → `--wp--custom--z--*` |

**Rule:** runtime CSS custom properties; `brand.json` overrides at runtime (Principle V); components consume the
variable, never a literal.

## 4. Component (delivered shape)

A UI atom delivered as one of: a `corex/*` block (modal), a registered **block style** (card/section/table-
striped/button-variants/empty-state), a **documented core block** (button/link/search/dropdown/pagination/table/
list/image), a **token-only CSS utility** (skeleton), or a **runtime API** (toast = `window.Corex.notices`). Each
carries: role + ARIA semantics, attributes/props, when-to-use / when-not-to-use, and consumes only tokens.

## 5. Pattern / Template (composition)

- **Pattern:** a named composition of **real registered** blocks/parts (pattern-accuracy test guards drift).
  New: section-header, content-split, stats, FAQ, posts/news.
- **Template:** an FSE page-type layout (no business logic — Principle I). New: `page-landing`, `page-contact`,
  `page-form`.

## 6. ModalBlock (`corex/modal`)

| Aspect | Value |
|---|---|
| attributes | `title` (label), `triggerLabel`, inner blocks (content) |
| markup | a trigger `<button>` + native `<dialog aria-labelledby>` with heading + content + close button |
| behavior | `view.js`: open (`showModal()`), ESC/backdrop/close → `close()`, focus returns to trigger; native focus-trap |
| no-JS | trigger is a same-page anchor to the content; content readable; degrades |
| tokens | overlay z (`--wp--custom--z--modal`), radius, shadow, focus ring — all tokens |
