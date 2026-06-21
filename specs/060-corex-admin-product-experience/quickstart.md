# Quickstart: CoreX Admin Product Experience

Validation guide for Spec 060. Implementation in tasks.md.

## Prerequisites

- WordPress 7.0+ recognizing the CoreX theme + plugins; `corex-config` active. Composer + npm dev deps installed.

## 1. Unit checks (no browser)

```bash
# Add-on state resolver matrix, settings-section state, captcha write-only + states, asset scoping, readiness gating
composer test -- --filter Addon
composer test -- --filter Settings
composer test -- --filter Captcha
```

**Expected**: resolver returns exactly one correct `AddonStatus` per runtime combination; `canToggle` only for
installed; settings sections reflect state; captcha secret never rendered; admin adapter/CSS registered but not
globally enqueued.

## 2. Asset scoping (WP-CLI / non-Docker OK)

- Load a CoreX admin screen and confirm `corex-admin-tokens` + the screen CSS load; load a non-CoreX wp-admin page
  and the front page and confirm they are **absent** (no global restyle, no frontend branding).

## 3. State walk-through (admin)

- Toggle captcha install/active/flag/keys and confirm the Add-ons + Settings screens show: not installed → inactive →
  feature off → configuration needed → active+configured. Confirm a not-installed add-on offers no enable action.

## 4. Accessibility / RTL / responsive (browser — ENVIRONMENT-GATED)

```bash
npm run test:e2e -- admin    # Playwright, where a browser runtime is available
```

- Landmarks/heading order, WCAG 2.2 AA contrast (dark + light), visible focus, status not by color alone; RTL
  mirroring; reduced motion; no horizontal scroll at narrow widths / 200% zoom; permission-denied/empty/error states.

## 5. Readiness honesty

- Open the Readiness/Status screen; confirm environment-gated checks render as gated, never green.

## 6. Guards & Definition of Done

- `wp-guard`, `clean-code-guard`, `test-guard`, `docs-guard` clean; docs + PROGRESS updated; DECISIONS entry.

## Environment-gating note

Where Docker/wp-env or a browser runtime is unavailable, step 4 (and any wp-env render) is ENVIRONMENT-GATED, never
PASS. Steps 1–2 run without a browser.
