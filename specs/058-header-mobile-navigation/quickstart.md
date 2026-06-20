# Quickstart: Header, Mobile Navigation, Mega Menu, and Footer System

Runnable validation for Spec 058. Implementation details live in `tasks.md`; this is a verification guide.

## Prerequisites

- A WordPress 7.0+ install that recognizes the CoreX theme + plugins (`wp theme list` shows `corex`; `wp plugin
  list` shows the CoreX plugins). The shared local WAMP install is used for non-Docker checks.
- Node + Composer dev dependencies installed (`composer install`, `npm install`).

## 1. Static / unit checks (no browser)

```bash
# PHP: pattern + category registration, conditional-enqueue, markup contracts, theme.json tokens
composer test -- --filter Navigation

# JS: behavior module (scroll state, accordion toggle, reduced-motion guard, teardown)
npm run test:js -- corex-navigation

# Token / raw-literal scan (Principle V): no raw hex or hard-coded font in nav CSS/patterns
npm run lint:css
```

**Expected**: Pest navigation suite green; Jest behavior suite green; CSS lint clean; the raw-literal scan reports
zero hits in `theme/assets/css/corex-navigation.css` and `theme/patterns/*.php`.

## 2. Registration check (WP-CLI, non-Docker OK)

```bash
wp eval 'var_dump( WP_Block_Patterns_Registry::get_instance()->is_registered("corex/header-simple") );'
wp eval 'var_dump( in_array("corex", wp_list_pluck( WP_Block_Pattern_Categories_Registry::get_instance()->get_all_registered(), "name" ), true) );'
```

**Expected**: `bool(true)` for both — the CoreX category and the header-simple pattern are registered.

## 3. Render check (any theme-aware page)

- Activate the CoreX theme; load the front page.
- Confirm the header renders brand (M2 logo) + primary navigation + CTA, and the footer renders regions + a legal
  row inside a `contentinfo` landmark.
- Confirm the navigation CSS loads on this page and is **absent** on a request that renders no navigation (Principle
  VI conditional load).

## 4. Accessibility / behavior (browser — ENVIRONMENT-GATED)

Run where a browser runtime is available (else record ENVIRONMENT-GATED, not PASS):

```bash
npm run test:e2e -- navigation     # Playwright
```

Scenarios:
- Keyboard: Tab reaches every destination (desktop, mega, mobile); visible focus throughout.
- Escape / outside-click close the mega menu, mobile overlay, and search overlay; focus returns to the trigger.
- Mobile (≤ breakpoint): menu control opens a focus-trapped overlay; mega content is an accordion (no hover).
- Sticky/transparent: transparent header switches to a solid AA-contrast background on scroll / menu-open.
- RTL: switch site to an RTL locale; alignment, drawer side, chevrons, and reading order mirror.
- Reduced motion: with `prefers-reduced-motion: reduce`, no non-essential animation plays.
- No horizontal scroll at 320px width or 200% zoom; no clipped content.
- No-JS: disable JavaScript; all destinations remain reachable.

## 5. Guards & Definition of Done

- `wp-guard` (theme/pattern PHP + block markup), `clean-code-guard` (behavior JS), `test-guard` (tests),
  `docs-guard` (docs) run clean on the diff.
- Docs updated (docs-app foundations/patterns); `PROGRESS.md` updated; non-trivial choices in `DECISIONS.md`.

## Environment-gating note

In this workspace, Docker's Linux engine and a compatible browser-automation Node runtime may be unavailable. When
so, steps in §4 (and any wp-env step) are recorded as **ENVIRONMENT-GATED** with the reason, never reported as PASS.
Steps 1–3 run without a browser.
