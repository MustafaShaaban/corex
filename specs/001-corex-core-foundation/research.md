# Phase 0 Research: corex-core Foundation

All decisions below resolve the Technical Context. No NEEDS CLARIFICATION remain.

## R1 — DI container engine  *(REVISED 2026-06-08 during T011 — see DECISIONS #21)*

**Decision**: A **focused custom container** `Corex\Container\Container` (PHP Reflection, ~130 lines)
implementing `Corex\Container\ContainerInterface extends Psr\Container\ContainerInterface`. We keep
`psr/container` (the PSR-11 interface, for interop) but take **no third-party container engine**.

**Why the reversal** (the original plan picked `league/container`): reading the installed source
showed league 4.x **does not detect circular dependencies** (its `ReflectionContainer` recurses until
the process dies) and its `has()` cannot distinguish an explicit binding from any autowirable class —
so cycle detection (FR-010) and precise unbound-interface/unhinted-scalar messages (FR-007a/FR-009)
could not be layered on cleanly. Coordinating our own autowiring with league's delegate proved
fragile. A small custom container gives total control over resolution, cycle detection, and error
messages for ~130 owned, fully-tested lines — and Laravel itself ships a custom container.

**Rationale**:
- We own the resolution stack → FR-010 (circular) is a clean exception, not a stack overflow.
- We own the reflection path → FR-007a (unbound interface names the interface) and FR-009 (unhinted
  scalar names class + param) produce exact, actionable messages.
- Still PSR-11 (FR-005); the public contract is unchanged, so this is an internal engine swap only.
- Zero runtime container dependency to track/upgrade.

