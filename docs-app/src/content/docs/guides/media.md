---
title: Image optimization
description: Convert uploads to WebP and render optimized, accessible <picture> markup.
---

The optional **Corex Media** add-on converts uploads to WebP and renders optimized images — without you
hand-writing `<img>` tags. The framework runs fully without it; it degrades gracefully everywhere.

## WebP on upload

When the server supports GD/Imagick + WebP, uploading a JPEG/PNG generates a sibling `.webp` and **preserves the
original**. With no support, the upload succeeds unchanged — no error. SVG follows your site's existing policy.

> The `.webp` sibling is written next to the original on disk, **not** registered as its own Media Library item —
> so it won't show up as a separate attachment. Delivery happens through the `<picture>` helper / optimize filter
> below; a plain inserted `<img>` keeps using the original unless it's rendered through the helper.

## Settings

Under **Corex → Settings → Media** you can toggle WebP conversion, set the quality (1–100, default 82), and toggle
JPEG/PNG conversion independently. The panel shows a live **server-support** read-out — GD, Imagick, WebP encode,
and uploads-directory writability (also in Tools → Site Health). The section is hidden until the add-on is
installed and disabled until it's active. **Originals are always preserved** — that is not a setting.

Filter seams (for code-level control): `corex_media_webp_enabled`, `corex_media_webp_quality`,
`corex_media_convert_jpeg`, `corex_media_convert_png`, `corex_media_convertible_mimes`.

## The WebP activation gate

A `.webp` is **not served just because it exists**. After conversion, CoreX measures the derivative and serves it
only when it passes a gate; otherwise the original is delivered. The default gate:

- the derivative exists and is a valid image;
- its dimensions match the original;
- it is smaller than the original by at least the **minimum saving threshold** (default **5%**, set under
  Settings → Media or via the `corex_media_min_saving` filter).

The result is tracked per attachment (`_corex_webp` meta): original/generated paths + bytes, saving %, dimensions,
quality, source hash, generated-at, and `active_for_delivery` + `inactive_reason`. So a WebP that came out *larger*
(common for already-optimized PNGs) is generated but quietly **not served** — you always get the smaller file.

## Regenerating existing uploads

To backfill WebP siblings for images uploaded before conversion was on:

```bash
wp corex media regenerate-webp --dry-run            # preview what would convert
wp corex media regenerate-webp --limit=50           # convert in batches
wp corex media regenerate-webp --attachment=123     # a single attachment
```

It **never deletes or overwrites originals**, skips attachments that already have a `.webp` sibling, skips
unsupported types, respects the current settings, and reports convert/skipped/converted/failed counts. Run it on
**local/staging first** and **back up** before large runs.

## Resetting / cleaning up

To remove CoreX-generated WebP derivatives safely:

```bash
wp corex media reset-webp --dry-run --all     # preview
wp corex media reset-webp --all                # all tracked derivatives
wp corex media reset-webp --attachment=123     # a single attachment
```

It deletes **only** files tracked in a `_corex_webp` record — never originals, never manually-uploaded WebP, never
any untracked file — clears the record afterwards, and reports scanned/deleted/skipped/failed counts. Deleting a
WordPress attachment also removes just its tracked CoreX derivative automatically.

> Generated WebP files are **not** separate Media Library attachments — the library shows one original per upload.
> WebP status/actions surface through Settings → Media (a full attachment-detail UI is future).

## The picture helper

```php
echo $media->render( $attachmentId, 'large' );          // lazy, async, webp + fallback
echo $media->render( $heroId, 'full', true );           // LCP: fetchpriority=high, eager
```

It emits an accessible `<picture>`: a WebP `<source>` + an `<img>` fallback with the real `alt`,
`loading="lazy"` + `decoding="async"` by default, a responsive `srcset`/`sizes`, and — for a designated **LCP/hero**
image — `fetchpriority="high"` and eager (not lazy). With no WebP sibling it degrades to a plain optimized `<img>`.

For a raw image **URL** (e.g. a block attribute), use `$media->pictureForUrl( $url, $alt, $lcp )`. Any block can
opt into optimized output **without depending on the add-on** through the filter seam:

```php
$img = sprintf( '<img src="%s" alt="%s" loading="lazy" />', esc_url( $url ), esc_attr( $alt ) );
echo apply_filters( 'corex_media_optimize_image', $img, [ 'url' => $url, 'alt' => $alt ] );
```

When Corex Media is active and a WebP sibling exists, the filter returns the `<picture>`; otherwise it returns your
fallback `<img>` unchanged. (Retrofitting the built-in CoreX UI image blocks to use this seam — preserving each
block's classes/loading — is a tracked follow-up.)

## Diagnostics

Site Health and `wp corex doctor` report GD/Imagick/WebP/AVIF support as advisory status — so a missing capability
is visible, never a hard failure.

## Out of scope

AVIF **generation** and CDN/Blob offload are a later increment (the probe still reports AVIF support). This feature
augments WordPress's own image handling — it does not replace it.
