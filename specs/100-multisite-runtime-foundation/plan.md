# Implementation Plan: The network CoreX has been claiming to support

**Spec**: [spec.md](spec.md) · **Branch**: `spec/100-multisite-runtime-foundation`

## Constitution Check

| Principle | How this change satisfies it |
|---|---|
| II plugins boot themselves | The contexts are built by `Boot` itself, so they answer in CLI, REST, admin and cron alike — including before the container exists. |
| III thin controllers / fat services | `SiteMigrationRunner` holds the migration logic; the CLI command added here is a printer. Repositories keep owning data access. |
| IV everything injected | Contexts, scope, inspector, migrator, version store and registry are all container-resolved. The only direct construction is in `Boot`/`Application`, which are the composition root and already exempt. |
| VII security declarative | Network operations gate on a network capability through an injected checker (FR-042, FR-043); nothing hand-rolls `is_super_admin()`. |
| IX no optional dependency is hard | Everything lands in `corex-core`. Nothing here requires `addons/corex-multisite` (spec 101), and single-site installs gain no dependency. |
| X spec is source of truth | This spec precedes the code. The cookbook it corrects is amended in the same change (FR-046). |

## The architecture, in one sentence

**Never capture; where you memoize, subscribe to invalidation.**

## Why not rebuild the container per blog

The obvious design — key `Boot::$app` by blog id, or give the container a child scope — is wrong
here, on three counts:

1. `ProviderRepository::load()` runs `register()` then `boot()`, and `boot()` calls
   `add_action`/`add_filter`. Rebuilding per blog **re-registers the entire hook graph**, so one
   `switch_to_blog()` duplicates every CoreX hook. `HookRegistry` dedupes within one instance, and a
   new `Application` gets a new one.
2. WordPress fires `switch_blog` on the switch **and** on the restore. A loop over 200 sites would be
   400 full 13-provider register+boot cycles, each re-globbing `config/*.php` and re-scanning the
   controller directory.
3. `ConfigServiceProvider::boot()` installs schema. Rebuilding on switch would attempt a schema
   install on every switch.

`Application::$container` is `readonly` and `Container` has no `forget()`. Adding one would widen a
deliberately minimal PSR-11 surface with a method whose only legitimate caller is this layer, and
invite "just forget it" as a general escape hatch.

The actual defect surface is about five singleton factories that capture a value, plus the boot-time
activation read. Sizing the mechanism to that is the whole design.

## Files

