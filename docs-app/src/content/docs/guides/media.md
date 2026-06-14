---
title: Image optimization
description: Convert uploads to WebP and render optimized, accessible <picture> markup.
---

The optional **Corex Media** add-on converts uploads to WebP and renders optimized images — without you
hand-writing `<img>` tags. The framework runs fully without it; it degrades gracefully everywhere.

## WebP on upload

When the server supports GD/Imagick + WebP, uploading a JPEG/PNG generates a sibling `.webp` and **preserves the
original**. With no support, the upload succeeds unchanged — no error. SVG follows your site's existing policy.

## The picture helper

```php
echo $media->render( $attachmentId, 'large' );          // lazy, async, webp + fallback
echo $media->render( $heroId, 'full', true );           // LCP: fetchpriority=high, eager
```

It emits an accessible `<picture>`: a WebP `<source>` + an `<img>` fallback with the real `alt`,
`loading="lazy"` + `decoding="async"` by default, a responsive `srcset`/`sizes`, and — for a designated **LCP/hero**
image — `fetchpriority="high"` and eager (not lazy). With no WebP sibling it degrades to a plain optimized `<img>`.

## Diagnostics

Site Health and `wp corex doctor` report GD/Imagick/WebP/AVIF support as advisory status — so a missing capability
is visible, never a hard failure.

## Out of scope

AVIF **generation** and CDN/Blob offload are a later increment (the probe still reports AVIF support). This feature
augments WordPress's own image handling — it does not replace it.
