# Data model — build pipeline & block editor registration (018)

No database entities (blocks are dynamic and read via their own renderers). The "model" here is the
**structural contract** of the build artifacts and block metadata.

## Block package

A plugin/add-on that owns one or more blocks.

| Field | Meaning |
|---|---|
| source blocks dir | where block folders live (e.g. `src/blocks`, `src/Blocks`, `src/Block/blocks`, `blocks`) |
| build output dir | `build/blocks/` (per package; git-ignored) |
| build script | `package.json` → `wp-scripts build --webpack-src-dir=<src> --output-path=build/blocks` |

## Block

| Field | Meaning |
|---|---|
| `name` | `corex/<slug>` |
| `block.json` | apiVersion 3; `category:"corex"`; `editorScript:file:./index.js`; `style:file:./style-index.css`; optional `viewScript`; `corex.renderer` = PHP FQCN |
| `index.js` | editor: `registerBlockType` + `<ServerSideRender>` (+ InspectorControls); `import './style.scss'` |
| `style.scss` | token-only, logical CSS |
| `view.js` | optional front-end behaviour (e.g. the form submit handler) |
| renderer (PHP) | implements `Corex\Blocks\BlockRenderer`; produces escaped, bounded markup |

## Build output (per block, post-build)

| Artifact | Purpose |
|---|---|
| `block.json` (copied) | registration metadata with resolved asset paths |
| `index.js` + `index.asset.php` | editor script + its WP script dependencies/version |
| `style-index.css` | compiled, minified front+editor style |
| `style-index-rtl.css` | auto-generated RTL stylesheet |
| `view.js` (+ `view.asset.php`) | when the block ships a front-end view script |

## Invariants

- A block registers from `build/blocks/<name>` when it exists, else its source folder.
- No block stylesheet contains a hardcoded colour/size/font (enforced by a unit test).
- A block's assets enqueue only when the block renders on the page.
