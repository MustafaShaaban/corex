# Implementation Plan: Add-on manager admin screen (026)

**Branch**: `feature/026-addon-manager` | **Date**: 2026-06-11 | **Spec**: [spec.md](./spec.md)

## Summary

A "Corex Add-ons" submenu in `corex-config` that lists every Corex add-on and toggles each (plugin
activation + its feature flag) with dependency awareness. A pure `AddonRegistry` (the known add-ons + deps)
and a pure `AddonManager` (compute the view model + decide whether a toggle is allowed, naming the blocker)
make every decision headlessly; an `AddonsScreen` renders + gates (via the shared `AdminGuard`) and an
`AddonActivator` performs the plugin/flag writes. Mirrors the settings + setup-wizard screens exactly.

## Technical Context

**Language/Version**: PHP 8.3. **Primary Dependencies**: the P5 `Corex\Security\Admin\AdminGuard` (cap +
nonce), the spec-021 feature-flag option layer, WP plugin APIs (`activate_plugins`/`deactivate_plugins`/
`is_plugin_active`) at the activator boundary. **Testing**: Pest — registry + manager unit-tested headlessly;
the activator's flag toggle integration-tested on `./wp`. **Project Type**: WP plugin (`corex-config`).
**Constraints**: pure registry/manager (no WP); all output escaped + i18n + RTL; toggles nonce + cap gated;
dependency conflicts refused deterministically (no silent cascade).

## Constitution Check (v1.2.1)

- [x] **III/IV (layering + DI)** — PASS. `AddonRegistry`/`AddonManager`/value objects pure + injected;
  `AddonsScreen` renders + gates + delegates; `AddonActivator` is the WP boundary. No `new` of a service in a
  method.
- [x] **VII (security)** — PASS. Toggles route cap + nonce through `AdminGuard` (Principle VII admin-screen
  policy, DECISIONS #58); all output escaped; dependency rules prevent leaving the site broken.
- [x] **VIII (i18n/RTL)** — PASS. Every string translation-ready (`corex` domain); layout uses logical CSS /
  WP admin classes (RTL-correct), like the settings + setup-wizard screens.
- [x] **IX (optional dep)** — PASS. An uninstalled add-on is shown unavailable, never fatal; no add-on is a
  hard dependency of the screen.
- [x] **X (spec)** — implements spec 026 (written first).
- [x] **Guard Gate / DoD** — planned: clean-code-guard (registry/manager/screen) + wp-guard (screen escaping +
  nonce/cap, activator plugin/option writes) + test-guard (Pest). Docs: `plugins/corex-config/README.md`.

**Gate**: PASS.

## Architecture (to build) — `plugins/corex-config/src/Addons/`

| Component | Kind | Responsibility |
|---|---|---|
| `Addon` | pure value object | slug, pluginFile, label, `?flag`, `requires` (list of add-on slugs) |
| `AddonRegistry` | pure service | `all(): list<Addon>` — the known Corex add-ons + their dependencies |
| `AddonState` | pure value object | `activeSlugs` + `enabledFlags` snapshot (fed from WP) + `isActive()`/`flagOn()` |
| `AddonView` | pure value object | an `Addon` + active? + flagOn? + installed? + `blockedReason` (null when togglable) |
| `AddonManager` | pure service | `views(state): list<AddonView>`; `canEnable/canDisable(slug,state)`; `blockingDependents()` / `missingDependencies()` |
| `AddonsScreen` | admin boundary | submenu under `corex-settings`; render the list + per-add-on form; `maybeToggle()` (AdminGuard) → activator |
| `AddonActivator` | WP boundary | `enable(Addon)` / `disable(Addon)` — `activate_plugins`/`deactivate_plugins` + set/clear the flag option |

**Dependency rules** (pure, in `AddonManager`):
- `canDisable(A)` = no **active** add-on requires A (else blocked, naming the dependents).
- `canEnable(B)` = every add-on in `B.requires` is **active** (else blocked, naming the missing deps).

## Registry (initial content)

Add-ons (slug → plugin file, flag, requires) — kits require the UI library, mirroring the blueprints:

| slug | plugin file | flag | requires |
|---|---|---|---|
| `corex-ui` | `corex-ui/corex-ui.php` | — | — |
| `corex-email` | `corex-email/corex-email.php` | — | — |
| `corex-captcha` | `corex-captcha/corex-captcha.php` | — | — |
| `corex-newsletter` | `corex-newsletter/corex-newsletter.php` | — | — |
| `corex-careers` | `corex-careers/corex-careers.php` | — | — |
| `corex-bookings` | `corex-bookings/corex-bookings.php` | — | — |
| `corex-kit-company` | `corex-kit-company/corex-kit-company.php` | — | `corex-ui` |
| `corex-kit-portfolio` | `corex-kit-portfolio/corex-kit-portfolio.php` | — | `corex-ui` |
| `corex-kit-woo` | `corex-kit-woo/corex-kit-woo.php` | `woocommerce_kit` | `corex-ui` |

## Project Structure (to create)

```text
plugins/corex-config/src/Addons/{Addon,AddonRegistry,AddonState,AddonView,AddonManager,AddonsScreen,AddonActivator}.php
plugins/corex-config/src/ConfigServiceProvider.php   (register the screen, like AdminDashboard)
tests/Unit/Config/AddonRegistryTest.php              (the known add-ons + deps)
tests/Unit/Config/AddonManagerTest.php               (views + the dependency-aware toggle rules)
tests/Integration/Config/AddonActivatorTest.php      (flag toggle on ./wp; plugin toggle reversible)
```

## Phase 0 / 1 artifacts

- `research.md` — dependency-conflict policy (refuse + explain) and the flag↔plugin sync.
- `data-model.md` — the value objects + manager methods.
- `contracts/addons-screen-contract.md` — the screen's menu, form fields, and gating behaviour.
- `quickstart.md` — run + validate (list, toggle, dependency refusal).

## Complexity Tracking

No unjustified violations. The pure-core + admin-boundary split is the established Corex admin pattern
(AdminDashboard, SetupWizardScreen); the dependency logic is the feature's substance and lives in the pure,
tested `AddonManager`.
