# Implementation Plan: corex-core Foundation

**Branch**: `001-corex-core-foundation` | **Date**: 2026-06-08 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/001-corex-core-foundation/spec.md`

## Summary

Deliver the corex-core bootstrap layer: a self-booting kernel (`Boot` on `plugins_loaded`) that
stands up a PSR-11 dependency-injection container, runs a **Service Provider** register/boot
lifecycle, exposes a layered **Config** engine (`.env` → WP options → defaults), wires WordPress
hooks declaratively via a **HookRegistry**, and discovers controllers by PSR-4 convention via a
**ControllerMap**. No business logic. The design's load-bearing idea is the **Service Provider** as
the single extension seam — every future module and third-party add-on registers with the framework
by shipping a provider, which is what makes the framework scale without touching core.

## Technical Context

**Language/Version**: PHP 8.3 (`declare(strict_types=1)` everywhere)

**Primary Dependencies**:
- `psr/container` (PSR-11 interface — the resolution contract)
- *(No third-party container engine — `Corex\Container\Container` is a focused custom container; the
  original `league/container` choice was reversed during T011, see research.md R1 + DECISIONS #21.)*
- `vlucas/phpdotenv` (`.env` parsing — safe/immutable load; decided in spec Clarifications)
- WordPress 7.0 runtime APIs (`add_action`/`add_filter`, options, `WP_DEBUG`, admin notices)

**Storage**: No custom tables. Config persistence uses existing WP options; `.env` is a file at the
monorepo root.

**Testing**: Pest (on PHPUnit) + Brain Monkey/WP function stubs for headless unit tests; a thin WP
integration suite run against the local install (`./wp`) for the boot/contexts and admin-notice paths.

**Target Platform**: WordPress ≥ 7.0, PHP ≥ 8.3 — front-end, admin, REST, WP-CLI, cron contexts.

**Project Type**: WordPress framework plugin (`corex-core`) inside a Composer/npm monorepo.

**Performance Goals**: Boot overhead negligible (< a few ms); container resolution O(depth of graph);
discovery results cached per request so a repeated boot does no extra filesystem scan.

**Constraints**: Non-fatal boot (no white screen, Principle II); zero hard dependency on optional
plugins (Principle IX); presentation-free (Principle I/III); single authoritative autoloader at the
monorepo root.

**Scale/Scope**: Foundation only. Must scale to dozens of modules/add-ons each contributing providers,
controllers, and hook subscribers — without edits to core. No NEEDS CLARIFICATION remain (resolved in
the spec's Clarifications session and DECISIONS #19).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Derived from `.specify/memory/constitution.md` (Corex Constitution v1.1.0).

- [x] **I. Theme is a skin** — **PASS**: all code ships in `corex-core`; nothing is placed in the
  theme; foundation is presentation-free.
- [x] **II. Plugins boot themselves** — **PASS**: `Boot::init()` self-registers on `plugins_loaded`;
  works in CLI/REST/admin/cron; no theme dependency; the config engine lives in core so it is
  self-sufficient (DECISIONS #19).
- [x] **III. Thin controllers, fat services** — **PASS**: foundation defines the layering and the
  `Controller` discovery contract; it adds no controller business logic.
- [x] **IV. Everything injected** — **PASS**: the container is the single resolution mechanism;
  autowiring + explicit interface bindings (FR-007a); the one global accessor is bounded to
  framework-boundary use and documented as such (FR-008a).
- [x] **V. Runtime tokens** — N/A (no styling in this feature).
- [x] **VI. Conditional assets** — N/A (no block assets in this feature).
- [x] **VII. Declarative security** — the route-middleware system is a later phase; the lone UI here
  (the admin notice) escapes output and uses a capability check — no hand-rolled request security.
- [x] **VIII. RTL-first** — the single admin notice uses logical CSS only and translatable strings.
- [x] **IX. No optional dep is hard** — **PASS**: ACF/Woo/Polylang/WPML are never referenced; SC-001
  proves boot with all of them absent.
- [x] **X. Spec is source of truth** — **PASS**: this plan traces to spec 001 (clarified); the
  spec↔framework-doc conflict was resolved and the doc amended (DECISIONS #19) before planning.
- [x] **Guard Gate + Definition of Done** acknowledged — every task runs `clean-code-guard` +
  `wp-guard` (and `test-guard` for test tasks, `docs-guard` for docs) clean before it ships; Pest
  tests written; i18n for the admin notice; RTL verified; PROGRESS/DECISIONS updated per task.

**Result: PASS** — no violations; Complexity Tracking not required.

## Project Structure

### Documentation (this feature)

```text
specs/001-corex-core-foundation/
├── plan.md              # This file
├── spec.md              # Feature spec (clarified)
├── research.md          # Phase 0 — tech decisions + rationale
├── data-model.md        # Phase 1 — entities + lifecycle
├── quickstart.md        # Phase 1 — runnable validation scenarios
├── contracts/
│   └── foundation-contracts.md   # Phase 1 — interface contracts (public API)
└── checklists/
    └── requirements.md  # Spec quality checklist (passing)