```
plugins/corex-core/src/Multisite/
  MultisiteContext.php                interface: enabled/subdomainInstall/isNetworkAdmin/isSwitched
  SiteContext.php                     interface: id/networkId/isMainSite/url
  NetworkContext.php                  interface: id/mainSiteId/siteCount/siteIds/exists
  NetworkCapabilities.php             interface: currentUserIsSuperAdmin/currentUserCan
  SingleSiteMultisiteContext.php      constants, zero WP calls
  SingleSiteContext.php               constants, zero WP calls
  SingleSiteNetworkContext.php        constants, zero WP calls
  SingleSiteCapabilities.php          delegates to current_user_can only
  WpMultisiteContext.php              WpSiteContext.php  WpNetworkContext.php  WpNetworkCapabilities.php
  RuntimeContexts.php                 detect() + seedInto(Container)
  SiteScoped.php                      interface: forgetSiteState(int $siteId): void
  SiteScope.php                       interface: currentSiteId/register/run
  SiteScopeManager.php                multisite impl; hooks switch_blog at priority 0
  SingleSiteScope.php                 single-site impl; run() is a direct call
  ActivationScope.php                 enum None|Site|Network|MustUse
  PluginActivationInspector.php       interface
  WpPluginActivationInspector.php     implements PluginActivationInspector, SiteScoped
  MultisiteServiceProvider.php        wires listen(), the lifecycle subscriber, self-heal
  SiteLifecycleSubscriber.php         wp_initialize_site / wp_delete_site / wpmu_drop_tables
  Events/SiteCreated.php  SiteSchemaInstalled.php  SiteMigrated.php
  Events/SiteMigrationFailed.php  SiteDeleting.php  SiteDeleted.php  SiteScopeSwitched.php

plugins/corex-core/src/Foundation/
  Boot.php                            activePlugins() deleted; runtimeState(RuntimeContexts)
  Application.php                     optional ?RuntimeContexts ctor param, seeded in boot()
  AddonProviderResolver.php           two-pass fixpoint
  AddonProviderResolution.php         + exclusions()/scopes(), reasonFor() kept
  AddonExclusion.php                  NEW  slug/reason/scope/siteId/detail + message()
  AddonBlockReason.php                NEW  enum
  AddonRuntimeState.php               + activationScopes, scopeOf()

plugins/corex-core/src/Support/Config/
  SettingScope.php  SettingDefinition.php  SettingRegistry.php     NEW
  Truthy.php                                                       NEW
  Sources/NetworkLockSource.php  Sources/NetworkDefaultsSource.php NEW
  ../Foundation/CoreServiceProvider.php                            five-source list

plugins/corex-core/src/Database/Schema/
  SchemaMigrator.php          NEW interface; Migrator implements it, body unchanged
  SchemaComponent.php  SchemaRegistry.php                          NEW
  SchemaVersionStore.php  WpOptionSchemaVersionStore.php           NEW
  SiteMigrationRunner.php  SiteMigrationResult.php
  NetworkMigrationResult.php  SiteMigrationOutcome.php             NEW
  SchemaSelfHeal.php                                               NEW

plugins/corex-core/src/Events/ListenerProvider.php                 + hasListenersFor()
plugins/corex-core/src/Jobs/JobRunner.php                          site-scoped execution
plugins/corex-config/src/ConfigServiceProvider.php                 registers a SchemaComponent
plugins/corex-config/src/Notifications/WpNotificationPreferenceStore.php  blog-prefixed meta key
plugins/corex-config/src/{Addons/AddonManager,Settings/AdminDashboard,Overview/OverviewRenderer,
  Access/AccessScreen,Forms/FormsFlowsScreen,Email/EmailStudioScreen}.php   inspector
plugins/corex-blocks/src/NewsletterSignupRenderer.php                      inspector
packages/cli/src/Commands/ResetCommand.php                                 inspector + network guard
packages/cli/src/Commands/MigrateCommand.php                               NEW
addons/corex-kit-company/src/BlueprintActivator.php                        inspector

.github/actions/provision-wordpress/action.yml   multisite/path/db-prefix inputs
.github/workflows/ci.yml                         integration-multisite job
phpunit-multisite.xml.dist  tests/bootstrap-multisite.php  composer.json script
.gitignore                                       /wp-ms/
docs/en/06-cookbooks/multisite.md  docs/ar/06-cookbooks/multisite.md
```

## Contracts

### Site scope

```php
interface SiteScoped
{
    /**
     * Discard anything derived from the site that was current when it was computed.
     * MUST be idempotent and MUST NOT read from WordPress — recomputation is lazy.
     */
    public function forgetSiteState(int $siteId): void;
}

interface SiteScope
{
    public function currentSiteId(): int;

    /** Register a memoizing service for invalidation. Idempotent per instance. */
    public function register(SiteScoped $service): void;

    /**
     * Run $callback with $siteId current, restoring the previous site even on throw.
     *
     * @template T
     * @param Closure():T $callback
     * @return T
     */
    public function run(int $siteId, Closure $callback): mixed;
}
```

`SiteScopeManager::onSwitchBlog(int $new, int $previous, string $context = '')` is hooked on
`switch_blog` at **priority 0** — it must observe the change before any other CoreX callback. It
returns immediately when `$new === $previous`, then updates its own id and calls
`forgetSiteState($new)` on every registered service. There is **no restore branch**: the only fact
acted on is that the id changed, which is symmetric and immune to an unbalanced switch stack.

`SingleSiteScope::run()` is `return $callback();` and its `register()` is empty.
`SiteScopeManager::listen()` is called only when `MultisiteContext::enabled()`, so a single-site
install has no `switch_blog` callback at all (SC-008).

**How a service opts in — exactly three steps.** Implement `SiteScoped`; move the captured value into
a nullable memo that `forgetSiteState()` nulls and the accessor recomputes; register **inside the
singleton factory**:

```php
$this->container->singleton(BrandingService::class, static function (ContainerInterface $c): BrandingService {
    $service = new BrandingService(/* … */);
    $c->make(SiteScope::class)->register($service);

    return $service;
});
```

