# Spec 057 Accessibility and RTL Evidence

Captured: 2026-06-20 EEST

Evidence IDs: `focus-surface`, `forced-colors`, `high-contrast`, `zoom-200`, `text-resize`, `reduced-motion`,
`light`, `dark`, `ltr`, `rtl`.

## Headless contracts

| Evidence | Result | Command or source |
|---|---|---|
| Light and dark contrast matrix | PASS | `ContrastMatrixTest.php`; all declared WCAG thresholds pass |
| Focus-surface coverage | PASS | Base, raised, status, overlay-equivalent, and future admin-context pairs are recorded |
| LTR and RTL fixture schema | PASS | Arabic, Latin, mixed commands, numerals, and bidi isolation hooks covered |
| Reduced-motion | PASS | Dedicated Playwright media emulation and fixture rule |
| Forced-colors / high-contrast | PASS | Chromium forced-colors emulation verifies the focusable control |
| Zoom-200 and text-resize behavior | PASS | Chromium fixture at 200% zoom remains focusable without scroll styling |
| Light/dark rendered surfaces | PASS | Chromium fixture switches to the complete dark raised-surface mapping |

The admin focus pair verifies the canonical palette contract only. The scoped `--corex-admin-*` adapter remains a
separate US4 responsibility and is not represented as implemented here.

## Browser and environment evidence

- Standalone Chromium fixture: PASS, 4/4 Playwright scenarios.
- Local WordPress CLI recognition/readiness: PASS from the established isolated installation.
- WordPress-rendered browser flow: ENVIRONMENT-GATED; the standalone fixture does not prove WordPress rendering.
- Docker/wp-env: ENVIRONMENT-GATED because the Docker engine is unavailable.
- External deployment: ENVIRONMENT-GATED; no deployment was performed.

## Typography and assets

- System Latin body, Space Grotesk heading, JetBrains Mono technical, and IBM Plex Sans Arabic fallback roles: PASS
  at the token-contract level.
- Approved self-hosted WOFF2 files and provenance manifest: BLOCKED on owner-approved assets.
- No asset file, checksum, license claim, or preload was invented.

## Guard and verification gate

- `clean-code-guard`: PASS; the implementation stays token-based and adds no speculative asset loader.
- `wp-guard`: PASS; WordPress-native theme data is retained, logical CSS is used, and no global enqueue changed.
- `test-guard`: PASS after replacing indirect overflow styling with an observable scroll-width assertion and adding
  computed direction/focus evidence.
- `docs-guard`: PASS; paths, commands, counts, blocked claims, and environment gates were verified against source and
  command output.
- Accessibility/RTL review: PASS for the headless contract and standalone Chromium fixture; WordPress-rendered
  browser evidence remains explicitly environment-gated.
- Full Pest is not green: 647 tests pass and nine future-story contracts remain RED (one blocked font manifest,
  three blocked logo contracts, three US4 admin-adapter contracts, and two US4 brand-validator contracts).
