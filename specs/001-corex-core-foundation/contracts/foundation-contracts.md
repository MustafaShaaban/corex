# Phase 1 Contracts: corex-core Foundation

The framework's "interfaces to other systems" are the PHP contracts module/add-on authors program
against. These are the **stable public API**; implementations may change behind them. Signatures are
the agreed shape — implementation lives in `tasks.md`/the implementation phase, not here.

## C1 — Container

```php
namespace Corex\Container;

interface ContainerInterface extends \Psr\Container\ContainerInterface
{
    // PSR-11: get(string $id): mixed ; has(string $id): bool

    /** Transient binding: a new instance per make(). $concrete = class-string|callable|null. */
    public function bind(string $id, callable|string|null $concrete = null): void;

    /** Shared binding: one instance reused for the request lifecycle. */
    public function singleton(string $id, callable|string|null $concrete = null): void;

    /** Register an already-built instance as a shared binding. */
    public function instance(string $id, object $instance): object;

    /** Resolve with autowiring. $parameters overrides specific constructor args by name. */
    public function make(string $id, array $parameters = []): mixed;

    /** Group bindings under a tag for batch resolution (extension/scale seam). */
    public function tag(string|array $ids, string $tag): void;

    /** @return iterable<object> all instances registered under a tag. */
    public function tagged(string $tag): iterable;
}
```

**Errors**: `Corex\Container\Exceptions\BindingResolutionException` (unresolvable id, unhinted scalar,
or unbound interface — message names the class and the offending parameter/interface, FR-007a/FR-009);
`Corex\Container\Exceptions\CircularDependencyException` (resolution cycle, FR-010). Both extend a
`ContainerException implements Psr\Container\ContainerExceptionInterface`.

## C2 — Config

```php
namespace Corex\Support\Config;

interface ConfigInterface
{
    /** First source (.env → options → defaults) that has $key wins; else $default. Never throws. */
    public function get(string $key, mixed $default = null): mixed;

    public function has(string $key): bool;
}

interface Source
{
    public function has(string $key): bool;
    public function get(string $key): mixed; // only called when has() is true
}
```

Sources, highest precedence first: `Sources\DotenvSource`, `Sources\OptionsSource`,
`Sources\DefaultsSource`. `DotenvSource` swallows a malformed file (logs via `BootLogger`) and
behaves as empty (FR-013/FR-014).

**Facade** (framework boundary only): `Corex\Support\Facades\Config::get($key, $default)`,
`Config::has($key)`.

## C3 — Service Provider (the extension seam)

```php
namespace Corex\Foundation;

abstract class ServiceProvider
{
    public function __construct(protected ContainerInterface $container) {}

    /** Bind services into the container. MUST NOT trigger side effects or resolve hooks. */
    abstract public function register(): void;

    /** Run after ALL providers are registered: wire hooks, discover controllers, read config. */
    public function boot(): void {}

    /** Optional: hook-subscriber classes this provider contributes (passed to HookRegistry). */
    public function subscribers(): array { return []; }

    /** Optional: module namespace→dir map enabling controller discovery for this module. */
    public function controllerPaths(): array { return []; }
}
```

**Discovery order** (`ProviderRepository`): core providers → `config('app.providers')` → Composer
`extra.corex.providers`. Deduped by class-string; two-pass `register()` then `boot()`. A provider
throwing is caught + logged; boot continues (FR-023).

## C4 — Hook subscription

```php
namespace Corex\Hooks;

interface SubscribesToHooks
{
    /**
     * @return array<string, string|array{0:string,1?:int,2?:int}>
     *   'hook_name' => 'method'
     *   'hook_name' => ['method', $priority = 10, $accepted_args = 1]
     */
    public function hooks(): array;
}
```

`HookRegistry::register(string $subscriberClass): void` resolves the subscriber from the container
(FR-016), wires each entry via `add_action`/`add_filter`, and skips any `FQCN::method@hook` already
wired (FR-017).

## C5 — Controller discovery contract

- **Convention**: a controller is any **instantiable class** located in a module's `Controllers/`
  directory, resolved to its FQCN via that module's PSR-4 prefix. No attribute, no required base
  class (clarified decision).
- `ControllerMap::discover(array $namespaceToDir): string[]` returns discovered FQCNs and registers
  each as a container binding. Abstract/interface/trait/non-class files are skipped (FR-019); an empty
  result is valid (FR-020).

## C6 — Boot entry

```php
namespace Corex;

final class Boot
{
    /** Called once from corex-core.php; hooks bootstrap to plugins_loaded. Idempotent. */
    public static function init(): void;

    /** @internal The booted Application (used by the bounded Corex facade). */
    public static function app(): \Corex\Foundation\Application;
}
```

**Bounded global accessor** (FR-008a — framework boundary only, NOT for app services/controllers):
`Corex\Support\Facades\Corex::make(string $id, array $parameters = []): mixed`.

## Contract test matrix

Each contract maps to acceptance criteria; these become failing tests first (TDD), per task.

| Contract | Asserts | Spec ref |
|---|---|---|
| C1 `singleton` vs `bind` | same vs new instance | US1 AS3/AS4 |
| C1 `make` autowiring | constructor deps auto-supplied | US1 AS5, FR-007 |
| C1 interface hint unbound | `BindingResolutionException` names interface | FR-007a |
| C1 unhinted scalar | `BindingResolutionException` names class+param | FR-009 |
| C1 cycle | `CircularDependencyException` | FR-010 |
| C2 precedence | env>option>default; missing→default | US2 AS1–5, SC-003 |
| C2 malformed `.env` | empty source + logged, boot ok | FR-014, SC-008 |
| C3 providers | register-all then boot-all; dedupe; throw→logged | FR-004, FR-023 |
| C4 hooks | mapped method fires at priority/args; no double-wire | US3, SC-004 |
| C5 discovery | controller found+resolvable; non-controller skipped; empty ok | US4, SC-005 |
| C6 boot | one boot per request across 5 contexts | US1, SC-001, SC-006 |
