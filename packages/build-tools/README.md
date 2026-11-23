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
   ships an `index.js` that registers the type and draws the block in the editor,
   by the rule of DECISIONS #656:
   - **A static block is edited in place.** Its `edit` draws the same elements as
     its renderer prints on the page, and its text is typed on the canvas, never in
     the side panel. Its `parity.json` proves it: the parity tests compare the
     editor's tree and the page's with it (below). It never imports
     `@wordpress/server-side-render`.
   - **A block that shows records keeps a server render.** A list of posts, a trail
     or a form shows records it does not hold, so its editor previews the server
     render with
     [`<ServerSideRender>`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-server-side-render/)
     inside the block's own wrapper, inert (it cannot be clicked or submitted).

   `wp corex make:block <Name>` generates the first shape, `--records` the second
   (`packages/cli/README.md`).
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
| A client site plugin, `sites/<client>/<slug>-site` | `src/Blocks` | `build/blocks` |

A client site plugin has its own `package.json` with these two scripts and its own `@wordpress/scripts`,
so `npm install && npm run build` in its folder builds its blocks with nothing from the framework's install.

A block folder contains:

```
my-block/
  block.json     # apiVersion 3; editorScript: file:./index.js;
                 #   style: file:./style-index.css; corex.renderer: <FQCN>;
                 #   corex.editor: in-place | records | none | pending
  index.js       # editor registration; imports ./style.scss
  parity.json    # an in-place block's tree, compared by the parity tests
  style.scss     # token-only, logical properties (RTL-correct)
  view.js        # OPTIONAL front-end behaviour (forms submit, Interactivity)
```

`index.js` must `import './style.scss';` — wp-scripts extracts any file literally
named `style.*` to `style-index.css` (the block's front-end + editor `style`) and
also emits `style-index-rtl.css` automatically. The `block.json` `style` field
therefore points at the **compiled** name, `file:./style-index.css`.

## The root element and the wrapper attributes

What an editor sets on a block (its HTML anchor, its alignment, its extra CSS class) and
the class WordPress generates for it (`wp-block-corex-<name>`) reach the page without the
renderer doing anything (spec 136, DECISIONS #670). The registrar passes the renderer's
markup through one collaborator (`Corex\Blocks\BlockWrapper`), which merges what
`get_block_wrapper_attributes()` returns into the markup's **root element**: each class is
added, a style is appended to the root's style, and any other attribute (`id`, `aria-label`)
is set only when the root does not already have it. Empty markup stays empty, and with
nothing to merge the markup is returned byte for byte.

So a renderer **prints exactly one root element**, and never calls
`get_block_wrapper_attributes()` itself. The root is the first element of the markup. When
something may come before it (the form's test-site notice), the block lists the classes
its root can carry in `block.json` (`corex.root`), and the attributes go on the first
element, in document order, that carries any of them. Whole class names match, so
`corex-flow` does not match `corex-flow-notice`:

```json
"corex": { "renderer": "Corex\\Forms\\Block\\FormBlockRenderer", "root": [ "corex-flow", "corex-form" ] }
```

When no element carries one, the first element is the root. The first match wins even when
it sits inside another element, so a form whose own markup wraps `<form class="corex-form">`
in an element of its own gets the attributes on the form. An anchor and an alignment reach
the page only where the block declares them in `supports` (`anchor`, `align`); the extra
class and the generated class need no declaration. The six blocks of `corex-forms` declare
`"className": false`, so they print no generated class.

The editor's preview is the exception. A `<ServerSideRender>` preview asks WordPress's block
renderer route (`/wp/v2/block-renderer/...`), and there the previewed block's markup comes
back unmerged: the editor's own wrapper already carries the attributes. Nothing changes in a block's `index.js`
for it.

## The editor kind and the parity fixture

Every `block.json` declares how its editor draws it, in `corex.editor` (spec 136, DECISIONS
#671): `in-place` (drawn with the page's elements and typed on the canvas), `records` (a
server-rendered preview of records the block does not hold), `none` (no editor), or `pending`
(its turn has not come; only the blocks on the gate's waiting list in
`tests/block-editor-kinds.test.js` may say so). The gate fails a block with no kind, an
`in-place` block with no `parity.json` or whose editor imports
`@wordpress/server-side-render`, and a `records` block whose editor does not.

An `in-place` block keeps a `parity.json` beside its `block.json`:

```json
{
	"prefix": "corex-hero",
	"attributes": { "title": "Build sites that last", "ctaText": "Start", "ctaUrl": "https://example.test" },
	"innerBlocks": [ { "name": "core/paragraph", "attributes": {}, "innerHTML": "<p>Text</p>" } ],
	"needs": [ "post" ],
	"pageOnly": [ "corex-hero__trap" ],
	"tree": [
		{ "tag": "section", "classes": [ "corex-hero" ], "children": [
			{ "tag": "h2", "classes": [ "corex-hero__title" ] },
			{ "tag": "a", "classes": [ "corex-hero__cta" ] }
		] }
	]
}
```

- `prefix`: the block's class. A class of the block is the prefix itself or the prefix followed
  by `__` or `--`; every other class (`wp-block-*`, `is-selected`) is ignored.
- `attributes`: what the block is drawn with, every field filled, so the editor's placeholders
  and the page's optional parts are all drawn.
- `innerBlocks` (optional): the child blocks, each `name`, `attributes`, `innerHTML` for a
  static block, and its own `innerBlocks`. The element that holds them is compared; what they
  draw is compared through their own fixtures.
- `needs` (optional): what the page needs to draw the block: `"post"`, a current post, or
  `"plugin:<folder>/<file>.php"`, a plug-in that must be active (the PHP test fails, naming it,
  when it is not). The editor needs neither.
- `pageOnly` (optional): classes of elements only the page draws (a trap field against robots);
  they are removed from the page's tree before the comparison.
- `tree`: the expected tree. Each element that carries a class of the block, with its `tag`, its
  block `classes` and its `children`; an element with no class of the block is transparent and
  its children take its place.

An element the editor needs and the page does not (a link field, a remove button) carries
`data-corex-editor-only` and no class of the block; it is skipped with what it holds.

Two tests find every `parity.json` under `plugins/` and `addons/` by search, so a new block needs
no new test: `tests/Integration/Blocks/ParityTest.php` draws it through WordPress
(`render_block`), and `tests/block-editor-parity.test.js` renders its `edit` with
`react-dom/server`. They cover the framework's blocks only. The helper they use is exported as
`@corex/build-tools/parity`, with one shared set of stand-ins for the `@wordpress/*` packages,
so an app or client package tests its own blocks the same way. What a client needs:

- `installStandIns( jest )`, once in the test file, before anything else;
- `blockIn( folder )`, the block as the gate reads it;
- `gateFailures( block, waiting )`, the gate's rules for one block (an empty list when it passes;
  `waiting` is a list of blocks allowed to be `pending`, usually none);
- `editorParityProblem( folder )`, the editor's side of the comparison (an empty string when the
  editor draws the fixture's tree, or the block, both trees and the markup).

It also exports the pieces they are made of (`parityTree`, `fixtureProblems`, `renderEdit`,
`registeredBlock`, `editorScriptsOf`, `editorImportsOf`, `findFiles`). The page's side, in PHP, is
`tests/Support/ParityTree.php` in the framework; a client draws its block through WordPress
with it, or with a copy of it in its own tests.

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
