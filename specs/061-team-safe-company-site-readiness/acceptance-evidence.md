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

- **RTL mirroring** (Arabic/logical-property layout across admin + login).
- **200% zoom** reflow (no clipping/overlap at 200%).
- **Full keyboard navigation + visible focus** across every interactive control.
- **Light-mode** login/admin sweep, and **reduced-motion** behavior end-to-end.

Recommended: run these manually on a real browser/WP before the first production client launch, and record results
here.

## Shared-host dist builder verification (Phase 13 / FR-061-06)

- `npm run build:dist -- --dry-run` (real repo): plans **35 trees** (core + framework plugins/addons + theme +
  vendor), correctly **excludes** `wp-config.php` and the symlinked `wp/wp-content/`, and writes nothing. **PASS**
- Jest `tests/build-shared-host-dist.test.js` — **5 passed**: plan composition + manifest shape; never plans
  `wp-config.php`/`wp/wp-content`; built tree excludes `node_modules`/`tests` and passes `verifyDist`; verifier
  rejects a forbidden path; dry-run writes nothing.
