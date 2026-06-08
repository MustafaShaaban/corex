# Feature Specification: corex-core Foundation

**Feature Branch**: `001-corex-core-foundation`

**Created**: 2026-06-08

**Status**: Draft

**Input**: User description: "corex-core foundation — the framework's bootstrap layer. Scope (PROGRESS.md Phase 5): (1) Boot: corex-core self-initializes on plugins_loaded, independent of any theme, working in CLI/REST/admin/cron contexts; (2) PSR-11 DI Container: bind/singleton/resolve with autowiring, the single mechanism for dependency resolution; (3) ControllerMap: auto-discovery of controllers; (4) HookRegistry: declarative registration of WP actions/filters; (5) Config: layered resolution .env → WP options → defaults. This is foundation infrastructure only — no business modules yet. Must honor the constitution: everything injected via the container, no theme dependency, runs fully with no optional plugins (ACF/Woo/Polylang) installed."

## Overview

corex-core is the engine of the Corex framework. This feature delivers the bootstrap layer
that every later module depends on: the plugin must start itself, stand up a dependency-injection
container, load configuration from layered sources, register WordPress hooks declaratively, and
discover the controllers a module exposes. No business behavior ships here — this is the load-bearing
foundation that makes the rest of the framework possible, testable, and theme-independent.

The "users" of this foundation are the developers building Corex modules (and the framework's own
runtime), not site visitors. Success means a module author can rely on a started container, resolved
config, and a hook/controller wiring mechanism without writing bootstrapping boilerplate themselves.

## Clarifications

### Session 2026-06-08

- Q: How are controllers discovered (FR-018)? → A: Directory + PSR-4 namespace scan of a conventional `Controllers/` location per module; no annotations or hand-maintained registry.
- Q: How does the container resolve an interface type-hint to a concrete class? → A: Explicit `Interface → Concrete` bindings registered in a service provider; resolving an unbound interface raises a clear error.
- Q: How is the optional `.env` file parsed? → A: Via the `vlucas/phpdotenv` Composer library (safe/immutable loading), not a hand-written parser.
- Q: Is a global container accessor allowed given Principle IV ("everything injected")? → A: A single global accessor is permitted but documented as framework-boundary/bootstrap use only (hook wiring, CLI); application services and controllers MUST use constructor injection.
- Q: How are boot-time problems (malformed `.env`, resolution errors) surfaced? → A: Always written to the WordPress debug log; additionally shown as a dismissible admin notice only when `WP_DEBUG` is on. Boot stays non-fatal.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - The plugin boots itself and exposes a container (Priority: P1)

A developer activates corex-core in any WordPress context (front-end, admin, REST request, WP-CLI
command, or cron event). With no theme active and no optional plugins installed, the framework
starts itself exactly once and makes a single shared dependency-injection container available to all
subsequent code. The container can register bindings (transient and shared/singleton) and resolve
them, automatically supplying a resolved instance for each constructor dependency.

**Why this priority**: Nothing else in Corex can exist without a started runtime and a working
container — the constitution requires every dependency to be injected through it (Principle IV) and
the engine to boot independently of the theme (Principle II). This is the irreducible MVP.

**Independent Test**: Activate corex-core with the default theme switched off; from a WP-CLI command
and from a front-end request, confirm the framework reports it has booted once and that a service
registered as a singleton resolves to the same instance both times, with its own dependencies
auto-supplied.

**Acceptance Scenarios**:

1. **Given** corex-core is active and no Corex theme is active, **When** WordPress finishes loading
   plugins, **Then** the framework has initialized itself once and a shared container is available.
2. **Given** the framework is booted, **When** the same request triggers loading a second time,
   **Then** initialization does not run again (idempotent boot — no duplicate registrations).
3. **Given** a class is registered as a singleton, **When** it is resolved twice, **Then** the same
   instance is returned both times.
4. **Given** a class is registered as a transient binding, **When** it is resolved twice, **Then** a
   new instance is returned each time.
5. **Given** a class whose constructor type-hints other registered classes, **When** it is resolved,
   **Then** each constructor dependency is resolved from the container automatically (autowiring).