Registering inside the factory is load-bearing: it happens exactly when the service is first
resolved. Nothing is eagerly constructed, the container is untouched, and a service never resolved in
a request costs nothing.

### Activation

```php
enum ActivationScope: string
{
    case None    = 'none';
    case Site    = 'site';
    case Network = 'network';
    case MustUse = 'must-use';

    public function isActive(): bool;
    public function label(): string;   // translated, 'corex' domain
}

interface PluginActivationInspector
{
    public function scopeOf(string $pluginFile): ActivationScope;
    public function isActive(string $pluginFile): bool;
    /** @return list<string> every active plugin file, any scope, deduped, network first */
    public function activePluginFiles(): array;
    /** @return array<string, ActivationScope> */
    public function scopes(): array;
}
```

`WpPluginActivationInspector` takes **only** `MultisiteContext` — no container, no config, no logger.
That is what lets `Boot` construct it before the `Application` exists. It reads
`get_option('active_plugins')` → `Site`; when multisite, `array_keys(get_site_option('active_sitewide_plugins', []))`
→ `Network` (network wins on collision, FR-007); then applies

```php
apply_filters('corex_plugin_activation_scopes', array<string, ActivationScope> $scopes, int $siteId)
```

which is the only route to `MustUse` (FR-009) — directory-shaped add-ons behind an mu-plugin loader
are not enumerable and CoreX will not guess.

### Where the contexts bind, and why not in a provider

`Boot::boot()` calls `RuntimeContexts::detect()` — one `is_multisite()` check, then five small object
constructions — and passes it to `Application`, which seeds them next to `ContainerInterface`,
`BootLogger`, `HookRegistry` and `ControllerMap` in `boot()`.

```php
public function __construct(
    private readonly bool $debug = false,
    private readonly array $providers = [],
    private readonly ?RuntimeContexts $contexts = null,
) { $this->container = new Container(); }
```

The `null` default keeps every existing `new Application($debug, $providers)` call site — including
the tests — compiling unchanged.

**They must not be bound in `CoreServiceProvider::register()`.** `Container::register()`
(`Container.php:107-113`) does `unset($this->instances[$id])` before recording the binding, so a
later `singleton()` would silently discard Boot's instance and produce a second inspector with a
divergent memo. Root-seeding via `instance()` is the only safe mechanism given the current container,
and a unit test pins that no provider binds these ids.

### Provider resolution

`resolve()` becomes two passes:

```php
/** Pass 1: per-provider gate, ignoring dependencies. @return array<string, ?AddonBlockReason> */
private function selfGate(AddonRuntimeState $state): array;

/** Pass 2: iteratively drop providers whose dependencies are not in the surviving set. */
private function satisfyDependencies(array $candidates, array $dependencies): array;
```

Pass 2 runs to a fixpoint, so declaration order stops mattering (FR-011) — an existing latent bug
that Multisite merely makes reachable. Because the whole resolution is computed per-site, the correct
cross-scope answers fall out of FR-012 without a special case.

`AddonProviderResolution` keeps `reasonFor()` (FR-014) and gains `exclusions()` / `exclusionFor()` /
`scopes()`. `AddonExclusion` carries `slug`, `AddonBlockReason`, `ActivationScope`, `siteId`,
`detail` and a translated `message()`. `AddonRuntimeState` gains an optional
`array $activationScopes = []` plus `scopeOf()`, so `AddonStatusResolver` and its tests are
untouched.

### Config

Source list becomes, highest precedence first:

```
DotenvSource → NetworkLockSource → OptionsSource → NetworkDefaultsSource → DefaultsSource
```

Two invariants force exactly this arrangement:

- **`.env` stays on top.** It is the deployment, owned by the host operator. A network lock above it
  would let a database row a network admin can write override the deployment file — a precedence
  inversion and a privilege-escalation path (FR-022).
- **A lock beats a site option; a network default loses to one.** That is what the two words mean,
  and it requires one source above `OptionsSource` and one below it. No other ordering expresses
  both (FR-021).

Non-breaking, mechanically: both new sources return `false` from `has()` unless multisite is enabled
**and** the key is registered with the matching scope **and** a network option is actually stored.
Unregistered keys default to `SettingScope::Site`, so every key that resolves today resolves through
an identical chain (FR-020, FR-024). SC-007 is the proof obligation.

