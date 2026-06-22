# Corex Media

Optional image optimization for Corex (spec 048; settings + regeneration spec 061): WebP on upload, a settings
panel, a regeneration command, and an optimized `<picture>` helper.

- **WebP on upload** — a JPEG/PNG upload gets a sibling `.webp` (the original is **always** preserved) when the
  server supports GD/Imagick + WebP. With no support, the upload succeeds unchanged (Principle IX).
- **Settings** (Corex → Settings → **Media**) — toggle WebP conversion, set the quality (1–100), and toggle
  JPEG/PNG conversion. The panel shows a live **server-support** read-out (GD / Imagick / WebP encode / uploads
  writable). The section is hidden/disabled until the add-on is installed/active; originals are always kept (not a
  setting). Filter seams: `corex_media_webp_enabled`, `corex_media_webp_quality`, `corex_media_convert_jpeg`,
  `corex_media_convert_png`, `corex_media_convertible_mimes`.
- **Regeneration** — `wp corex media regenerate-webp [--dry-run] [--limit=<n>] [--attachment=<id>]` backfills WebP
  siblings for existing uploads. It never deletes/overwrites originals, skips attachments that already have a
  sibling, respects the current settings, and reports convert/skipped/converted/failed counts. Run on
  local/staging first and back up before large runs; use `--dry-run` to preview and `--limit` to batch.
- **`MediaImage` helper** — `$media->render($attachmentId, 'large', $isLcp)` emits an accessible `<picture>`:
  a WebP `<source>` + an `<img>` fallback, real `alt`, `loading="lazy"` + `decoding="async"`, and
  `fetchpriority="high"` + eager for the LCP/hero image; responsive `srcset`/`sizes`. No WebP → plain `<img>`.
  For raw URLs (block attributes), `$media->pictureForUrl($url, $alt, $lcp)` does the same; any block can opt in
  without depending on this add-on via `apply_filters('corex_media_optimize_image', $fallbackImgHtml, ['url'=>…, 'alt'=>…])`.
- **Why `.webp` siblings aren't separate Media Library items** — the WebP is written next to the original on disk,
  not registered as its own WordPress attachment, so it won't appear as a distinct library item. Delivery happens
  through the `<picture>` helper / the optimize filter, which serve WebP to supporting browsers and the original
  everywhere else; a plain inserted `<img>` keeps using the original unless rendered through the helper.
- **Health probe** — reports GD/Imagick/WebP/AVIF support in Site Health + `wp corex doctor` (advisory).

The pure cores (`ImageCapability`, `ConversionPlan`, `PictureRenderer`, `MediaImageProbe`) are unit-tested;
the GD/Imagick conversion + WP attachment access are thin boundaries. **AVIF generation and CDN/Blob offload are
out of scope** (a later increment; the probe still reports AVIF support). Core never depends on this add-on.
