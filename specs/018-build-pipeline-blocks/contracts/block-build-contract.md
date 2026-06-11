# Contract — block-package build & discovery

The convention a block package MUST follow so the engine discovers, registers, and the editor recognises its
blocks. (This is the contract `wp corex make:block` scaffolds and the engine consumes.)

## Package build script

`package.json`:

```json
{ "scripts": { "build": "wp-scripts build --webpack-src-dir=<SRC> --output-path=build/blocks" } }
```

- `<SRC>` is the package's source blocks directory.
- Output MUST land in `build/blocks/<slug>/` per block.

## Block source folder (`<SRC>/<slug>/`)

- `block.json` — apiVersion 3; `name:"corex/<slug>"`; `category:"corex"`; `editorScript:"file:./index.js"`;
  `style:"file:./style-index.css"`; optional `viewScript:"file:./view.js"`; `corex.renderer:"<FQCN>"`.
- `index.js` — `import './style.scss';` then `registerBlockType(metadata.name, { edit: ServerSideRender…, save: () => null })`.
- `style.scss` — token-only, logical CSS.
- (optional) `view.js` — front-end behaviour.

## Renderer (PHP)

Implements `Corex\Blocks\BlockRenderer::render(array $attributes, string $content, object $block): string` and
returns escaped, bounded markup. Registered via the block's `corex.renderer` and the container.

## Engine guarantees (consumer side)

- `BlockMap::discover($dir)` finds each `<dir>/*/block.json`, de-dupes by name (first wins), skips malformed.
- A provider discovers from `build/blocks` when present, else the source dir.
- `DynamicBlockRegistrar::register` calls `register_block_type($dir, ['render_callback' => …])`, wires
  `wp_set_script_translations` for editor/view/script handles, and the "Corex" inserter category is registered
  once via `block_categories_all`.

## Verification (machine-checkable)

- Each `corex/*` registered block type exposes a non-empty `editor_script_handles`.
- `do_blocks('<!-- wp:corex/<slug> /-->')` returns the renderer's escaped output (or a safe empty state).
- Built `style-index-rtl.css` exists for every block with a stylesheet.