```php
enum SettingScope: string { case Site; case NetworkDefault; case NetworkLocked; }

final class SettingDefinition
{
    public function __construct(
        public readonly string $key,          // dot key, e.g. 'security.throttle.limit'
        public readonly SettingScope $scope,
        public readonly string $label = '',
        public readonly ?string $description = null,
    ) {}
}

final class SettingRegistry
{
    public function add(SettingDefinition $definition): void;   // last write wins, keyed by key
    public function get(string $key): ?SettingDefinition;
    public function scopeOf(string $key): SettingScope;         // Site when unregistered
    public function all(): array;
    public function withScope(SettingScope $scope): array;      // spec 101 reads this
}
```

`SettingRegistry` is bound **before** the `ConfigInterface` closure in `CoreServiceProvider::register()`.
Because the sources hold the registry object by reference and resolution never happens during the
register pass, a definition added by any later provider is honoured — pinned by a test that registers
a definition *after* `ConfigInterface` has been resolved and asserts the lock applies.

Both sources use the same option namespace `corex_network_*` (mirroring `OptionsSource`'s dot→
underscore mapping), so re-scoping a key from default to locked is a one-line change with no data
migration. Runtime (admin-UI) locking is spec 101; v1 here is code-declared scope plus a
`corex_setting_scopes` filter.

`Truthy::of(mixed $value): bool` with the six-value set `1|true|on|yes` (bool, int, string) becomes
the single rule behind `FeatureFlags::toBool()` (unchanged behaviour — it is already the canonical
set), `Boot::featureFlagEnabled()` (widened from `[true, 1, '1']`) and `AddonManager.php:100`
(widened from strict `=== '1'`). It is a pure static so `Boot` can call it before the container
exists (FR-025).

`Boot::featureFlagEnabled()` also gains two fixes: it reads `.env` through a `DotenvSource` instead
of the `getenv()` call that can never fire (FR-026 — **a real behaviour change, changelog it**), and
on multisite falls back to `get_site_option('corex_features_' . $flag)` when the site option is
absent. Boot has no container, so this is a documented fixed rule for the `features.*` namespace
only, not a general precedence.

### Migrations

`Migrator` is `final` and its `create()` requires `wp-admin/includes/upgrade.php`, so it is
unrunnable headlessly. Extract an interface — **`Migrator`'s body does not change**:

```php
interface SchemaMigrator
{
    public function fullName(string $name): string;
    public function create(Table $table): void;
    public function drop(string $name): void;
    public function exists(string $name): bool;
}
```

`DataServiceProvider` adds `singleton(SchemaMigrator::class, fn ($c) => $c->make(Migrator::class))`,
so existing `make(Migrator::class)` callers are untouched.

```php
final class SchemaComponent
{
    /** @param list<Table> $tables */
    public function __construct(
        public readonly string $id,          // 'product-foundation'
        public readonly string $version,     // '3'
        public readonly array $tables,
        public readonly string $optionName,  // 'corex_product_foundation_schema_version'
    ) {}
}
```

`optionName` is **explicit, not derived** — FR-033. `ConfigServiceProvider` stops calling
`installFoundationSchema()` and registers one `SchemaComponent` with its seven tables instead; the
method's version-check and post-verify logic moves verbatim into the runner.

```php
final class SiteMigrationRunner
{
    public function __construct(
        private readonly SchemaMigrator $migrator,
        private readonly SchemaRegistry $schemas,
        private readonly SchemaVersionStore $versions,
        private readonly SiteScope $scope,
        private readonly NetworkContext $network,
        private readonly EventDispatcher $events,
    ) {}

    public function runForSite(int $siteId): SiteMigrationResult;
    /** @param list<int> $siteIds */
    public function runForSites(array $siteIds): NetworkMigrationResult;
    /** Resume with $result->nextOffset until $result->complete. */
    public function runForNetwork(int $batchSize = 50, int $offset = 0): NetworkMigrationResult;
    /** @return list<int> sites whose stored version differs from a registered component */
    public function pendingSiteIds(int $batchSize = 0, int $offset = 0): array;
}
```