6. **Given** the request is a WP-CLI, REST, admin, or cron context, **When** the framework boots,
   **Then** it initializes correctly in every one of those contexts (not only front-end page loads).

---

### User Story 2 - Configuration resolves from layered sources (Priority: P1)

A developer reads a configuration value by key. The framework resolves it from a defined precedence:
an environment file (`.env`) overrides stored WordPress options, which override framework-shipped
defaults. The value is returned with its expected type, and a missing key returns a caller-supplied
fallback rather than an error.

**Why this priority**: The container and most services need configuration at construction time (e.g.
environment name, feature flags, paths). Layered config is required by the first real services, so it
ships alongside the container as part of the foundation MVP.

**Independent Test**: Define a key in defaults only, then add a WordPress option for it, then add an
`.env` entry for it; confirm the resolved value changes to follow the precedence at each step, and
that an unknown key returns the provided default.

**Acceptance Scenarios**:

1. **Given** a key exists only in shipped defaults, **When** it is read, **Then** the default value
   is returned.
2. **Given** the same key also exists as a stored WordPress option, **When** it is read, **Then** the
   option value is returned instead of the default.
3. **Given** the same key also exists in `.env`, **When** it is read, **Then** the `.env` value wins
   over both the option and the default.
4. **Given** a key that exists in no layer, **When** it is read with a fallback argument, **Then** the
   fallback is returned and no error is raised.
5. **Given** the framework runs with no `.env` file present, **When** any key is read, **Then**
   resolution still works using options and defaults (the `.env` layer is optional).

---

### User Story 3 - Hooks are registered declaratively (Priority: P2)

A developer declares which WordPress actions and filters a class responds to, and the framework wires
each declaration to the corresponding callback at the right time, with the right priority and argument
count — without the developer calling the WordPress hook-registration functions by hand in scattered
places.

**Why this priority**: Declarative hook registration is how every later module attaches behavior to
WordPress. It depends on the booted container (to resolve the objects whose methods are hooked) but is
not needed to prove the container itself works, so it follows the P1 core.

**Independent Test**: Declare a class that maps one action and one filter to its methods, register it
through the framework, then trigger the action and apply the filter; confirm both callbacks run with
the declared priority and receive the declared number of arguments.

**Acceptance Scenarios**:

1. **Given** a class declares an action-to-method mapping, **When** that action fires, **Then** the
   mapped method runs.
2. **Given** a class declares a filter-to-method mapping with a priority and argument count, **When**
   that filter is applied, **Then** the mapped method runs at the declared priority and receives the
   declared number of arguments.
3. **Given** the object whose method is hooked has its own dependencies, **When** the hook fires,
   **Then** the object is resolved from the container (its dependencies injected), not constructed by
   hand.
4. **Given** the same declaration is registered twice, **When** the hook fires, **Then** the callback
   is not double-registered.

---

### User Story 4 - Controllers are discovered automatically (Priority: P3)

A developer adds a controller to a module by placing it where the framework expects controllers to
live, and the framework discovers it and makes it resolvable through the container — without the
developer maintaining a central manual list of every controller.

**Why this priority**: Auto-discovery is a developer-experience accelerator that builds on the
container and hook layers. The framework is fully functional with manual registration; discovery
removes boilerplate. It is therefore the last slice of the foundation.

**Independent Test**: Place a new controller in the conventional controllers location, boot the
framework, and confirm the controller is known to the framework and resolvable from the container
without having been listed anywhere by hand.

**Acceptance Scenarios**:

1. **Given** a controller placed in the conventional location, **When** the framework boots, **Then**
   the controller is discovered and is resolvable from the container.
2. **Given** a file in the controllers location that is not a controller, **When** discovery runs,
   **Then** it is ignored (no error, not registered as a controller).
3. **Given** no controllers exist yet, **When** discovery runs, **Then** the framework boots normally
   with an empty controller set.

---

### Edge Cases

- **Unresolvable dependency**: resolving a class whose constructor requires an unregistered,
  non-autowireable parameter (e.g. an unhinted scalar) raises a clear, actionable error naming the
  class and the parameter — never a silent null.
