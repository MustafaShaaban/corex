# Corex Media

Optional image optimization for Corex (spec 048): WebP on upload + an optimized `<picture>` helper.

- **WebP on upload** — a JPEG/PNG upload gets a sibling `.webp` (the original is preserved) when the server
  supports GD/Imagick + WebP. With no support, the upload succeeds unchanged (Principle IX).
- **`MediaImage` helper** — `$media->render($attachmentId, 'large', $isLcp)` emits an accessible `<picture>`:
  a WebP `<source>` + an `<img>` fallback, real `alt`, `loading="lazy"` + `decoding="async"`, and
  `fetchpriority="high"` + eager for the LCP/hero image; responsive `srcset`/`sizes`. No WebP → plain `<img>`.
- **Health probe** — reports GD/Imagick/WebP/AVIF support in Site Health + `wp corex doctor` (advisory).

The pure cores (`ImageCapability`, `ConversionPlan`, `PictureRenderer`, `MediaImageProbe`) are unit-tested;
the GD/Imagick conversion + WP attachment access are thin boundaries. **AVIF generation and CDN/Blob offload are
out of scope** (a later increment; the probe still reports AVIF support). Core never depends on this add-on.