Every dependency is an interface or a pure object, so the runner is **100% headless-unit-testable**
with a fake migrator, a fake version store and a `FakeSiteScope` that records the ids it was asked to
run under. That is the highest-leverage decision in this plan: batching, continue-on-error,
idempotency and event dispatch are all proved in the `test` job, and the integration suite is left
with only the five facts that need a real WordPress.

Semantics: version check first (`AlreadyCurrent`, no writes) → `dbDelta` (itself idempotent) → the
existing post-verify (`exists()` for every table) before the version is written, so a partial create
leaves the version unwritten and the next run retries (FR-030). Each site body runs inside
`$this->scope->run($siteId, …)` (FR-018). `runForSites()` wraps each site in its own try/catch so one
failure never aborts the run, and — unlike `ProviderRepository` — the failure is **returned**, not
only logged (FR-031).

`WpNetworkContext::siteIds()` uses `get_sites(['fields' => 'ids', 'number' => $limit ?: 0,
'offset' => $offset, 'orderby' => 'id', 'order' => 'ASC', 'deleted' => 0, 'spam' => 0])`. Archived
sites are **included** — an archived site can be un-archived and must not return with a stale schema.

### Site lifecycle hooks

`SiteLifecycleSubscriber` implements the existing `SubscribesToHooks` seam so `HookRegistry` wires it
with container injection and per-hook dedupe:

| Hook | Priority | Why |
|---|---|---|
| `wp_initialize_site` | 20 | After WordPress's own priority-10 default has created the site's core tables and options. WordPress fires it while switched to the new site; `SiteScope::run()` is safe either way because the `$new === $prev` guard makes the inner switch a no-op. |
| `wp_delete_site` | 10 | Dispatches `SiteDeleted`. |
| `wpmu_drop_tables` | 10 | **A filter, not an action.** CoreX appends `$migrator->fullName($name)` for each registered table and **WordPress does the dropping**, in its own order with its own error handling. It fires while switched to the site being deleted, so `fullName()` resolves the right prefix. |

`SchemaSelfHeal` replaces the current every-request install: it runs `runForSite()` only when
`is_admin() || wp_doing_cron() || defined('WP_CLI')`, behind a 60-second per-site transient lock.
This **narrows** today's behaviour (a front-end-only site used to heal on first page view) and is a
deliberate trade under FR-035 — `wp_initialize_site` covers new sites, and cron covers an upgraded
site within the hour. Changelog it.

### Events

Reuse `plugins/corex-core/src/Events/`. One addition:

```php
public function hasListenersFor(string $eventClass): bool
{
    return ($this->listeners[$eventClass] ?? []) !== [];
}
```

so a 500-site loop does not construct `SiteScopeSwitched` when nobody listens (FR-041).

Events are `final`, immutable, scalars only (FR-040): `SiteCreated`, `SiteSchemaInstalled`,
`SiteMigrated`, `SiteMigrationFailed`, `SiteDeleting`, `SiteDeleted`, `SiteScopeSwitched`.
Three are mirrored as WordPress actions, following the `do_action('corex_booted', $app)` precedent:
`corex_site_created`, `corex_site_migrated`, `corex_site_deleted`. `SiteScopeSwitched` is not mirrored
— WordPress already has `switch_blog` — and neither are the failure events, which are in-framework
diagnostics consumed by a notification producer.

## Testing

**Headless (Pest + Brain Monkey), `tests/Unit/Multisite/` and siblings** — runs in the existing
`test` job, no new infrastructure:

- `ActivationScope`, `WpPluginActivationInspector` (stub `get_option`/`get_site_option`/`apply_filters`),
  network-wins-over-site, dedupe, the filter seam, and single-site output equal to today's (FR-010).
- The three `SingleSite*` contexts asserted to make **zero** WordPress calls; the `Wp*` ones via
  Brain Monkey.
- `SiteScopeManager`: the `$new === $prev` no-op; switch and restore handled identically; every
  registered service invalidated; `run()` restores on throw; **`add_action('switch_blog', …)` never
  called on single-site** (SC-008).
- `AddonProviderResolver`: order-independence (declare a dependency *after* its dependent), cross-scope
  satisfaction, and exclusion messages naming scope + site.
- `SettingRegistry`, both network sources, and the SC-007 precedence matrix — present in each layer,
  absent everywhere, stored null — with empty network sources versus today's three-source chain.
  Plus a definition registered *after* `ConfigInterface` was resolved.