- **Circular dependency**: resolving A→B→A is detected and reported as a circular-dependency error,
  rather than recursing until the process exhausts memory.
- **Boot before WordPress is ready**: if framework code is reached before the expected boot point,
  the framework still initializes exactly once and does not register hooks twice.
- **Malformed `.env`**: a syntactically invalid `.env` line does not crash boot; config falls back to
  options/defaults and the problem is surfaced (logged), not swallowed silently.
- **Unknown config key with no fallback**: reading a missing key without a fallback returns a null/
  empty result by the documented contract, not a fatal error.
- **Optional plugin absent**: with ACF, WooCommerce, Polylang, and WPML all uninstalled, every part
  of the foundation still loads and operates (no hard dependency).
- **Multiple activation contexts in one lifecycle** (e.g. cron firing during an admin request): the
  single-boot guarantee holds across the lifecycle.

## Requirements *(mandatory)*

### Functional Requirements

**Boot**

- **FR-001**: corex-core MUST self-initialize at the standard WordPress plugin-load point, without
  requiring any theme to be active.
- **FR-002**: The framework MUST initialize exactly once per request lifecycle (idempotent boot); a
  second trigger MUST NOT re-run registration.
- **FR-003**: The framework MUST boot correctly in front-end, admin, REST, WP-CLI, and cron contexts.
- **FR-004**: The framework MUST operate fully with none of the optional integrations (ACF,
  WooCommerce, Polylang, WPML) installed.

**Dependency-Injection Container**

- **FR-005**: The framework MUST expose a single shared container that complies with the PSR-11
  container interface (`get`/`has` contract).
- **FR-006**: The container MUST support registering a transient binding (new instance per resolution)
  and a shared/singleton binding (one instance reused for the lifecycle).
- **FR-007**: The container MUST autowire constructor dependencies — when resolving a class, each
  type-hinted constructor parameter is resolved from the container automatically.
- **FR-007a**: When a constructor type-hints an interface, the container MUST resolve it via an
  explicit `Interface → Concrete` binding (registered in a service provider); an unbound interface
  MUST raise a clear error naming the interface, not return null.
- **FR-008**: The container MUST be the single mechanism for dependency resolution in the framework;
  foundation code MUST NOT construct its own dependencies inline (per constitution Principle IV).
- **FR-008a**: A single global container accessor MAY exist for framework-boundary use only (hook
  wiring, WP-CLI/cron bootstrap where constructor injection cannot reach). Application services and
  controllers MUST NOT use it — they MUST receive dependencies via constructor injection. The
  accessor's documentation MUST state this boundary.
- **FR-009**: Resolving an unregistered, non-autowireable dependency MUST raise a clear error that
  names the class and the offending parameter.
- **FR-010**: The container MUST detect a circular dependency and raise a circular-dependency error
  rather than recursing unboundedly.

**Configuration**

- **FR-011**: The framework MUST resolve a configuration value by key using the precedence
  `.env` → WordPress options → shipped defaults (first match wins).
- **FR-012**: Reading a missing key MUST return a caller-supplied fallback (or a documented empty
  result when none is supplied), never a fatal error.
- **FR-013**: The `.env` layer MUST be optional — absence of a `.env` file MUST NOT break config
  resolution.
- **FR-014**: A malformed `.env` entry MUST NOT crash boot; the framework MUST fall back to the lower
  layers and surface the problem per FR-023.

**Observability & error surfacing**

- **FR-023**: Boot-time problems (malformed `.env`, unresolvable/circular dependencies, discovery
  errors) MUST be written to the WordPress debug log every time, and additionally shown as a
  dismissible admin notice only when `WP_DEBUG` is enabled. Boot MUST remain non-fatal (no white
  screen) — surfacing a problem never replaces continuing with the lower config layers where possible.

**Hook Registry**

- **FR-015**: The framework MUST let a class declare its WordPress action and filter subscriptions
  (hook name → method, with priority and accepted-argument count) and wire them on its behalf.
- **FR-016**: Hooked objects MUST be resolved from the container so their own dependencies are
  injected (no hand-construction).
- **FR-017**: Registering the same declaration more than once MUST NOT result in a double-registered
  callback.

**Controller Discovery**

