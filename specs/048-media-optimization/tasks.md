# Tasks: Media & image optimization

**Feature**: 048-media-optimization · **Branch**: `feature/048-media-optimization`
**Input**: [spec.md](./spec.md) · [plan.md](./plan.md)

**Tests**: REQUIRED (Pest; live GD/Imagick conversion env-gated).

**Story legend**: US1 = WebP on upload (P1, MVP) · US2 = picture helper (P1) · US3 = responsive srcset (P2) ·
US4 = image-support probe (P2).

---

## Phase 2: Foundational pure cores (block the stories)

- [ ] T001 [P] Pest `tests/Unit/Media/ImageCapabilityTest.php` — the value object reports gd/imagick/webp/avif; `canWebp()` true only with a converter + webp.
- [ ] T002 Implement `Corex\Media\ImageCapability` (pure value object + static `detect()` boundary) until T001 green.
- [ ] T003 [P] Pest `tests/Unit/Media/ConversionPlanTest.php` — JPEG/PNG + webp-capable → convert + output path (original preserved); unsupported/non-image/already-webp → skip.
- [ ] T004 Implement `Corex\Media\ConversionPlan` (pure) until T003 green.

## Phase 3: US1 — WebP on upload (P1, MVP)

- [x] T005 [US1] Implement `Corex\Media\WebpConverter` (boundary: GD/Imagick conversion of the plan's target, fail-safe — corrupt/oversized → original, logged) + hook it on the WP upload/metadata hook in `MediaServiceProvider` (gated by `ImageCapability::detect()`).

## Phase 4: US2 + US3 — picture helper + responsive (P1/P2)

- [ ] T006 [P] [US2] Pest `tests/Unit/Media/PictureRendererTest.php` (stub esc_*) — `<picture>` with a webp `<source>` + `<img>` fallback + real alt + lazy/async; LCP → fetchpriority=high + eager; no webp → plain `<img>`; empty alt valid; srcset when multiple widths.
- [ ] T007 [US2] Implement `Corex\Media\PictureRenderer` (pure, escaped) until T006 green.
- [x] T008 [US2] Implement `Corex\Media\MediaImage` helper (attachment id → renderer data via WP image functions → markup) + bind it; the helper degrades to a plain `<img>` when the add-on data is absent.

## Phase 5: US4 — image-support probe (P2)

- [x] T009 [US4] `Corex\Media\MediaImageProbe` — reports GD/Imagick/WebP/AVIF into the spec-036 health seam + `wp corex doctor` as good/recommended (advisory). Pest on the pure status from injected capability.

## Phase 6: Polish

- [x] T010 [P] Docs: `addons/corex-media/README.md` + docs-app `guides/media.md` (the add-on, helper, capabilities, WebP/original-preservation, AVIF/CDN out of scope). Wire the add-on into Boot + composer PSR-4 + AddonRegistry.
- [x] T011 Guard Gate (clean-code, wp-guard — escaped markup, upload-hook safety, no secret, advisory probe, test-guard, docs-guard).
- [x] T012 Suites green (`composer test`); record counts. Live conversion/probe env-gated.
- [ ] T013 Update `PROGRESS.md` + `DECISIONS.md` #82; NEXT STEP. Commit → PR → CI → merge.

---

## Dependencies & order

- Foundational (T001–T004) block the stories. **MVP = US1** (WebP on upload). US2/US3 (helper) build on the cores;
  US4 (probe) on the capability. Polish last.
- TDD: T001→T002, T003→T004, T006→T007, the probe status T009.
- **Parallel**: T001/T003/T006 (`[P]`), docs T010.