```

### Source Code (repository root)

All foundation code lives in `corex-core` (PSR-4 `Corex\` → `plugins/corex-core/src/`). Namespaces
are chosen to avoid the `Corex\Config\` collision with the corex-config plugin: the core config
**engine** uses `Corex\Support\Config\`; the corex-config plugin keeps `Corex\Config\` for its
settings UI.

```text
plugins/corex-core/
├── corex-core.php                     # entry; calls Corex\Boot::init() on plugins_loaded
└── src/
    ├── Boot.php                       # Corex\Boot — idempotent static entry, builds the Application
    ├── Foundation/
    │   ├── Application.php            # holds the Container; runs the provider register→boot lifecycle
    │   ├── ServiceProvider.php        # abstract base: register() + boot() — THE extension seam
    │   └── ProviderRepository.php     # collects providers (core + config + discovered add-ons), orders them
    ├── Container/
    │   ├── Container.php              # Corex\Container — PSR-11 + bind/singleton/make; wraps league/container
    │   ├── ContainerInterface.php     # extends Psr\Container with bind/singleton/make/tag
    │   └── Exceptions/
    │       ├── BindingResolutionException.php   # FR-009 (unresolvable / unhinted scalar)
    │       └── CircularDependencyException.php  # FR-010
    ├── Hooks/
    │   ├── HookRegistry.php           # wires subscribers to add_action/add_filter; dedupe guard (FR-015–017)
    │   └── SubscribesToHooks.php      # interface: hooks(): array — declarative subscription map
    ├── Http/
    │   └── ControllerMap.php          # PSR-4 scan of each module's Controllers/ ; registers bindings (FR-018–020)
    ├── Controllers/                   # core's own controllers location (empty for now; proves discovery)
    └── Support/
        ├── Config/
        │   ├── ConfigInterface.php    # get(key, default), has(key)
        │   ├── Repository.php         # precedence engine: .env → options → defaults (FR-011–013)
        │   └── Sources/
        │       ├── DotenvSource.php   # vlucas/phpdotenv reader; malformed → log + skip (FR-014)
        │       ├── OptionsSource.php  # WP options layer
        │       └── DefaultsSource.php # shipped defaults
        ├── Facades/
        │   ├── Corex.php              # Corex::make() — bounded global accessor (FR-008a)
        │   └── Config.php             # Config::get() (framework doc §3)
        └── BootLogger.php             # FR-023: debug.log always + admin notice when WP_DEBUG

tests/
├── Unit/Foundation/                   # container, providers, config precedence, hook map, discovery (headless)
└── Integration/                       # boot in real WP across contexts; malformed .env admin notice
```

**Structure Decision**: Single plugin (`corex-core`); the layout extends the illustrative tree in
COREX-FRAMEWORK.md §4 with three justified additions — `Foundation/` (Application + ServiceProvider +
ProviderRepository, the Laravel-idiomatic bootstrap/extension layer), `Hooks/` (HookRegistry +
subscriber contract), and `Support/Config/` (the config engine, namespaced to avoid the
`Corex\Config\` plugin collision). These are infrastructure homes, not new business layers.

## Scalability & extensibility design (explicit project requirement)

The foundation is built so that **every future update and add-on extends the framework without
editing core**:

1. **Service Provider is the only extension seam.** A module/add-on ships a `ServiceProvider` with
   `register()` (bind into the container) and `boot()` (wire hooks/run side effects after all
   providers are registered). Core, corex-config, corex-blocks, and third-party add-ons are all just
   providers — uniform mechanism, no special cases.
2. **Providers are discovered, not hard-listed.** `ProviderRepository` collects providers from (a) a
   core providers list, (b) a `config('app.providers')` array, and (c) add-on packages declaring
   providers via Composer `extra.corex.providers` — so installing an add-on auto-registers it. This
   is the durable seam the §14 Extension API builds on.
3. **Depend on interfaces, not concretions.** `ContainerInterface`, `ConfigInterface`,
   `SubscribesToHooks`, and the `ServiceProvider` base are the stable contracts; the league/container
   engine sits behind our wrapper so it can be swapped without breaking consumers.
4. **Convention over configuration.** Controllers are found by location (`Controllers/` per module)
   and hooks by a declared map — adding either requires zero central edits (SC-004, SC-005).
5. **PSR alignment** (PSR-11 container, PSR-4 autoload, PSR-3-style logging contract) keeps the
   framework interoperable with the wider PHP ecosystem as it grows.
6. **Non-fatal, observable boot** means a broken add-on surfaces as a logged notice, not a dead
   site — essential as the number of providers grows.

## Phase 0 — Research

See [research.md](./research.md). All technology choices resolved (container engine, `.env` loader,
provider discovery, testing stack, logging). No open NEEDS CLARIFICATION.

## Phase 1 — Design & Contracts

- [data-model.md](./data-model.md) — the six foundation entities, their fields, relationships, and
  lifecycle (register → boot, resolution, precedence).
- [contracts/foundation-contracts.md](./contracts/foundation-contracts.md) — the PHP public API
  surface each entity exposes (the framework's "interfaces to other systems").
- [quickstart.md](./quickstart.md) — runnable validation scenarios mapping to the spec's acceptance
  criteria and success metrics.

## Complexity Tracking

No constitution violations — section intentionally empty.
