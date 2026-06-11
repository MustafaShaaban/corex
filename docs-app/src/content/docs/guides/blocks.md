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
