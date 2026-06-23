# Acceptance evidence — spec 061

## M6 admin design acceptance sweep (Phase 16)

Rendered against the live site `http://corex.local` (authenticated admin), Playwright + Chromium, dark mode.

| Surface | Check | Result |
|---|---|---|
| `wp-login.php` (logged out) | `corex-login` body class, dark canvas `rgb(22,24,29)` (#16181d), SSO button present, leading user icon present | **PASS** |
| Add-ons screen | `corex-admin-screen` shell; 10 add-on cards; 10 tier badges; "Where to start" guidance; **0 relative doc links** (all absolute, GitHub fallback) | **PASS** |
| Settings / Overview | `corex-admin-screen` shell present | **PASS** |

Verbatim DOM probe (2026-06-22):
```
login:  { corexClass:true, bg:"rgb(22, 24, 29)", hasSso:true, hasUserIcon:true }
addons: { adminShell:true, cards:10, tierBadges:10, guidance:true, docLinkCount:5, relativeDocLinks:0 }
overview:{ adminShell:true }
```

### Environment-gated (NOT claimed passed)

The following require a manual/assistive-tech pass and a browser matrix this environment cannot fully drive; they
are **environment-gated**, not verified:

- **Full keyboard navigation + visible focus** across every interactive control — programmatic focus and a 25-Tab
  sweep across the full wp-admin chrome did not reliably isolate the CoreX content's `:focus-visible` ring, so this
  stays a **manual** sweep (the CSS does define `:focus-visible` rings — verified in source: login + shell
  stylesheets use `:where(a,button,input):focus-visible { outline … }`, and the RTL login render below shows a
  visible brass focus ring on the focused field).
- **Light-mode** login/admin sweep, and **reduced-motion** behavior end-to-end (reduced-motion was verified earlier
  via `#login` animation-duration collapsing to `1e-05s`; the full light-mode pass remains manual).

### Automated portions now verified (Priority 2, 2026-06-23)

| Check | Method | Result |
|---|---|---|
| **RTL mirroring — login** | `wp-login.php` rendered with `dir="rtl"` (dark) | **PASS** — `document` horizontal overflow = 0; layout mirrors correctly (SSO key icon + field icons move to the inline-end, reveal toggle to the inline-start, "←/?" punctuation mirrored). Proof: `tests/e2e/clip/m6-rtl-login.png`. |
| **RTL mirroring — admin (Add-ons)** | Add-ons screen with `dir="rtl"` | **PASS** — horizontal overflow = 0 (logical CSS). |
| **200% zoom — admin (Add-ons)** | `body { zoom: 2 }` on the Add-ons screen | **PASS** — content reflows within the viewport, no horizontal overflow. |

Recommended: run the remaining manual items (full keyboard sweep with a screen reader, light-mode pass) on a real
browser/WP before the first production client launch, and record results here.

## Shared-host dist builder verification (Phase 13 / FR-061-06)

- `npm run build:dist -- --dry-run` (real repo): plans **35 trees** (core + framework plugins/addons + theme +
  vendor), correctly **excludes** `wp-config.php` and the symlinked `wp/wp-content/`, and writes nothing. **PASS**
- Jest `tests/build-shared-host-dist.test.js` — **5 passed**: plan composition + manifest shape; never plans
  `wp-config.php`/`wp/wp-content`; built tree excludes `node_modules`/`tests` and passes `verifyDist`; verifier
  rejects a forbidden path; dry-run writes nothing.
