# Phase 1 Data Model: corex-core Foundation

This feature has no persisted database entities. The "entities" are the runtime objects of the
bootstrap layer and their relationships/lifecycle. Each maps to a spec Key Entity and FRs.

## Entity map

```text
Boot (static)
  └─ builds ──> Application
                  ├─ owns ──> Container ──(resolves)──> any class
                  ├─ uses ──> ProviderRepository ──(collects)──> ServiceProvider[]
                  │                                   register() → boot()
                  ├─ exposes ──> Config (Repository over Sources[])
                  ├─ uses ──> HookRegistry ──(wires)──> SubscribesToHooks subscribers
                  └─ uses ──> ControllerMap ──(discovers)──> Controller bindings
BootLogger ── records problems from any of the above
```

## 1. Boot  *(spec: Boot/Kernel; FR-001..FR-004)*

- **Role**: the single static entry point invoked from `corex-core.php`.
- **State**: `private static bool $booted` (idempotency guard); `private static ?Application $app`.
- **Behavior**: `init()` hooks `boot()` onto `plugins_loaded`; `boot()` is a no-op if `$booted`.
- **Relationships**: creates exactly one `Application`.
- **Invariants**: boots once per request (FR-002, SC-006); works in all 5 contexts (FR-003).

## 2. Application  *(spec: Boot/Kernel; FR-004, FR-008a)*

- **Role**: orchestrates the boot sequence and holds the container.
- **Fields**: `Container $container`; `ProviderRepository $providers`; `bool $booted`.
- **Behavior**: build container → register config + core bindings → collect providers →
  `register()` all → `boot()` all (which triggers hook wiring + controller discovery).
- **Relationships**: owns `Container`; drives `ProviderRepository`, `HookRegistry`, `ControllerMap`.
- **Invariants**: never references a theme or optional plugin (Principles I, II, IX).

## 3. Container  *(spec: Container; FR-005..FR-010, FR-007a)*

- **Role**: the sole dependency-resolution mechanism.
- **Conforms to**: `Psr\Container\ContainerInterface` (`get`, `has`) plus the Corex surface
  `bind`, `singleton`, `instance`, `make`, `tag` (see contracts).
- **Fields (conceptual)**: binding registry (id → factory/concrete), shared-instance cache,
  in-progress resolution stack (for circular detection).
- **Behavior**:
  - `bind(id, concrete)` → transient (new each `make`), `singleton(id, concrete)` → one instance.
  - `make(id)` autowires constructor type-hints (FR-007); interface hints require an explicit
    `Interface → Concrete` binding (FR-007a).
  - Unresolvable/unhinted-scalar param → `BindingResolutionException` naming class + param (FR-009).
  - Resolution cycle → `CircularDependencyException` (FR-010).
- **Invariants**: a `singleton` returns the same instance every time (US1 AS3); a `bind` returns a
  fresh instance every time (US1 AS4).

## 4. ServiceProvider + ProviderRepository  *(extension seam; supports FR-004, scalability)*

- **ServiceProvider (abstract)**:
  - `register(Container $c): void` — bind services; MUST NOT resolve/boot side effects here.
  - `boot(): void` — run after all providers registered; may wire hooks, read config.
  - optional `array $provides` (tag) and ordering hints.
- **ProviderRepository**:
  - **Fields**: `string[] $providerClasses` (deduped, ordered).
  - **Sources**: core list + `config('app.providers')` + Composer `extra.corex.providers`.
  - **Behavior**: instantiate each provider via the container; two-pass `register()` then `boot()`.
- **Invariants**: registering the same provider twice has no extra effect; a provider that throws is
  caught, logged (FR-023), and does not abort boot.

## 5. Config (Repository + Sources)  *(spec: Config; FR-011..FR-014)*

- **Role**: keyed read access over layered sources.
- **Conforms to**: `Corex\Support\Config\ConfigInterface` (`get(key, default = null)`, `has(key)`).
- **Sources (precedence high→low)**: `DotenvSource` → `OptionsSource` → `DefaultsSource`.
- **Fields**: ordered `Source[]`; each `Source` answers `has(key)` / `get(key)`.
- **Behavior**: `get` returns the first source that `has` the key; else the caller default (FR-012).
  Missing/absent `.env` is fine (FR-013); malformed `.env` → source yields nothing + `BootLogger`
  records it (FR-014).
- **Key format**: dot notation (`app.providers`, `app.env`); resolution is per top-level key with
  optional nested array access in defaults.
- **Invariants**: precedence holds in 100% of combinations (SC-003); never fatal on a missing key.

## 6. HookRegistry + SubscribesToHooks  *(spec: HookRegistry; FR-015..FR-017)*

- **SubscribesToHooks (interface)**: `hooks(): array` returning
  `['hook_name' => 'method']` or `['hook_name' => ['method' => string, 'priority' => int, 'args' => int]]`.
- **HookRegistry**:
  - **Fields**: `array<string,bool> $wired` (dedupe keys `FQCN::method@hook`).
  - **Behavior**: for each subscriber class, resolve it from the container (FR-016), read `hooks()`,
    and `add_action`/`add_filter` each entry at its priority/arg-count (default 10/1); skip already
    wired keys (FR-017).
- **Invariants**: a declared action/filter fires the mapped method with the declared priority/args
  (US3 AS1, AS2); double registration does not double-fire (US3 AS4, SC-004).

## 7. ControllerMap  *(spec: ControllerMap; FR-018..FR-020)*

- **Role**: convention-based controller discovery.
- **Fields**: `string[] $controllers` (discovered FQCNs); per-module `[namespacePrefix => dir]`.
- **Behavior**: scan each module's `Controllers/` dir, map to FQCN via PSR-4, keep only instantiable
  classes (skip abstract/interface/trait/non-class — FR-019), register each as a container binding.
- **Invariants**: zero central edits to add a controller (SC-005); empty set still boots (FR-020).

## 8. BootLogger  *(spec: Observability; FR-023, SC-008)*

- **Role**: record boot-time problems.
- **Behavior**: always `error_log` (respecting `WP_DEBUG_LOG`); when `WP_DEBUG`, enqueue one
  dismissible `admin_notices` message (escaped, i18n, `manage_options`-gated). Never aborts boot.
- **Surface**: PSR-3-shaped (`warning()`, `error()`) so it can delegate to a full logger later.

## Lifecycle (happy path)

1. `plugins_loaded` fires → `Boot::boot()` (guarded) → new `Application`.
2. Application builds `Container`; binds `ConfigInterface` (Repository + Sources), `BootLogger`,
   `HookRegistry`, `ControllerMap`, and the `Corex`/`Config` facades' accessor.
3. `ProviderRepository` collects providers (core + config + add-ons), `register()` all.
4. `boot()` all providers → providers wire their `SubscribesToHooks` classes via `HookRegistry` and
   trigger `ControllerMap` discovery for their module.
5. Framework is ready; `Corex::make()` / `Config::get()` available at framework boundaries.

## Error paths

| Trigger | Handling | FR |
|---|---|---|
| Unhinted scalar / unbound concrete | `BindingResolutionException` (names class+param) | FR-009 |
| Resolution cycle A→B→A | `CircularDependencyException` | FR-010 |
| Unbound interface hint | `BindingResolutionException` (names interface) | FR-007a |
| Missing `.env` | ignored; lower layers serve | FR-013 |
| Malformed `.env` | source yields nothing; logged; boot continues | FR-014, FR-023 |
| Missing config key | caller default / null | FR-012 |
| Provider throws on boot | caught, logged, boot continues | FR-023 |
| Non-controller file in `Controllers/` | skipped silently | FR-019 |
