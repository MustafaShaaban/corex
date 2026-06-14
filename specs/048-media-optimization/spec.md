# Feature Specification: Media & image optimization

**Feature Branch**: `feature/048-media-optimization`

**Created**: 2026-06-14

**Status**: Draft (forward spec — precedes code; full Spec Kit flow)

**Input**: User description: "A real media performance plan — convert uploads to WebP (preserve originals), render
optimized `<picture>` tags with lazy loading + async decoding + fetchpriority for the LCP image, detect missing
GD/Imagick/WebP support and fall back gracefully, and a Site Health / doctor probe for image support. Optional
`corex-media` add-on (Principle IX). AVIF/CDN are a later increment."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Uploads become WebP without losing the original (Priority: P1) 🎯 MVP

A site owner uploads a JPEG/PNG and the framework generates a **WebP** copy alongside it (the original is
preserved), when the server supports it — so pages can serve a smaller modern format. If the server lacks
WebP/GD/Imagick support, the upload still succeeds unchanged (graceful degradation).

**Why this priority**: WebP is the highest-leverage media win; doing it on upload (with the original kept) is the
foundation the render helper builds on.

**Independent Test**: With image support present, uploading an image produces a WebP sibling and keeps the
original; with support absent, the upload succeeds with no WebP and no error.

**Acceptance Scenarios**:

1. **Given** a supported server, **When** a JPEG/PNG is uploaded, **Then** a WebP copy is generated and the
   original file is preserved.
2. **Given** an unsupported server (no GD/Imagick/WebP), **When** an image is uploaded, **Then** the upload
   succeeds unchanged — no WebP, no error (Principle IX).
3. **Given** a non-image upload (PDF, SVG), **When** uploaded, **Then** no conversion is attempted; SVG follows
   the site's existing SVG policy (not enabled by this feature).

---

### User Story 2 - An optimized image helper (Priority: P1)

A developer renders an image through a helper that emits an optimized, accessible `<picture>` — the WebP source
with the original as fallback, the real `alt`, `loading="lazy"` and `decoding="async"` by default, and
`fetchpriority="high"` for a designated **LCP/hero** image — instead of hand-writing `<img>` tags.

**Why this priority**: The render side is where the performance is realized; a helper makes "do images right" the
default.

**Independent Test**: Render an image id; the output is a `<picture>` with a WebP `<source>` + an `<img>` fallback
carrying alt, lazy, async decode; rendering it as the LCP image sets `fetchpriority="high"` and not lazy.

**Acceptance Scenarios**:

1. **Given** an image with a WebP variant, **When** rendered, **Then** the output is a `<picture>` with a WebP
   `<source>` and an `<img>` fallback (the original format), with the real `alt`.
2. **Given** any rendered image, **When** emitted, **Then** `loading="lazy"` and `decoding="async"` are set by
   default.
3. **Given** an image marked as the **LCP/hero**, **When** rendered, **Then** `fetchpriority="high"` is set and it
   is **not** lazy-loaded.
4. **Given** an image with no WebP variant, **When** rendered, **Then** it degrades to a plain optimized `<img>`
   (lazy/async), never a broken `<picture>`.
5. **Given** a missing/empty alt, **When** rendered, **Then** the markup is still valid + accessible (empty alt
   for decorative, never a crash).

---

### User Story 3 - Responsive sizes (Priority: P2)

