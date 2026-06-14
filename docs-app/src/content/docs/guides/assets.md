---
title: Assets & cache-busting
description: Resolve asset URLs, paths, and versions through one helper — with per-environment cache-busting.
---

Corex resolves an asset's URL, filesystem path, and cache-busting version through one helper, so you never
hand-build a `plugins_url()` call or guess a version string.

## The helpers

Inject `Corex\Assets\AssetManager` and ask it:

```php
$assets->url('images/logo.svg');     // the public URL
$assets->path('images/logo.svg');    // the absolute filesystem path
$assets->version('build/app.css');   // a cache-busting version token
```

Use them with `wp_enqueue_*`:

```php
wp_enqueue_style('app', $assets->url('build/app.css'), [], $assets->version('build/app.css'));
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
