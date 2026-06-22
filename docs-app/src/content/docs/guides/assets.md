---
title: Assets & cache-busting
description: Resolve asset URLs, paths, and versions through one helper — with per-environment cache-busting.
---

Corex resolves an asset's URL, filesystem path, and cache-busting version through one helper, so you never
hand-build a `plugins_url()` call or guess a version string.

## `Corex\Assets\*` vs `Corex\Media\*`

Two clearly separated concerns — use the right one:

- **`Corex\Assets\*`** — your **source-controlled** theme/plugin/client assets: SCSS→CSS, JS, images, fonts under
  `assets/`. This is the approved way to resolve, version, enqueue, and render them.
- **`Corex\Media\*`** — WordPress **Media Library** uploads (the WebP-on-upload pipeline). See
  [Image optimization](/guides/media/).

## The enqueue/render facades

The `Style` / `Script` / `Image` / `Picture` facades resolve the URL + cache-busting version for you and call the
right WordPress function — no hand-built `plugins_url()` or version strings:

```php
use Corex\Assets\{Style, Script, Image};

Style::enqueue('client-app', 'css/app.css');
Script::enqueue('client-app', 'js/app.js', ['defer' => true, 'in_footer' => true]);
Script::enqueueModule('client-module', 'js/module.js');     // <script type="module">

echo Image::picture('images/hero.jpg', ['alt' => 'Hero image', 'class' => 'hero__image']);
```

- **Scripts** support `deps`, `in_footer`, `defer`/`async`, `module`, and `version`, and automatically merge a
  sibling wp-scripts `*.asset.php` (its `dependencies` + content-hash `version`).
- **`Image::picture()`** emits a `<picture>` with a WebP `<source>` when a **built** `.webp` sibling exists next to
  the source, else a plain `<img>` (source-controlled images — Media Library uploads use `Corex\Media`).

### SCSS is source only

```php
Style::enqueue('app', 'scss/app.scss');   // ✗ refused — reported via _doing_it_wrong, nothing enqueued
Style::enqueue('app', 'css/app.css');     // ✓ the compiled CSS
```

Passing a `.scss`/`.sass` path never enqueues it — compile it to CSS first and enqueue the compiled file from
`assets/css/`.

### Registering a base (theme / plugin / client)

The facades resolve assets through a named **base**. The framework registers itself as `corex` (the default); a
theme/plugin/client registers its own base once, then enqueues against it:

```php
add_action('wp_enqueue_scripts', function () {
    \Corex\Assets\Assets::registerBase(
        'acme',
        get_stylesheet_directory() . '/assets',
        get_stylesheet_directory_uri() . '/assets',
        wp_get_theme()->get('Version'),
    );
    \Corex\Assets\Style::enqueue('acme-app', 'css/app.css', ['base' => 'acme']);
    \Corex\Assets\Script::enqueue('acme-app', 'js/app.js', ['base' => 'acme', 'in_footer' => true]);
});
```

## Low-level resolution

The facades sit on `Corex\Assets\AssetManager`, which you can inject and ask directly:

```php
$assets->url('images/logo.svg');     // the public URL
$assets->path('images/logo.svg');    // the absolute filesystem path
$assets->version('build/app.css');   // a cache-busting version token
```

A relative path is confined to its base — a traversal (`../`) never resolves outside it.

## Per-environment cache-busting

The `version()` token follows the environment (resolved from Corex config, falling back to
`wp_get_environment_type()`, defaulting **production-safe**):

| Environment | Version strategy |
|---|---|
| **local / development** | `filemtime` — busts on every edit |
| **staging / production** | the build **manifest** content hash when present, else the framework/site version (busts on a release) |

So a release never serves stale CSS/JS, and a local edit is always reflected. Public **source maps** are exposed
only in local — never in staging/production by default.

## Build manifest

When your build emits a manifest (`build/manifest.json`) mapping a source name to its hashed output
(`{ "build/app.css": { "file": "build/app.4f3a.css", "hash": "4f3a" } }`), `url()` resolves to the hashed file and
`version()` to its hash. With no manifest, it falls back to the plain file + filemtime/version — never an error.

## Diagnostics

```bash
wp corex assets:doctor   # environment, manifest present?, sample resolutions, source-map exposure
wp corex cache:clear     # clear Corex's cached asset/version state
```

## See also

- [The response contract & runtime](/guides/frontend-runtime/) — the buildless front-end runtime.
- [REST resources](/guides/rest/) — scaffolding code that consumes these assets.
