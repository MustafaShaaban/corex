# Implementation Plan: Site kits — Company, Portfolio, Woo (023)

**Branch**: `023-site-kits` (uncommitted on `develop`) | **Date**: 2026-06-11 | **Spec**: [spec.md](./spec.md)

> Retrospective plan — maps each FR to the file that already satisfies it and flags drift. No new architecture.

## Summary

Three site kits as composition manifests: the Company kit gains a drift-protection test; a new Portfolio kit
adds a `corex_project` CPT + `corex/projects` block + FSE templates under a `Corex\Portfolio\` prefix; a new,
gated Woo kit composes a WooCommerce storefront and declares HPOS, running only when Woo is active AND its flag
is on. Each is wired (Boot/composer/npm) and self-disables rather than hard-depending on an optional plugin.

## Technical Context

**Language/Version**: PHP 8.3 + block JS (built by `@wordpress/scripts`). **Primary Dependencies**: spec-010
Blueprint + spec-009 UI `PatternLibrary`, spec-018 block build; WooCommerce (optional, gated). **Testing**:
Pest (manifest cross-checks, render, gate truth table). **Project Type**: WP add-on plugins. **Constraints**:
kits are pure manifests; blocks bounded/escaped/token-only/RTL; Woo never a hard dependency; HPOS-safe (no
direct order/meta access).

## Constitution Check (v1.2.0)

- [x] **III/IV (layering + DI)** — PASS. Blueprints + gate are pure; `ProjectsRenderer` injects a
  `ProjectsProvider`; `WpProjectsProvider` is the only `WP_Query` caller. No `new` of a service in a method.
- [x] **V/VI/VIII (tokens/dynamic blocks/RTL)** — PASS. `corex/projects` is a dynamic, server-rendered block;
  templates + block styles are token-only + logical CSS; lazy thumbnails.
- [x] **VII (security)** — PASS. Block output escaped; count bounded (1–24); empty-state non-fatal.
- [x] **IX (optional dep)** — PASS (exemplary). `WooKitGate` + a self-disabling provider keep WooCommerce
  strictly optional; the kit declares HPOS and reuses Woo's own blocks (minimal woo-guard surface).
- [x] **X (spec)** — reconciled by this retrospective spec.
- [x] **Guard Gate / DoD** — PARTIAL. wp-guard self-review at delivery; formal re-run (incl. **woo-guard** on
  the Woo kit) is **P2**. Tests: CompanyKitManifest 3 + Portfolio 4 + WooKit 3, green; all three active on real
  WP (0 fatals). READMEs present.

**Gate**: PASS (P2 formal guard re-run incl. woo-guard tracked).

## FR → implementation map

| FR | Satisfied by |
|---|---|
| FR-001 company drift-proof | `addons/corex-kit-company/src/{Blueprint,Company/CompanyBlueprint}.php`; `tests/Unit/Kit/CompanyKitManifestTest.php` |
| FR-002 portfolio CPT + tax | `addons/corex-kit-portfolio/src/PortfolioServiceProvider.php` (`Corex\Portfolio\`, `corex_project` + `project_type`) |
| FR-003 projects block | `addons/corex-kit-portfolio/src/Blocks/{projects/*, ProjectsRenderer, ProjectsProvider, WpProjectsProvider}.php` |
| FR-004 templates + blueprint | `theme/templates/{archive-project,single-project}.html`; `addons/corex-kit-portfolio/src/PortfolioBlueprint.php` |
| FR-005 woo gate | `addons/corex-kit-woo/src/{WooKitGate,WooServiceProvider}.php` (self-disabling) |
| FR-006 HPOS + storefront | `addons/corex-kit-woo/src/{WooServiceProvider (HPOS declare),WooBlueprint}.php` |
| FR-007 wiring + READMEs | `plugins/corex-core/.../Boot` provider list; root `composer.json` PSR-4; `package.json` workspaces; each kit README |

**Drift found:** none material. (`SetupWizard*`/`Blueprint::featureFlags()` physically live in the company kit
but belong to **spec 024** item 13 — scoped out here.)

## Project Structure (already implemented)

```text
addons/
├── corex-kit-company/src/{Blueprint,BlueprintRegistry,Company/CompanyBlueprint,KitServiceProvider}.php
├── corex-kit-portfolio/src/{PortfolioServiceProvider,PortfolioBlueprint,Blocks/*}
└── corex-kit-woo/src/{WooServiceProvider,WooBlueprint,WooKitGate}.php
theme/templates/{archive-project,single-project}.html
tests/Unit/{Kit/CompanyKitManifestTest,Portfolio/PortfolioTest,Woo/WooKitTest}.php
```

## Complexity Tracking

No unjustified violations. The only tracked debt is **P2** (formal guard re-run, incl. woo-guard on the Woo
kit). Visual/editor validity of templates/patterns is a documented browser follow-up.