- `SiteMigrationRunner` in full: idempotency, continue-on-error, batching/`nextOffset`, the exact site
  ids passed to `SiteScope::run()`, version written only after every table verifies, and the literal
  option name `corex_product_foundation_schema_version` (FR-033).
- `Truthy` across the six-value set and the three former call sites.
- A test asserting no provider binds `SiteContext`/`MultisiteContext`/`NetworkContext`/
  `PluginActivationInspector` (the `Container::register()` clobber risk).

**Integration against a real network**, `tests/Integration/Multisite/` — only the five facts
WordPress alone can answer, plus the boot-error assertion:

1. `Migrator::fullName()` inside `switch_to_blog($n)` yields `cxms_{n}_corex_*`, and `cxms_corex_*` on
   the main site.
2. A network-activated add-on's provider is loaded on all three sites (SC-001); a site-activated one on
   exactly one (SC-002).
3. `cxms_3_corex_*` exists for the site created **after** activation, with no manual step (SC-004).
4. `wp site delete` removes them (SC-005).
5. Notification preferences and a branding value do not leak (SC-006).
6. `BootLogger` recorded no error on any site (SC-009) — `ProviderRepository` swallows Throwables, so
   without this a broken provider is indistinguishable from a working one.

## The multisite environment

A **second** WordPress install, not a converted one: `./wp` must stay genuinely single-site, because
"`is_multisite() === false` changes nothing" is a load-bearing claim only a real single-site install
can prove.

- `./wp-ms/` — subdirectory multisite, prefix `cxms_`, **same MySQL database** as `./wp`. One service
  in CI, no extra cost. Added to `.gitignore` beside `/wp/`.
- No web server is involved: the integration suite loads WordPress in-process through `wp-load.php`,
  so `.htaccess` rewrites are irrelevant. That is what makes subdirectory the cheapest correct choice.
- `tests/bootstrap-multisite.php` mirrors `bootstrap-integration.php`, pointing at `wp-ms/wp-load.php`
  and keeping its exit-with-instructions behaviour — a green run must never mean "nothing ran".
- `tests/Integration/Multisite/TestCase.php` extends the integration `TestCase` and additionally skips
  when `! is_multisite()`.
- `composer test:multisite`, not part of `composer test`.

Provisioning gains `multisite` / `path` / `db-prefix` inputs, and the activation branch **is** the
fixture:

```bash
wp core multisite-install --path=wp-ms --url=http://localhost/ --title=Corex ...
wp plugin activate corex-core --network --path=wp-ms
wp plugin activate corex-config corex-forms corex-blocks corex-ui corex-guides --network --path=wp-ms
wp site create --slug=site2 --title='Second Site' --path=wp-ms
wp plugin activate corex-email --path=wp-ms --url='http://localhost/site2/'   # site-scoped only
wp site create --slug=site3 --title='Third Site' --path=wp-ms                 # after activation
```

**CI is the authority for this suite**, consistent with the existing integration and browser policy.
Locally `composer test:multisite` fails loudly with setup instructions until a developer opts in.

## Risks, stated rather than discovered

1. **`ProviderRepository` swallows Throwables.** Any failure introduced here is logged, not surfaced.
   The SC-009 boot-error assertion must land with the first integration test, not after.
2. **`Container::register()` unsets instances.** Root-seeded contexts must never be re-bound. Pinned
   by a unit test.
3. **`switch_blog` priority 0** must stay the lowest CoreX callback on that hook; any future listener
   uses priority ≥ 10 so it reads post-invalidation state. Documented as a rule in the class.
4. **`ResetCommand` deletes `corex_*` options.** On multisite it must operate on one site unless
   `--network` is passed, and must never touch `corex_network_*`. This ships in the same change as the
   inspector conversion, not later.
5. **The schema-version option name.** One character of drift re-runs `dbDelta` on seven tables for
   every existing install. Pinned by a literal-string test.
6. **Two real behaviour changes** need changelog entries: `.env` feature flags now gate add-on boot,
   and schema self-heal no longer runs on front-end requests.
7. **Step ordering.** The activation fix makes previously-dead add-ons boot for the first time on
   existing networks, which runs their schema registration for the first time. The migration work must
   land before or with it, not after.
