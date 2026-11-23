---
title: Create a block (CLI)
description: Scaffold a complete, registered, working dynamic block with one command.
---

Every Corex block is **dynamic**: its markup is produced server-side by a PHP renderer. How
the editor draws it follows one rule (DECISIONS #656):

- **A static block is edited in place.** Its editor draws the same elements as the page, and
  its text is typed on the canvas. Its `parity.json` proves it: two tests draw the block in
  the editor and on the page and compare both trees with it.
- **A block that shows records keeps a server render.** A list of posts, a trail or a form
  previews its server render with `<ServerSideRender>`, inside the block's wrapper and inert.

Every `block.json` says which, in `corex.editor`: `in-place`, `records`, `none` (a block with no
editor, such as the assistant) or `pending` (a framework block whose turn has not come; only the
blocks on the gate's waiting list may say so). For the framework's own blocks, a test fails a
block that breaks its rule. One command scaffolds either of the first two shapes.

## Scaffold

```bash
wp corex make:block TeamMember --path=wp             # edited in place
wp corex make:block LatestNews --records --path=wp   # shows records
```

This writes, under your app's `Blocks/` directory:

```
Blocks/
  team-member/
    block.json     # apiVersion 3, category "corex", editorScript + style wired,
                   #   a title attribute, the anchor and a top and bottom
                   #   margin supported,
                   #   corex.renderer → App\Blocks\TeamMemberRenderer,
                   #   corex.editor → in-place (records with --records)
    index.js       # registerBlockType; the title typed on the canvas with RichText
                   #   (--records: <ServerSideRender> inside <Disabled>)
    parity.json    # the tree the editor and the page both draw (not with --records)
    style.scss     # theme.json tokens and logical properties only
  TeamMemberRenderer.php   # implements Corex\Blocks\BlockRenderer
```

The slug (`team-member`), title, block name (`<prefix>/team-member`), CSS class, and
renderer FQCN are all derived from the name.

## Build

```bash
npm run build       # compile index.js + style.scss → build/blocks/
# or: npm run start (watch mode)
```

After building, the block registers with an editor script and renders server-side. The
PHP renderer is where you produce escaped markup. It prints one root element; what an editor
sets on the block (its anchor, an extra class) is added to that root for you:

```php
final class TeamMemberRenderer implements BlockRenderer
{
    public function render(array $attributes, string $content, object $block): string
    {
        $title = trim((string) ($attributes['title'] ?? ''));

        if ($title === '') {
            return '';
        }

        return sprintf(
            '<div class="%1$s"><p class="%1$s__title">%2$s</p></div>',
            esc_attr('corex-team-member'),
            wp_kses_post($title)
        );
    }
}
```

When you add an element to the renderer, draw it in `index.js` too and add it to
`parity.json`. The framework's parity tests cover the framework's blocks only (they search
`plugins/` and `addons/`). A block `make:block` writes into your app or client plugin is checked
by a test of your own that calls `@corex/build-tools/parity`:

```js
const {
	blockIn,
	editorParityProblem,
	gateFailures,
	installStandIns,
} = require( '@corex/build-tools/parity' );

installStandIns( jest );

test( 'the team member block meets the editor standard', () => {
	const folder = `${ __dirname }/../src/Blocks/team-member`;

	expect( gateFailures( blockIn( folder ) ) ).toEqual( [] );
	expect( editorParityProblem( folder ) ).toBe( '' );
} );
```

## Why a build is required

A dynamic block still needs editor-side `registerBlockType()` or WordPress shows
*"Your site doesn't include support for this block."* The `index.js` provides it; the
build compiles it (and an auto-generated RTL stylesheet). See
[Troubleshooting](/troubleshooting/) if the editor still complains.

## The built-in `corex/*` library

The `corex-ui` add-on ships a set of ready-made dynamic blocks under the **Corex** inserter
category — all server-rendered, token-only, and RTL-correct:

| Block | Renders |
|---|---|
| `corex/posts` | Recent posts as accessible linked cards (bounded count) |
| `corex/breadcrumbs` | An accessible breadcrumb trail |
| `corex/copyright` | The current year + site name |
| `corex/stat` | A single statistic — value, label, optional description |
| `corex/testimonial` | A quote with attribution (`<figure>`/`<blockquote>`/`<figcaption>`) |
| `corex/pricing` | A pricing card — plan, price, features, optional CTA |
| `corex/accordion` | Accessible disclosures from a list — native `<details>`, no JavaScript |
| `corex/hero` | A page hero — eyebrow, heading, subheadline, button, optional background image |
| `corex/cta` | A call-to-action banner — heading, supporting line, button |
| `corex/team` | A responsive grid of team members — photo, name, role, bio |
| `corex/gallery` | A responsive image gallery from the media library, with captions |
| `corex/tabs` | Tabbed content with **no front-end JavaScript** (a CSS-only accessible tabs pattern) |

Together these are enough to build a full landing page — **hero → stats → team → gallery → cta** —
entirely from Corex blocks, with no theme code. (A "stats grid" is just several `corex/stat` blocks
inside a columns/grid container.)

## Inline editing (modern, in-canvas)

The component blocks (`stat`/`testimonial`/`pricing`/`accordion`/`hero`/`cta`/`team`/`gallery`/
`tabs`) are edited **inline on the canvas** — you type the heading, quote, price, or panel text
directly into the block like a modern page builder (not only in the right sidebar). They stay
**dynamic**: the text lives in block attributes, `save` returns `null`, and the PHP renderer
produces the markup from those attributes (so the editor and the front end share one source of
truth). Rich text (bold, italic, links) is preserved safely with `wp_kses_post`. Repeatable lists
— pricing features, accordion panels, team members, gallery images, tabs — are added/removed as
inline rows. Image blocks (`hero`/`team`/`gallery`) pick from the **media library** (no pasted
URLs), storing `{id, url, alt}` and rendering real `<img>` with alt text + lazy loading. `tabs`
renders an accessible tabbed widget with **zero view JavaScript** (a CSS-only radio/label pattern),
so it works even with scripts disabled.

The **form** block lets you **pick a form from a dropdown** of the registered forms (no more
typing a slug); the list comes from the cap-gated `corex/v1/forms` route.

Each block is a pure `BlockRenderer` — see its generated page in the **Reference**.
