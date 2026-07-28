---
title: User guides
description: Ship in-admin user guides with your site, using the same registry Corex uses for its own.
---

Corex Guides puts a **Guides** screen in the admin and a Help tab on the screens your guides
describe. Corex registers its own guides through it — reading and answering messages, publishing a
post, checking whether an email was sent — and your site registers its own through exactly the same
public API.

This matters because a client's guide is about the client's site. It documents their post types and
their flows, it ships with their plugin, and it must survive a Corex upgrade without anybody editing
Corex.

## Scaffold one

```bash
wp corex make:guide Projects
```

That writes `Guides/ProjectsGuide.php` in your plugin, with a topic, two steps, and a warning — the
parts authors skip when starting from an empty file.

## Register it

From your service provider's `boot()`:

```php
use Corex\Guides\GuideRegistry;
use Corex\Support\Facades\Corex;

Corex::onReady( static function (): void {
    Corex::make( GuideRegistry::class )->registerDeferred(
        static fn (): array => [ ( new ProjectsGuide() )->guide() ]
    );
} );
```

:::danger[Wrap it in `Corex::onReady()`]
Calling `Corex::make()` directly from `plugins_loaded` is a coin flip that ends in a **white screen
on the whole site**. Corex boots on `plugins_loaded` at priority 10, and the generated site starter
boots there too — which one WordPress runs first depends on your plugin's *directory name*. The
plugin that loses reaches `Boot::app()`, which **throws**.

Nor can you guard it yourself: `Boot::app()` throws rather than returning null, so
`if ( Boot::app() === null )` — the check you would naturally write — *is* the crash. Use
`Corex::onReady()`, which runs immediately if Corex is up and waits for the `corex_booted` action if
it is not. There is no ordering left to get wrong.
:::

### Use `registerDeferred()`, not `register()`

Both exist, and only one is safe from a plugin.

Corex boots on `plugins_loaded` at priority 10. Your plugin very likely boots on `plugins_loaded` at
priority 10 too — the generated starter does. Which of you WordPress loads first depends on your
plugin's directory name, so `register()` works or silently does nothing depending on what you called
your folder.

`registerDeferred()` takes a factory and runs it the first time anything **reads** the registry,
which is an admin request — long after every plugin has loaded. There is no race left to lose.

:::caution[Keep the factory cheap]
It runs on **every admin page load**, not just on the Guides screen — Corex reads the registry on
`current_screen` to work out whether the page has a help tab. Building `Guide` objects costs
nothing, so that is free. A factory that queries the database or reads a file would put that cost on
every page in wp-admin. If your guide content really has to come from storage, cache it yourself:
the registry does not, because it cannot know how long your data stays fresh.
:::

## Write the guide

```php
use Corex\Guides\Guide;
use Corex\Guides\GuideStep;
use Corex\Guides\GuideTopic;

Guide::for( 'projects', __( 'Managing projects', 'perego' ) )
    ->withSummary( __( 'Add a project, choose where it appears, and publish it.', 'perego' ) )
    ->inSection( 'content' )
    ->onScreen( 'edit.php?post_type=perego_project' )
    ->requiring( 'edit_posts' )
    ->withTopic( GuideTopic::for(
        'add',
        __( 'Add a project', 'perego' ),
        '',
        [
            new GuideStep(
                __( 'Choose Projects, then Add Project.', 'perego' ),
                __( 'The editor opens with an empty project.', 'perego' ),
            ),
            new GuideStep(
                __( 'Choose Publish.', 'perego' ),
                __( 'The project appears on the Projects page.', 'perego' ),
                warning: __( 'Publishing makes it public immediately.', 'perego' ),
            ),
        ],
    ) );
```

### What each part does

| Method | Effect |
| --- | --- |
| `inSection()` | Groups the guide on the Guides screen. Sections appear in **key order**, alphabetically — that is the only lever you have over where your section lands. |
| `onScreen()` | The admin address the guide describes. Also puts the guide in that screen's **Help tab**. Use the address WordPress itself uses: `edit.php` for posts, `edit.php?post_type=…` for anything else. |
| `requiring()` | The capability a reader needs. Anyone without it never sees the guide — they could not follow it. Omit it and the guide is shown to anyone who can open the admin. |
| `ordered()` | Position within the section. Lower first; the default is 50. |

### Steps

A step is an **instruction** and an **expected result**, not a paragraph. The pair is what makes a
step checkable: somebody does the first and can tell from the second whether it worked. A guide
written as prose reads fine and leaves people stuck with no way to know they are stuck.

Add `warning:` to anything hard to undo. It renders *before* the instruction — a caution somebody
meets after the fact is decoration.

## Replace a Corex guide

Guides are keyed by id, so registering your own under a Corex id replaces it:

```php
Guide::for( 'corex-publishing', __( 'How we publish here', 'perego' ) )
```

## Without a container

If you cannot reach the container, use the filter. Anything returned that is not a `Guide` is
discarded rather than rendered — this screen is what somebody opens when they are already stuck.

```php
add_filter( 'corex_guides', static function ( array $guides ): array {
    $guides[] = ( new ProjectsGuide() )->guide();

    return $guides;
} );
```

## Screenshots

A step can carry one:

```php
use Corex\Guides\GuideScreenshot;

new GuideStep(
    __( 'Choose Projects.', 'perego' ),
    __( 'The list opens.', 'perego' ),
    screenshot: new GuideScreenshot( 'projects-list', __( 'The Projects list.', 'perego' ) ),
);
```

The `alt` text is required, not optional — a screenshot with no text alternative is a step a
screen-reader user simply does not get.

The capture id links the image to the script that produces it in both directions: the script fails
loudly for an id it was asked for and could not capture, and a reviewer can find the code behind any
image in the tree. Corex captures its own with
`node tests/e2e/capture-guide-screenshots.mjs`; add your ids to your own equivalent so your images
cannot quietly stop matching your product.

## Guides for inactive features

There is nothing to do. Register your guides from your own service provider, and a deactivated
plugin registers nothing — so its guides are simply absent. No `is_active()` check, and no list of
plugin slugs to keep in step with reality.
