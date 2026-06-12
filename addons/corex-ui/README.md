# Corex UI

The Corex UI block library: server-rendered `corex/*` dynamic blocks plus a curated set of
token-only, accessible, RTL section patterns under a single **Corex** inserter category. Designs
are composed of these units. Optional add-on; requires `corex-core` and the `corex-blocks` engine.

> Dynamic blocks are server-rendered (spec-004 engine) with editor registration + compiled token-only
> styles via the `@wordpress/scripts` build pipeline (spec 018); content sections are block patterns.
> Run `npm run build` to compile each block's editor script + `style-index.css` (+ RTL).

## Dynamic blocks

| Block | Renders |
|---|---|
| `corex/posts` | Recent posts as accessible linked cards (`count` attribute, bounded 1–12; empty state) |
| `corex/breadcrumbs` | An accessible `nav` breadcrumb trail to the current page |
| `corex/copyright` | The current year + site name (footer line) |
| `corex/stat` | A single statistic — `value`, `label`, optional `description` |
| `corex/testimonial` | A quote with attribution — accessible `figure`/`blockquote`/`figcaption` (`quote`/`author`/`role`) |
| `corex/pricing` | A pricing card — `plan`, `price`, `period`, `features` (one per line), optional CTA |
| `corex/accordion` | Accessible disclosures from `items` (one `Title \| Content` per line) — native `<details>`, no JS |

Each is server-rendered, escaped, and token-styled; its CSS loads only where the block renders
(declared in `block.json`). The component blocks (`stat`/`testimonial`/`pricing`/`accordion`) are **edited
inline on the canvas** (RichText) while staying dynamic — the renderer reads the attributes and renders rich
text safely with `wp_kses_post`; repeatable lists (pricing features, accordion panels) are inline rows. The
`corex/form` block **selects a form from a dropdown** (the cap-gated `corex/v1/forms` route), not a typed slug.
(Spec 029.)

## Section patterns

Under the **Corex** inserter category: **hero, features, call-to-action, testimonial, contact**.
The contact pattern composes the `corex/form` contact block (spec 007). Every pattern is styled
only with `theme.json` presets (color slugs + `var:preset` spacing), uses logical CSS (RTL-correct),
is translation-ready, and is intentionally **neutral** — a client brand restyles it via `brand.json`
with no markup edits.

## Manifest

`UiManifest::describe()` enumerates the dynamic blocks (read from their `block.json` files, so it
cannot drift) and the section patterns with their category — for kits/tooling (spec 010) to discover
and compose.

## Tests

```bash
composer test   # headless: dynamic-block renders, token-only assertion, manifest
```

> Block/pattern **registration** and token-only/accessibility structure are covered headlessly.
> The **editor/visual** validity of the pattern markup should be confirmed in a browser.
