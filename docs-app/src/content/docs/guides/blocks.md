---
title: Create a block (CLI)
description: Scaffold a complete, registered, working dynamic block with one command.
---

Every Corex block is **dynamic** — its markup is produced server-side by a PHP renderer,
and the editor previews that render with `<ServerSideRender>`. One command scaffolds the
whole thing.

## Scaffold

```bash
wp corex make:block TeamMember --path=wp
```

This writes, under your app's `Blocks/` directory:

```
Blocks/
  team-member/
    block.json     # apiVersion 3, category "corex", editorScript + style wired,
                   #   corex.renderer → App\Blocks\TeamMemberRenderer
    index.js       # registerBlockType + <ServerSideRender>; imports style.scss
    style.scss     # token-only, RTL-correct
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
PHP renderer is where you produce escaped markup:

```php
final class TeamMemberRenderer implements BlockRenderer
{
    public function render(array $attributes, string $content, object $block): string
    {
        return sprintf('<div class="%s">%s</div>',
            esc_attr('corex-team-member'),
            esc_html__('Team Member', 'corex'));
    }
}
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

## Inline editing (modern, in-canvas)

The component blocks (`stat`/`testimonial`/`pricing`/`accordion`) are edited **inline on the
canvas** — you type the heading, quote, price, or panel text directly into the block like a
modern page builder (not only in the right sidebar). They stay **dynamic**: the text lives in
block attributes, `save` returns `null`, and the PHP renderer produces the markup from those
attributes (so the editor and the front end share one source of truth). Rich text (bold,
italic, links) is preserved safely with `wp_kses_post`. Repeatable lists — pricing features,
accordion panels — are added/removed as inline rows.

The **form** block lets you **pick a form from a dropdown** of the registered forms (no more
typing a slug); the list comes from the cap-gated `corex/v1/forms` route.

Each block is a pure `BlockRenderer` — see its generated page in the **Reference**.