- **FR-018**: The framework MUST auto-discover controllers by scanning a conventional `Controllers/`
  directory per module and resolving their PSR-4 namespace, making each resolvable from the container,
  without a hand-maintained central list or per-class annotation.
- **FR-019**: Non-controller files in the controllers location MUST be ignored by discovery without
  error.
- **FR-020**: An empty controller set MUST NOT prevent the framework from booting.

**Cross-cutting**

- **FR-021**: No part of the foundation may contain business logic, CPT/taxonomy registration, or
  presentation; it is infrastructure only (per constitution Principles I & III).
- **FR-022**: Every foundation behavior MUST be exercisable in automated tests without a browser and
  without optional plugins present.

### Key Entities

- **Boot/Kernel**: the single entry point that starts the framework once, builds the container, and
  triggers config load, hook registration, and controller discovery.
- **Container**: holds bindings (transient/singleton) and resolves them with autowiring; the sole
  source of dependency resolution.
- **Config**: a keyed accessor over the layered sources (`.env`, options, defaults) with a fallback
  contract.
- **HookRegistry**: the mapping of declared hook subscriptions (hook name, target method, priority,
  argument count) to live WordPress registrations.
- **ControllerMap**: the discovered set of controllers and their resolvable identities.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: With no theme active and no optional plugins installed, corex-core boots with zero PHP
  fatals or warnings in front-end, admin, REST, WP-CLI, and cron contexts (5/5 contexts clean).
- **SC-002**: A developer can register and resolve a dependency-injected service in **5 lines or
  fewer**, with its dependencies auto-supplied and no manual `new` of those dependencies.
- **SC-003**: A configuration key resolves to the correct layer in **100%** of the precedence
  combinations (default-only, option-over-default, env-over-option, missing-with-fallback).
- **SC-004**: A developer can subscribe a class to a WordPress action or filter declaratively in **a
  single declaration** (no scattered hook-function calls), and the callback fires with the declared
  priority and argument count.
- **SC-005**: Adding a new controller requires **zero edits to any central registry** — placing the
  file in the conventional location is sufficient for it to be discovered and resolvable.
- **SC-006**: Booting the framework twice within one request produces **exactly one** set of
  registrations (no duplicates).
- **SC-007**: Every foundation behavior above is covered by an automated test that runs headlessly and
  passes with all optional plugins absent.
- **SC-008**: Given a deliberately malformed `.env`, the site still boots (no fatal/white screen),
  the problem appears in the debug log, and — with `WP_DEBUG` on — a single admin notice is shown.

## Assumptions

- **Audience**: the consumers of this foundation are Corex module developers and the framework's own
  runtime; there is no end-user-facing UI in this feature.
- **Container standard**: PSR-11 is the container contract (named in the framework's own scope and
  constitution Principle IV); a Laravel-inspired bind/singleton/resolve surface is expected on top of
  the PSR-11 `get`/`has` minimum.
- **Controller discovery strategy**: discovery is convention-based (controllers live in a known
  location/namespace per module) rather than requiring a hand-maintained manifest; the precise
  convention (directory scan vs. namespace mapping vs. attribute marker) is a planning-phase decision.
- **Config sources**: the three layers are an optional `.env` file at the project root, WordPress
  options, and framework-shipped default values; secrets belong in `.env`, not in committed defaults.
  The `.env` file is parsed with `vlucas/phpdotenv`; a malformed file is caught and logged rather
  than allowed to crash boot (FR-014).
- **Boot point**: "self-initialize" means hooking the framework's start to the standard WordPress
  plugin-load lifecycle so it runs in every execution context, not only HTTP page loads.
- **Scope boundary**: Model/Field driver, QueryBuilder, CLI generators, blocks, middleware/security,
  theme tokens, forms, and all add-ons are explicitly **out of scope** for this feature (later phases
  per PROGRESS.md and COREX-SPECKIT-START.md).
- **Persistence**: this feature introduces no custom database tables; configuration uses existing
  WordPress options storage.
- **Environment**: a working WordPress install (≥ 7.0) with the monorepo mapped into `wp-content/`
  already exists (Environment Gate satisfied); this feature is developed against it.
