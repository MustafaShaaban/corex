# Implementation Plan: Header, Mobile Navigation, Mega Menu, and Footer System

**Branch**: `spec/058-header-mobile-navigation` | **Date**: 2026-06-20 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/058-header-mobile-navigation/spec.md`; design input [M3 navigation and footer handoff](../../design/handoffs/navigation-footer.md).

## Summary

Deliver reusable, accessible site **header** and **footer** building blocks for CoreX block themes — FSE template
parts plus a set of registered block patterns per variant — that a site owner composes without a builder. The MVP
(P1) is an accessible header (brand + primary navigation + CTA) whose navigation collapses into an accessible mobile
menu. Mega menus (P2), footer variants (P2), and the header variants/sticky/transparent behavior + action slots (P3)
layer on top. All visual values come from the merged M2 brand tokens (Spec 057); this feature adds layout, behavior,
and accessibility only — no new brand values, no commerce/account logic, no builder.

**Technical approach**: lean on the WordPress core `core/navigation` block, which already ships an accessible mobile
overlay and submenu disclosure (focus trap, Escape, `aria-expanded`, outside-click) via the Interactivity API. CoreX
adds: (1) **patterns** (`theme/patterns/*.php`, auto-registered by WordPress for block themes) for each header/footer
variant and the mega-menu layouts; (2) **scoped presentation CSS** consuming `theme.json` tokens; and (3) a small,
**conditionally enqueued** behavior script for the increments core does not provide (sticky/transparent header,
mega-menu panel positioning, mobile mega accordion). Pattern-category registration and the conditional behavior
asset live in a **plugin** service provider (Principle I keeps the theme presentation-only and disposable); the
pattern/part markup and CSS live in the **theme**.

## Technical Context

**Language/Version**: PHP 8.3+ (plugin registration), HTML block markup (theme parts/patterns), CSS (logical
properties), vanilla JS using the WordPress Interactivity API / small enhancement script. Target WordPress 7.0+.

**Primary Dependencies**: WordPress core blocks (`core/navigation`, `core/site-logo`/`core/site-title`,
`core/group`, `core/buttons`, `core/columns`, `core/social-links`), the Interactivity API (bundled with core), and
the merged M2 `theme.json` tokens + logo package (Spec 057). No new third-party dependency.

**Storage**: N/A — file-based (template parts, patterns, theme.json, CSS/JS assets). No database, no options, no CPT.

**Testing**: Pest (PHP: pattern/category registration, conditional-enqueue logic, markup contracts), Jest (JS: the
behavior enhancement module — sticky/transparent state machine, accordion toggle, reduced-motion guard), Playwright
(E2E: keyboard/focus/Escape/outside-click, RTL, reduced-motion) where the browser runtime is available; otherwise
ENVIRONMENT-GATED.

**Target Platform**: WordPress 7.0+ FSE block theme (front end), modern evergreen browsers; progressive-enhancement
fallback for no-JS.

**Project Type**: WordPress framework — theme (presentation) + plugin (registration/conditional assets).

**Performance Goals**: no global site-wide CSS/JS; the behavior script loads only where a CoreX header that needs it
renders, is small (target < 5KB min), and degrades to a usable server-rendered fallback. No layout shift from the
sticky/transparent transition; no animation under `prefers-reduced-motion`.

**Constraints**: WCAG 2.2 AA; RTL-first via logical properties; tokens are runtime (`theme.json`), never build-time;
no raw hex/size/font in CSS; no client-side framework, icon font, or build-time token dependency.

**Scale/Scope**: 6 header variants, 4 mega-menu layouts, 4 mobile-nav patterns, 6 footer variants, each with an RTL
example; one behavior enhancement module; docs + tests. Bounded by FR-021 (no builders/commerce/M4-M5-M9/Pro).

## Constitution Check

*GATE: passed before Phase 0; re-checked after Phase 1 design (below).*

- [x] **I. Theme is a skin** — theme holds only parts, patterns, style/CSS, token consumption. All PHP registration
  (pattern categories, conditional behavior asset) lives in a plugin service provider. Deactivating the theme breaks
  presentation only.
- [x] **II. Plugins boot themselves** — the new provider registers on the existing plugin boot (`plugins_loaded`
  chain via `corex-core`), not on a theme hook; pattern-category registration and asset registration do not require
  the CoreX theme to be active.
- [x] **III. Thin controllers, fat services** — N/A for routes; the provider only registers patterns/assets (no
  controllers, no business rules, no DB).
- [x] **IV. Everything injected** — the provider is constructed/booted through the existing PSR-11 container/service-
  provider mechanism; no `new` of a dependency inside a method.
- [x] **V. Runtime tokens** — all color/spacing/typography/radius/shadow/focus/z values come from `theme.json`
  presets/custom properties; only layout-level custom props (header height, nav breakpoint) are added under the
  existing `custom` convention. No raw hex/size/font.
- [x] **VI. Conditional assets** — the behavior CSS/JS is registered (never globally enqueued) and attached only to
  the CoreX navigation surfaces that render it (via `wp_enqueue_block_style`/render-time enqueue), mirroring
  `HttpServiceProvider`. No global library.
- [x] **VII. Declarative security** — N/A: no routes, no AJAX/REST, no user input, no DB writes. Pattern PHP files
  contain only static block markup and translatable strings (escaped where dynamic). No capability/nonce surface.
- [x] **VIII. RTL-first** — all CSS uses logical properties; RTL examples shipped per variant; mega-menu/drawer
  mirroring verified. Arabic typography uses the M2 Arabic font role.
- [x] **IX. No optional dep is hard** — no ACF/Woo/Polylang/WPML dependency; WooCommerce nav/footer explicitly
  deferred to M9. Language-switcher slot is a structural placeholder, not a Polylang/WPML dependency.
- [x] **X. Spec is source of truth** — this plan traces to the approved spec.md and the approved design handoff.
- [x] **Guard Gate + Definition of Done** acknowledged: each task runs the applicable guards
  (`wp-guard` for PHP/theme/block, `clean-code-guard` for JS, `test-guard` for tests, `docs-guard` for docs); tests,
  i18n, RTL, WCAG 2.2 AA, docs + PROGRESS updates required per task.

**Result: PASS.** No violations; Complexity Tracking not required.

## Project Structure

### Documentation (this feature)

```text
specs/058-header-mobile-navigation/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output
│   ├── pattern-registration.md
│   ├── token-consumption.md
│   └── interaction-behavior.md
├── checklists/
│   └── requirements.md  # from /speckit-specify
└── tasks.md             # Phase 2 output (/speckit-tasks)
```

### Source Code (repository root)

```text
theme/                                  # Presentation only (Principle I)
├── parts/
│   ├── header.html                     # default header part (P1) — references the simple-company pattern
│   └── footer.html                     # default footer part (P3) — references the simple footer pattern
├── patterns/                           # NEW — auto-registered by WP for block themes
│   ├── header-simple.php               # P1
│   ├── header-corporate.php            # P3 (top utility bar)
│   ├── header-saas.php                 # P3 (mega menu)
│   ├── header-docs.php                 # P3 (search slot)
│   ├── header-transparent.php          # P3
│   ├── header-minimal.php              # P3
│   ├── megamenu-simple.php             # P2
│   ├── megamenu-services.php           # P2
│   ├── megamenu-product.php            # P2
│   ├── megamenu-docs.php               # P2
│   ├── footer-simple.php               # P3 default
│   ├── footer-corporate.php            # P2/P3
│   ├── footer-saas.php
│   ├── footer-newsletter.php
│   ├── footer-locations.php
│   └── footer-legal.php
├── assets/
│   ├── css/
│   │   └── corex-navigation.css        # scoped nav/footer presentation (token-driven, logical props)
│   └── js/
│       └── corex-navigation.js         # sticky/transparent + mega-menu/accordion enhancement (conditional)
├── theme.json                          # + header layout custom props (height, breakpoint)
└── styles/                             # existing dark/editorial variations (unchanged unless nav needs tokens)

plugins/corex-core/src/
└── Theme/ (or Foundation/)
    └── NavigationServiceProvider.php   # NEW — registers pattern category + registers (not global-enqueues)
                                        # corex-navigation css/js; attaches to nav surfaces (Principle VI)

tests/
├── Pest (PHP)     # pattern/category registration, conditional-enqueue, markup contracts
└── Jest (JS)      # behavior module unit tests
docs-app/...       # foundations/patterns docs for navigation + footer
```

**Structure Decision**: Two-surface split mandated by Principle I. **Theme** owns all markup (parts, patterns),
presentation CSS, and token additions. **Plugin** (`corex-core`) owns the single new `NavigationServiceProvider`
that registers the CoreX pattern category and registers the behavior CSS/JS for conditional, surface-scoped loading.
This keeps the theme disposable and the assets conditional. Patterns are PHP files under `theme/patterns/` so
WordPress core auto-registers them for block themes without theme bootstrapping.

## Phase 0 — Research

See [research.md](./research.md). It resolves: (1) where pattern-category registration lives (plugin vs theme);
(2) the theme-vs-plugin owner of the behavior CSS/JS and the conditional-enqueue mechanism; (3) how far the core
`core/navigation` Interactivity API covers keyboard/focus/Escape/outside-click so CoreX builds the minimum
increment; (4) the sticky/transparent technique (scroll state) with a reduced-motion guard and no layout shift;
(5) the accessible mega-menu disclosure pattern and its mobile-accordion equivalent; and (6) the nav breakpoint as a
token.

## Phase 1 — Design & Contracts

- [data-model.md](./data-model.md): file-based entities — header/footer template parts, the per-variant pattern set,
  mega-menu item anatomy, action slots, and the shared navigation menu. No database.
- [contracts/pattern-registration.md](./contracts/pattern-registration.md): pattern names/slugs/categories, the
  CoreX pattern category, auto-registration expectations, and the conditional-enqueue contract for the behavior
  asset.
- [contracts/token-consumption.md](./contracts/token-consumption.md): the exact M2 tokens nav/footer consume and the
  new layout custom props; the "no new brand value / no raw literal" rule.
- [contracts/interaction-behavior.md](./contracts/interaction-behavior.md): keyboard, focus management, Escape,
  outside-click, sticky/transparent state, mobile accordion, RTL mirroring, reduced-motion, and the no-JS fallback —
  each as a testable behavioral contract.
- [quickstart.md](./quickstart.md): runnable verification (activate parts/patterns, render checks, Pest/Jest,
  accessibility checks) with explicit ENVIRONMENT-GATED notes for wp-env/browser evidence.

### Implementation phasing (mapped to user stories)

1. **Phase A — P1 MVP (US1)**: header part + `header-simple` pattern (brand/logo + `core/navigation` with
   `overlayMenu` + CTA); nav breakpoint + header tokens in `theme.json`; scoped CSS; `NavigationServiceProvider`
   (category + conditional CSS). Verify keyboard/focus/Escape/outside-click via core nav, visible focus, RTL, no
   horizontal scroll. Tests: Pest (registration/markup), Playwright (ENV-gated).
2. **Phase B — P2 mega menu (US2)**: mega-menu patterns + the behavior JS (panel disclosure beyond core, mobile
   accordion), `aria-expanded`/controls semantics, reduced-motion guard. Tests: Jest (behavior), Pest (markup).
3. **Phase C — P2 footer (US3)**: footer part + footer variant patterns; contentinfo landmark, reflow, RTL.
4. **Phase D — P3 variants/behavior/slots (US4)**: remaining header variants, sticky/transparent state, action-slot
   placeholders. Tests + docs.
5. **Phase E — Docs + final gate**: docs-app foundations/patterns pages, full test/build run, guards, PROGRESS/
   ROADMAP/CHANGELOG, ENVIRONMENT-GATED evidence recorded honestly.

## Complexity Tracking

No constitution violations. Not applicable.
