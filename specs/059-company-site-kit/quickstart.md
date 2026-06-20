# Quickstart: Company Site Kit v1

Validation guide for Spec 059. Implementation lives in tasks.md; this verifies it.

## Prerequisites

- WordPress 7.0+ recognizing the CoreX theme + plugins (`wp theme list`/`wp plugin list`), with `corex-ui` active
  (the kit's required module). Composer + npm dev deps installed.

## 1. Unit checks (no browser)

```bash
# Blueprint coverage, demo-level parity, token-only page markup, registered-pattern references
composer test -- --filter Company
composer test -- --filter Kit
```

**Expected**: the company-kit suite is green — full v1 page set present, one `front`, unique slugs, no raw literals,
only registered patterns referenced, demo levels structurally identical.

## 2. Preview/apply (WP-CLI / setup, non-Docker OK)

- Trigger the company kit apply at `minimal` on a clean site; confirm the **preview summary** lists every page before
  any mutation, then confirm and verify the pages exist with the M3 header/footer.
- Re-run; confirm existing slugs follow the chosen `PageDisposition` (`skip`/`adopt`) and nothing is silently
  overwritten.

## 3. Demo levels & SEO

- Apply at `minimal`, `standard`, `full` on clean sites; confirm identical page set/section order with differing
  content volume.
- Confirm each content page has editable SEO starter metadata that a common SEO plugin can read/override.

## 4. Accessibility / RTL / responsive (browser — ENVIRONMENT-GATED)

```bash
npm run test:e2e -- company-kit   # Playwright, where a browser runtime is available
```

- Landmarks/heading order per page; WCAG 2.2 AA contrast (dark + light); RTL mirroring; no horizontal scroll at
  320px / no clipping at 200% zoom.

## 5. Start a real site

```bash
wp corex make:site "<CompanyName>"
```

Confirm the scaffold + the company kit give a brand-aware, navigable starting point that uses CoreX foundations
without editing framework internals (see `make-site-verification.md` for the baseline run and the inheritance gap).

## 6. Guards & Definition of Done

- `wp-guard`, `clean-code-guard`, `test-guard`, `docs-guard` clean on the diff; docs + PROGRESS updated;
  non-trivial choices in DECISIONS.

## Environment-gating note

Where Docker/wp-env or a compatible browser runtime is unavailable, steps 4 (and any wp-env apply) are recorded
ENVIRONMENT-GATED, never PASS. Steps 1–2 run without a browser.