A rendered image carries a **srcset** of responsive widths (reusing WordPress's generated sizes) + a `sizes`
hint, so the browser picks an appropriately-sized file — built into the same helper.

**Why this priority**: Responsive sizing compounds the WebP win; P2 because the WebP `<picture>` (US1/US2) already
delivers the core improvement.

**Independent Test**: Render an image with multiple sizes; the `<img>`/`<source>` carries a `srcset` of widths +
a `sizes` attribute; an image with one size degrades to a single src.

**Acceptance Scenarios**:

1. **Given** an image with multiple generated sizes, **When** rendered, **Then** a `srcset` of widths + a `sizes`
   hint are emitted (WebP sources where available).
2. **Given** an image with a single size, **When** rendered, **Then** it degrades to a single source, no broken
   srcset.

---

### User Story 4 - Diagnose image support (Priority: P2)

A site owner sees, in **Site Health** and `wp corex doctor`, whether the server supports the image work — GD
and/or Imagick, WebP, AVIF — so a missing capability is visible rather than silently skipped.

**Why this priority**: Operability — knowing *why* WebP isn't being generated. P2 because the conversion already
degrades gracefully without it.

**Independent Test**: The probe reports GD/Imagick presence, WebP support, and AVIF support; on a server missing
WebP, it reports "recommended" with the reason, not a failure.

**Acceptance Scenarios**:

1. **Given** the image-support probe, **When** it runs, **Then** it reports GD/Imagick, WebP, and AVIF support
   with a plain-language status (good / recommended) and a next action.
2. **Given** the probe, **When** support is missing, **Then** it is **recommended** (advisory), never a hard
   failure — the site still works.

---

### Edge Cases

- An already-WebP (or already-optimized) upload → not re-converted.
- A corrupt/unreadable image → conversion skipped, upload still succeeds, logged (no fatal).
- A very large image → conversion bounded by the server's memory/time; failure degrades to the original.
- AVIF requested but unsupported → falls back to WebP/original (AVIF is best-effort/out of scope for v1).
- The add-on disabled/absent → no conversion, the helper degrades to a plain `<img>`; core never depends on it.
- An image hosted off-site (external URL) → rendered as-is (no local conversion).

## Requirements *(mandatory)*

### Functional Requirements

**Conversion (US1)**

- **FR-001**: On upload of a supported raster image (JPEG/PNG), the system MUST generate a **WebP** copy and
  **preserve the original**, when the server supports it.
- **FR-002**: When GD/Imagick/WebP support is absent, the upload MUST succeed unchanged — **no error** (Principle
  IX); a non-image or already-WebP upload MUST not be converted.
- **FR-003**: Conversion MUST be **capability-gated** (detect GD/Imagick + WebP) and **fail-safe** — a
  corrupt/oversized image degrades to the original, logged, never a fatal.

**Render helper (US2/US3)**

- **FR-004**: A media helper MUST render an image as an accessible `<picture>` — a WebP `<source>` + an `<img>`
  fallback (original format), with the real `alt`.
- **FR-005**: The helper MUST set `loading="lazy"` + `decoding="async"` by default, and `fetchpriority="high"` +
  **not lazy** for a designated LCP/hero image.
- **FR-006**: With no WebP variant, the helper MUST degrade to a plain optimized `<img>` (lazy/async); a
  missing/empty alt MUST still render valid, accessible markup.
- **FR-007**: The helper MUST emit a responsive **srcset** of widths + a `sizes` hint when multiple sizes exist,
  degrading to a single source otherwise.
- **FR-008**: All emitted markup MUST be escaped (URLs/attrs) and translation-ready where text applies.

**Diagnostics (US4)**

- **FR-009**: An image-support probe MUST report GD/Imagick, WebP, and AVIF support in **Site Health** + `wp corex
  doctor` as good/recommended (advisory, never a hard failure).

**Packaging (cross-cutting)**

- **FR-010**: This MUST ship as an **optional `corex-media` add-on**; the framework MUST run fully without it
  (Principle IX), and core MUST NOT depend on it. The markup/capability/conversion-plan logic MUST be **pure**
  (headless-testable); GD/Imagick/WP calls are a thin boundary.
- **FR-011**: No new hard dependency; AVIF generation and CDN/Blob offload are **out of scope** (a later increment).
  No secret is exposed.

### Key Entities *(include if feature involves data)*

- **Image capability**: what the server supports — GD, Imagick, WebP, AVIF — detected once.
- **Conversion plan**: for an uploaded image, whether to convert, to which format, and the output path —
  preserving the original.
- **Picture model**: the data a rendered `<picture>` needs — the WebP source(s), the fallback `<img>` src,
  srcset/sizes, alt, lazy vs eager (LCP), decoding, fetchpriority.
- **Image-support report**: the probe's view of GD/Imagick/WebP/AVIF for Site Health + doctor.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: On a supported server, uploading a JPEG/PNG yields a WebP copy **and** the preserved original, in
  **100%** of supported cases; on an unsupported server, the upload still succeeds with **no error**.
- **SC-002**: A developer renders an optimized, accessible `<picture>` (WebP + fallback, lazy/async, LCP-aware)
  through **one** helper, with **zero** hand-written `<img>` tags.
- **SC-003**: A designated LCP/hero image renders **eager** with `fetchpriority="high"`; every other image renders
  lazy — verifiable in the markup.
- **SC-004**: Image-server support (GD/Imagick/WebP/AVIF) is **visible** in Site Health + `wp corex doctor`, as
  advisory status (never a failure that blocks the site).
- **SC-005**: With the `corex-media` add-on disabled/absent, the framework runs fully and the helper degrades to a
  plain `<img>` — core never depends on the add-on.

## Assumptions

- Ships as a new optional add-on `addons/corex-media` (`Corex\Media\`), mirroring the existing add-on pattern
  (gated, self-disabling, never a hard dependency) — reusing the spec-036 health-probe seam and the spec-040 URL
  normalisation. The markup builder + capability detection + conversion plan are **pure** (headless-testable);
  GD/Imagick + WP upload hooks are a thin boundary.
- WebP is the target format for v1; **AVIF generation and CDN/Blob offload are out of scope** (a documented later
  increment — the probe still *reports* AVIF support).
- Conversion runs on the WordPress upload/attachment-metadata hooks, preserving the original and the WP-generated
  sizes; it does not replace WordPress's own image handling, it augments it.
- SVG is **not** enabled by this feature; it follows the site's existing SVG policy.
- Out of scope (explicitly): AVIF generation, CDN/Blob storage, an image CDN/resizing service, and bulk
  re-conversion of the existing media library (a possible later tool).
- Live conversion/probe behavior depends on the server's image libraries; per the environment gate, the pure
  markup/capability/plan logic is unit-tested headlessly and the live conversion runs where GD/Imagick/WebP exist.
