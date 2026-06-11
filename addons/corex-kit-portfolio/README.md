# Corex Kit — Portfolio

A creative/portfolio starter kit: a `corex_project` custom post type, a dynamic
projects-grid block, and portfolio FSE templates, composed by a Blueprint.

> Requires the `corex-core` and `corex-blocks` plugins active.

## What it provides

- **Domain** — a public `corex_project` CPT (title/editor/excerpt/**thumbnail**/custom-fields,
  REST-enabled, archive at `/projects`) and a hierarchical `project_type` taxonomy. Registered
  by `PortfolioServiceProvider` on `init` (domain belongs to the add-on, never the theme).
- **Block** — `corex/projects`, a dynamic, server-rendered grid of project cards (bounded
  1–24, default 6; linked heading + lazy-loaded thumbnail; accessible empty state). Built on
  the corex-blocks engine; previews in the editor via `<ServerSideRender>`. Token-only, RTL.
- **Templates** (in the theme — the skin): `archive-project` (a grid query over `corex_project`
  with featured image + title + type) and `single-project`. Token-only, logical CSS.
- **Blueprint** — `PortfolioBlueprint` declares the kit's modules/templates/parts/patterns for
  tooling and a future setup wizard.

## Build

The block ships SCSS + JS compiled by `@wordpress/scripts`:

```bash
npm install
npm run build --workspace=@corex/kit-portfolio
```

Discovery prefers `build/blocks` and falls back to the `src/Blocks` sources headlessly.

## Architecture

The renderer (`ProjectsRenderer`) takes an injected `ProjectsProvider`, so it is
unit-testable without WordPress; `WpProjectsProvider` is the only place that queries
`WP_Query` (bounded, `no_found_rows`). All output is escaped in the renderer.

## Tests

```bash
composer test   # renderer (bounded/escaped/empty/thumbnail) + blueprint manifest accuracy
```

> The block + CPT register and render correctly on a real install (verified via WP-CLI);
> the **visual/editor** appearance of the grid and templates should be confirmed in a browser.
