# Research — build pipeline & block editor registration (018)

Retrospective: the decisions below were made during implementation and are recorded here for the spec trace.
Full rationale is in `DECISIONS.md` #43 (and the regression fix #55).

## R1 — Build tool: @wordpress/scripts (not bespoke webpack / Vite)

- **Decision**: Use `@wordpress/scripts` (webpack + Babel + Sass + PostCSS) via npm workspaces.
- **Rationale**: WordPress-standard; gives SCSS compilation, minification, **automatic RTL CSS**, and the
  `*.asset.php` dependency manifest for free, with no config to drift. One shared convention; per-package
  `build` scripts pass `--webpack-src-dir`/`--output-path`.
- **Alternatives**: a hand-written webpack config (more to maintain, no benefit) and Vite (great DX, but
  diverges from the WP asset/`asset.php` ecosystem the editor expects).

## R2 — Dynamic-block editor presence: ServerSideRender (not a duplicated JS render)

- **Decision**: Each block ships an `index.js` that `registerBlockType`s and previews via
  `<ServerSideRender block=… attributes=… />`.
- **Rationale**: Blocks are dynamic/server-rendered; ServerSideRender makes the editor preview the **same** PHP
  output — one source of truth — and registering the type is what removes "block not supported".
- **Alternatives**: a JS `save()`/`edit()` re-implementation (two renderers to keep in sync — rejected);
  leaving blocks server-only (the original broken state — editor can't place them).

## R3 — SCSS extraction: import `style.scss` in `index.js`; reference compiled name in `block.json`

- **Decision**: `index.js` does `import './style.scss'`; `block.json` `style` points at the compiled
  `style-index.css`.
- **Rationale**: wp-scripts only compiles styles imported in JS; a file literally named `style.*` is extracted
  to `style-index.css` (+ `style-index-rtl.css`). Verified empirically before adopting.

## R4 — Discovery: prefer `build/blocks`, fall back to source

- **Decision**: each provider registers from `build/blocks` when present, else the source dir.
- **Rationale**: real installs build (editor scripts + compiled assets load); the headless Pest suite runs with
  no build (registers from source). Keeps the PHP core build-independent.

## R5 — Grouping: a single "Corex" inserter category

- **Decision**: register a `corex` block category via `block_categories_all`; every block sets `category:"corex"`.
- **Rationale**: a professional, unified inserter grouping; mirrors the existing Corex *pattern* category.
