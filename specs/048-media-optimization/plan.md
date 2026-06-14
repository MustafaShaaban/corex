# Implementation Plan: Media & image optimization

**Branch**: `feature/048-media-optimization` | **Date**: 2026-06-14 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/048-media-optimization/spec.md`

## Summary

A new **optional `corex-media` add-on** (`Corex\Media\`, gated + self-disabling — Principle IX) that converts
uploaded JPEG/PNG to **WebP** (preserving the original) and renders optimized, accessible `<picture>` markup
(WebP source + fallback, lazy/async, `fetchpriority` for the LCP image, responsive srcset). The judgement lives in
**pure cores** — `ImageCapability` (GD/Imagick/WebP/AVIF), `ConversionPlan` (convert? to what? output path), and
`PictureRenderer` (the escaped `<picture>` markup) — wrapped by thin boundaries (a `WebpConverter` on the WP upload
hook, a `MediaImageProbe` in the spec-036 health seam, and the helper that resolves an attachment). Core never
depends on the add-on; everything degrades gracefully (no support → original kept, helper → plain `<img>`). AVIF
generation + CDN are out of scope.

## Technical Context

**Language/Version**: PHP 8.3. Capability/plan/markup cores are pure; GD/Imagick + WP upload/attachment hooks are a
thin boundary.

**Primary Dependencies**: existing only — the add-on pattern (gated/self-disabling), spec-036 health-probe seam,
spec-040 URL normalisation, WordPress's own image sizes/upload hooks. No new dependency.

**Storage**: none new — WebP files sit beside the originals in uploads; no DB change beyond WP's attachment meta.

**Testing**: Pest — `ImageCapability` (value object), `ConversionPlan` (decide/skip/output), `PictureRenderer`
(escaped markup, lazy vs LCP, webp-or-fallback, srcset) with stubbed `esc_*`. Live GD/Imagick conversion env-gated.

**Target Platform**: WordPress media (upload) + front-end render.

**Project Type**: WordPress framework monorepo — new `addons/corex-media`.

**Performance Goals**: conversion is capability-gated + bounded (degrades to the original on failure); the render
helper is markup assembly; no per-request heavy work.

**Constraints**: escaped URLs/attrs (FR-008); accessible markup (alt, valid `<picture>`); no secret; optional +
graceful (FR-002/FR-010); pure cores (Principle III).

**Scale/Scope**: 1 add-on (provider, gated) + 3 pure cores + 2 thin boundaries (converter, probe) + a render helper.

## Constitution Check

*GATE: pass before Phase 0; re-check after Phase 1.* (Corex Constitution v1.2.1.)

- [x] **I. Theme is a skin** — N/A/PASS. The theme may call the render helper; no business logic added to it.
- [x] **II. Plugins boot themselves** — PASS. The add-on self-inits, gated; works without a theme.
- [x] **III. Thin controllers, fat services** — PASS. Capability/plan/markup are **pure**; converter/probe/helper
  are thin boundaries.
- [x] **IV. Everything injected** — PASS. The add-on's services are container-wired; the cores are value objects.
- [x] **V. Runtime tokens** — N/A (images, not design tokens).
- [x] **VI. Conditional assets** — N/A (no CSS/JS asset added).
- [x] **VII. Declarative security** — PASS. Emitted markup is **escaped** (esc_url/esc_attr); no secret; the
  converter touches only the uploaded file (no arbitrary path).
- [x] **VIII. RTL-first** — N/A (image markup is direction-agnostic; alt text is i18n where applicable).
- [x] **IX. No optional dep is hard** — PASS. `corex-media` is an **optional add-on**; absent → upload unchanged +
  helper degrades to `<img>`; core never depends on it; GD/Imagick are detected, never required.
- [x] **X. Spec is source of truth** — PASS. Traces to spec 048; reuses 036/040 + the add-on pattern.
- [x] **Guard Gate + DoD** — clean-code (pure cores), wp-guard (escaped markup, upload-hook safety, no secret),
  woo-guard N/A, test-guard (Pest), docs-guard (a media guide); WCAG (alt); PROGRESS/DECISIONS; NEXT STEP.

**Result: PASS — no violations.**

## Project Structure

```text
addons/corex-media/
├── corex-media.php                 # NEW — plugin header (optional add-on)
└── src/
    ├── ImageCapability.php         # NEW — pure value object (gd/imagick/webp/avif) + static detect() boundary
    ├── ConversionPlan.php          # NEW — pure: (path, mime, capability) → convert?/format/outputPath; preserves original
    ├── PictureRenderer.php         # NEW — pure: image data → escaped <picture> (webp+fallback, lazy/async, LCP, srcset)
    ├── WebpConverter.php           # NEW — boundary: GD/Imagick conversion on the upload hook (fail-safe)
    ├── MediaImageProbe.php         # NEW — boundary: GD/Imagick/WebP/AVIF support → spec-036 health report (advisory)
    ├── MediaImage.php              # NEW — helper: attachment id → PictureRenderer data → markup
    └── MediaServiceProvider.php    # NEW — gated registration (hooks the converter + probe + binds the helper)

addons/corex-media/README.md · docs-app/.../guides/media.md   # NEW — the add-on + helper + capabilities
tests/Unit/Media/ (Pest)                                       # NEW — ImageCapability, ConversionPlan, PictureRenderer
```

**Structure Decision**: A new **optional add-on** mirroring the existing gated add-ons (e.g. `corex-captcha`).
The pure cores (capability/plan/markup) are unit-tested headlessly; the GD/Imagick conversion + the WP upload hook
+ the health probe are thin boundaries. The probe folds into the spec-036 health seam; URLs use the spec-040
normalisation. Core has **no** reference to `corex-media`.

## Complexity Tracking

> No Constitution Check violations — section intentionally empty.
