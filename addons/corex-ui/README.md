# Corex UI

The Corex UI block library: server-rendered `corex/*` dynamic blocks plus a curated set of
token-only, accessible, RTL section patterns under a single **Corex** inserter category. Designs
are composed of these units. Optional add-on; requires `corex-core` and the `corex-blocks` engine.

> No JS build in this version — dynamic blocks are server-rendered (spec-004 engine) and content
> sections are block patterns. Custom JS-edit blocks + the build pipeline are a later spec.

## Dynamic blocks

| Block | Renders |
|---|---|
| `corex/posts` | Recent posts as accessible linked cards (`count` attribute, bounded 1–12; empty state) |
| `corex/breadcrumbs` | An accessible `nav` breadcrumb trail to the current page |
| `corex/copyright` | The current year + site name (footer line) |

Each is server-rendered, escaped, and token-styled; its CSS loads only where the block renders
(declared in `block.json`).

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
