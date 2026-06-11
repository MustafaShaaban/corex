# Contract: Corex component blocks

## block.json (each block)

```jsonc
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "corex/<slug>",
  "title": "Corex <Name>",
  "category": "corex",
  "icon": "<dashicon>",
  "description": "<one line>",
  "textdomain": "corex",
  "supports": { "html": false },
  "attributes": { /* per data-model.md */ },
  "editorScript": "file:./index.js",
  "style": "file:./style-index.css",
  "corex": { "renderer": "Corex\\Ui\\Blocks\\<Name>Renderer" }
}
```

## index.js (each block)

- `import './style.scss';`
- `registerBlockType(metadata.name, { edit, save: () => null })`.
- `edit` renders `<InspectorControls>` with a `TextControl`/`TextareaControl` per attribute (`__()` labels) and a
  `<ServerSideRender block={metadata.name} attributes={attributes} />` preview inside `useBlockProps()`.

## Renderer (each block)

- `final class <Name>Renderer implements Corex\Blocks\BlockRenderer` in `addons/corex-ui/src/Blocks/`.
- `render(array $attributes, string $content, object $block): string` → escaped, accessible HTML per
  data-model.md; graceful defaults; never a notice/fatal.

## Inserter / manifest expectations

- Each block appears under the **Corex** category with its title, icon, description.
- `UiManifest` enumerates each `corex/<slug>` (kits can compose them) with no engine change.
- `npm run build` compiles each `style.scss` → `style-index.css` (+ `style-index-rtl.css`) and `index.js`.
