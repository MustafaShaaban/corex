# Corex Build Tools

The Corex front-end build pipeline — SCSS + JS for blocks — built on
[`@wordpress/scripts`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/)
(webpack + Babel + Sass + PostCSS, the WordPress-standard toolchain). No bespoke
webpack config to maintain; we use the conventions wp-scripts already enforces.

## Why a build exists

Corex blocks are **dynamic** — their markup is produced server-side by a PHP
renderer (the `corex.renderer` key in `block.json`). But a dynamic block still
needs two things the editor can only get from JavaScript:

1. **Editor registration.** Without `registerBlockType()` running in the editor,
   WordPress shows *"Your site doesn't include support for this block."* Each block
   ships an `index.js` that registers the type and previews the server render with
   [`<ServerSideRender>`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-server-side-render/)
   — so the editor preview is byte-for-byte the front-end output, never a second
   implementation to keep in sync.
2. **Compiled, conditional assets.** SCSS compiles to a minified, **auto-RTL**
   stylesheet; `block.json` loads it only where the block renders (Principle VI).

## Layout convention

Each block package keeps block **sources** in its own folder and builds to a
package-level `build/blocks/`:

| Package | Source dir | Output |
|---|---|---|
| `plugins/corex-blocks` | `src/blocks` | `build/blocks` |
| `plugins/corex-forms` | `src/Block/blocks` | `build/blocks` |
| `addons/corex-ui` | `src/Blocks` | `build/blocks` |
| `addons/corex-careers` | `blocks` | `build/blocks` |

A block folder contains:

```
my-block/
  block.json     # apiVersion 3; editorScript: file:./index.js;
                 #   style: file:./style-index.css; corex.renderer: <FQCN>
  index.js       # editor registration; imports ./style.scss
  style.scss     # token-only, logical properties (RTL-correct)
  view.js        # OPTIONAL front-end behaviour (forms submit, Interactivity)
```

`index.js` must `import './style.scss';` — wp-scripts extracts any file literally
named `style.*` to `style-index.css` (the block's front-end + editor `style`) and
also emits `style-index-rtl.css` automatically. The `block.json` `style` field
therefore points at the **compiled** name, `file:./style-index.css`.

## Commands

From the repo root:

```bash
npm install            # once — installs @wordpress/scripts for all workspaces
npm run build          # build every block package (production)
npm run start          # watch mode across packages (development)
npm run lint:js        # wp-scripts lint-js
npm run lint:css       # wp-scripts lint-style
npm run format         # wp-scripts format
```

Per package: `npm run build --workspace=@corex/ui` (or `@corex/blocks`,
`@corex/forms`, `@corex/careers`).

## How PHP finds the build

Each block package's service provider registers from `build/blocks` **when it
exists**, falling back to the source dir otherwise:

```php
$built = dirname(__DIR__) . '/build/blocks';
$blocksDir = is_dir($built) ? $built : __DIR__ . '/Blocks';
```

So **run `npm run build` after install** (and after changing any block) for the
editor registration and compiled styles to load. `build/` is git-ignored —
it is a generated artifact, rebuilt on checkout and in CI before tests.

## i18n

`__()` strings in `index.js` use the literal `corex` text domain. The registrar
(`DynamicBlockRegistrar`) wires `wp_set_script_translations()` to each block's
editor script handle, so the strings resolve from `corex-*.json` language packs
when present.