**Alternatives considered**:
- `league/container` — chosen first, then rejected (no cycle detection; fragile autowiring coordination).
- `illuminate/container` (Laravel's, standalone) — drags `illuminate/*` contracts + heavier coupling.
- `php-di/php-di` — capable but heavier than a foundation needs; more surface than we use.

**Notes for implementation**: bindings (transient/shared) + a shared-instance cache + a `building`
resolution stack; constructor autowiring via `ReflectionClass`; `BindingResolutionException`,
`CircularDependencyException`, `EntryNotFoundException` (the last implements PSR-11
`NotFoundExceptionInterface`). `tag`/`tagged` were dropped from the interface for now (YAGNI — no
caller yet; add with their first real use, e.g. middleware/events).

## R2 — `.env` loader

**Decision**: `vlucas/phpdotenv` with **safe/immutable** loading (does not mutate `$_ENV`/`getenv`
globally; values are read into our Config repository).

**Rationale**: De-facto standard (Laravel's choice), robust quoting/escaping, and a documented way to
load without throwing on a missing file — directly satisfying FR-013 (optional) and FR-014 (malformed
must not crash). Decided in the spec Clarifications session.

**Alternatives considered**: `symfony/dotenv` (fine, different API/footprint); hand-written parser
(rejected — we would own every parsing edge case).

**Notes**: A malformed `.env` is caught at the `DotenvSource` boundary; the source yields no values
and `BootLogger` records the problem (FR-014 → FR-023). Lower layers (options, defaults) still serve.

## R3 — Provider discovery (the scalability seam)

**Decision**: `ProviderRepository` aggregates service providers from three sources, in this order:
1. a hard-coded **core providers** list (the foundation's own providers),
2. `config('app.providers')` (site/integration-level providers),
3. **add-on packages** that declare providers in their `composer.json` under
   `extra.corex.providers` (read from the root Composer `installed.json`).

Providers are de-duplicated by class name; `register()` runs for all, then `boot()` runs for all
(two-pass) so a provider may depend on another provider's bindings during boot.

**Rationale**: Installing an add-on (a Composer package) then auto-registers it — no core edits, no
central list to maintain. This is the §14 Extension API's durable mechanism and the single biggest
lever for "highly scalable for upcoming add-ons."

**Alternatives considered**: WordPress plugin-header scanning (rejected — couples discovery to WP
plugin packaging, not Composer packages, and misses non-plugin packages); a hand-maintained registry
(rejected — violates SC-005's spirit and does not scale).

## R4 — Boot timing & idempotency

**Decision**: `Corex\Boot::init()` is called from `corex-core.php` and hooks the framework's
bootstrap to `plugins_loaded` (default priority 10). A private static `bool $booted` guard makes a
second call a no-op (FR-002, SC-006). The Application is built once and stored statically; the bounded
`Corex::make()` facade reads it.

**Rationale**: `plugins_loaded` runs in every context (front-end, admin, REST, WP-CLI, cron) before
`init`, giving the framework a uniform, theme-independent start (Principle II, FR-001, FR-003).

**Alternatives considered**: booting on `init` (too late for some hooks; rejected); booting at file
include time (before WP APIs/options are reliably available; rejected).

## R5 — Hook wiring model

**Decision**: A class implements `SubscribesToHooks::hooks(): array` returning a map of
`hook => [method, priority, accepted_args]` (priority/args optional, sensible defaults). The
`HookRegistry` resolves the subscriber **from the container** (so dependencies inject, FR-016) and
registers each entry with `add_action`/`add_filter`. A per-registry set of already-wired
`(class::method @ hook)` keys prevents double registration (FR-017).

**Rationale**: One declarative surface, container-resolved, dedupe-safe — satisfies FR-015–017 and
SC-004 while staying trivially testable with Brain Monkey.

**Alternatives considered**: PHP-8 attribute-based hook tags (more magic, harder to grep; deferred —
can be layered on later without breaking the interface).

## R6 — Controller discovery

**Decision**: `ControllerMap` scans each registered module's `Controllers/` directory, maps file →
FQCN via the module's PSR-4 prefix, and registers each **instantiable** class as a container binding.
Abstracts, interfaces, traits, and non-class files are skipped (FR-019). An empty set is valid
(FR-020). Discovery results are computed once per boot.

**Rationale**: Convention-based (the clarified decision), zero annotations, zero central list
(SC-005). PSR-4 mapping means it works identically for core and every add-on.

**Alternatives considered**: attribute marker / base-class filter (the clarify session chose plain
directory + PSR-4 scan); a generated manifest (rejected — adds a build step).

**Notes**: "instantiable class in a `Controllers/` namespace" is the filter; no `#[Controller]`
attribute and no required base class, per the clarification.

## R7 — Logging & error surfacing

**Decision**: `BootLogger` writes every boot-time problem to the WordPress debug log
(`error_log`, honoring `WP_DEBUG_LOG`), and, **only when `WP_DEBUG` is true**, queues a single
dismissible `admin_notices` message (escaped, translatable, capability-gated to `manage_options`).
Boot never aborts on a logged problem (FR-023, SC-008). The logger exposes a small PSR-3-shaped
method surface so it can later delegate to a full PSR-3 logger.

**Rationale**: Quiet and non-fatal in production, visible in development — the exact behavior chosen
in the spec Clarifications session.

**Alternatives considered**: throw-on-failure (rejected — conflicts with non-fatal boot); always-on
admin notice (rejected — noisy in production).

## R8 — Testing stack

**Decision**: **Pest** (on PHPUnit) for all PHP tests. Headless **unit** tests use **Brain Monkey**
to stub WordPress functions (`add_action`, `get_option`, etc.) so the container/config/hook/discovery
logic is provable with no WP bootstrap and no optional plugins (FR-022, SC-007). A small
**integration** suite boots the framework inside the real `./wp` install to assert the 5/5 contexts
clean (SC-001) and the malformed-`.env` admin-notice path (SC-008).

**Rationale**: Matches COREX-FRAMEWORK.md §test stack (Pest + Brain Monkey); unit-first keeps the
suite fast and CI-portable; the thin integration layer covers what stubs cannot (real context
detection, real admin notice).

**Alternatives considered**: WP_Mock (similar to Brain Monkey; Brain Monkey chosen for Pest
ergonomics); full WP integration only (rejected — slow, hides logic regressions).

## Resolved dependencies summary

| Concern | Choice |
|---|---|
| Container engine | `league/container` behind `Corex\Container\Container` |
| PSR-11 contract | `psr/container` |
| `.env` parsing | `vlucas/phpdotenv` (safe/immutable) |
| Provider discovery | core list + `config('app.providers')` + Composer `extra.corex.providers` |
| Boot | `plugins_loaded`, static idempotency guard |
| Hooks | `SubscribesToHooks` map + `HookRegistry`, container-resolved, dedupe |
| Controllers | PSR-4 `Controllers/` scan, instantiable-class filter |
| Logging | `BootLogger`: debug.log always + `WP_DEBUG` admin notice |
| Tests | Pest + Brain Monkey (unit) + thin WP integration |

These add three runtime Composer requires to `plugins/corex-core/composer.json` and the root
`composer.json`: `psr/container`, `league/container`, `vlucas/phpdotenv` (plus `pestphp/pest`,
`brain/monkey` as dev deps).
