# Tasks: The network CoreX has been claiming to support

**Spec**: [spec.md](spec.md) · **Plan**: [plan.md](plan.md) · **Branch**: `spec/100-multisite-runtime-foundation`

Phases are ordered so each leaves the tree green. Phase 5 must not land before Phase 6 in a release —
the activation fix boots add-ons that were previously dead on network installs, and those add-ons
register schema.

## Phase 1 — Contexts and scope primitives (no behaviour change)

- [ ] **T001** `MultisiteContext`, `SiteContext`, `NetworkContext`, `NetworkCapabilities` interfaces
      in `plugins/corex-core/src/Multisite/`. (FR-001)
- [ ] **T002** `SingleSiteMultisiteContext`, `SingleSiteContext`, `SingleSiteNetworkContext`,
      `SingleSiteCapabilities` — constants only, zero WordPress calls. (FR-002)
- [ ] **T003** `WpMultisiteContext`, `WpSiteContext`, `WpNetworkContext`, `WpNetworkCapabilities`.
      `WpNetworkContext::siteIds()` includes archived, excludes deleted and spam. (FR-001, FR-043)
- [ ] **T004** `SiteScoped` and `SiteScope` interfaces; `SingleSiteScope` (direct call, empty
      register); `SiteScopeManager` with the `$new === $prev` guard and `switch_blog` at priority 0.
      (FR-015, FR-017, FR-018)
- [ ] **T005** `RuntimeContexts::detect()` + `seedInto(Container)`.
- [ ] **T006** `Application::__construct` gains `?RuntimeContexts $contexts = null`, seeded in
      `boot()` beside `ContainerInterface`/`BootLogger`/`HookRegistry`/`ControllerMap`. Existing
      two-arg call sites must compile unchanged. (FR-003)
- [ ] **T007** `ListenerProvider::hasListenersFor(string $eventClass): bool`. (FR-041)
- [ ] **T008** Pest: contexts answer correctly for both shapes; `SingleSite*` make zero WP calls;
      `SiteScopeManager` no-ops on equal ids, treats switch and restore identically, invalidates every
      registered service, and `run()` restores on throw.
- [ ] **T009** Pest: `add_action('switch_blog', …)` is never called when multisite is disabled.
      (SC-008, FR-004)
- [ ] **T010** Pest: no provider binds `SiteContext`/`MultisiteContext`/`NetworkContext`/
      `PluginActivationInspector` — the `Container::register()` clobber guard. (plan risk 2)

## Phase 2 — Config network layer

- [ ] **T011** `SettingScope` enum, `SettingDefinition`, `SettingRegistry` (`scopeOf()` returns
      `Site` when unregistered) + a `corex_setting_scopes` filter. (FR-023, FR-024)
- [ ] **T012** `NetworkLockSource` and `NetworkDefaultsSource` — `has()` returns false unless
      multisite is enabled **and** the key carries the matching scope **and** a network option is
      stored. Option namespace `corex_network_*`. (FR-021)
- [ ] **T013** `CoreServiceProvider::register()` — bind `SettingRegistry` **before** the
      `ConfigInterface` closure; five-source list `Dotenv → NetworkLock → Options → NetworkDefaults
      → Defaults`. (FR-020, FR-022)
- [ ] **T014** `Truthy::of()`; route `FeatureFlags::toBool()`, `Boot::featureFlagEnabled()` and
      `AddonManager.php:100` through it. (FR-025)
- [ ] **T015** `Boot::featureFlagEnabled()` reads `.env` through `DotenvSource` instead of `getenv()`,
      and falls back to `get_site_option('corex_features_*')` on multisite. (FR-026)
- [ ] **T016** Pest: the SC-007 precedence matrix — present in each layer, absent everywhere, stored
      null — identical with empty network sources; lock beats site option; default loses to it and
      beats code defaults; `.env` beats both.
- [ ] **T017** Pest: a `SettingDefinition` registered *after* `ConfigInterface` was resolved still
      applies.
- [ ] **T018** Pest: `Truthy` across the six-value set; the three former call sites agree.

## Phase 3 — Schema, migrations, site lifecycle

- [ ] **T019** `SchemaMigrator` interface; `Migrator implements SchemaMigrator` with **no body
      change**; `DataServiceProvider` binds `SchemaMigrator` → `Migrator`.
- [ ] **T020** `SchemaComponent` (explicit `optionName`) and `SchemaRegistry`. (FR-033)
- [ ] **T021** `SchemaVersionStore` + `WpOptionSchemaVersionStore` (`autoload = false`). (FR-029)
- [ ] **T022** `SiteMigrationOutcome`, `SiteMigrationResult`, `NetworkMigrationResult`. (FR-032)
- [ ] **T023** `SiteMigrationRunner` — `runForSite`/`runForSites`/`runForNetwork`/`pendingSiteIds`;
      version check first, `dbDelta`, post-verify before writing the version; each site inside
      `SiteScope::run()`; per-site try/catch returning the failure. (FR-028, FR-030, FR-031, FR-034)
- [ ] **T024** `ConfigServiceProvider` registers a `SchemaComponent` for the seven foundation tables
      with the literal option name `corex_product_foundation_schema_version`; delete
      `installFoundationSchema()`. (FR-033)
- [ ] **T025** `SchemaSelfHeal` — admin/cron/CLI only, behind a 60-second per-site transient lock.
      (FR-035)
- [ ] **T026** Site lifecycle events in `Multisite/Events/`, scalars only; mirror
      `corex_site_{created,migrated,deleted}` as WordPress actions. (FR-039, FR-040)
- [ ] **T027** `SiteLifecycleSubscriber` — `wp_initialize_site` @20, `wp_delete_site` @10,
      `wpmu_drop_tables` @10 as a **filter** that appends table names. (FR-036, FR-037)
- [ ] **T028** `MultisiteServiceProvider` — appended to `Boot::CORE_PROVIDERS`; calls
      `SiteScopeManager::listen()`, registers the subscriber and self-heal, all gated on
      `MultisiteContext::enabled()` where they only apply to a network.
- [ ] **T029** Pest: runner idempotency, continue-on-error, batching/`nextOffset`, exact site ids
      passed to the scope, version written only after every table verifies, and the literal option
      name.
- [ ] **T030** Pest: the subscriber's `wpmu_drop_tables` return value contains every registered
      table's full name and preserves the incoming ones.

## Phase 4 — CLI migration command

- [ ] **T031** `packages/cli/src/Commands/MigrateCommand.php` — `wp corex migrate [--network]
      [--batch=<n>] [--dry-run]`; per-site table output; exit non-zero when any site failed.
      (FR-031)
- [ ] **T032** Register it in `CliServiceProvider::boot()` following the existing closure-delegating
      pattern.
- [ ] **T033** Pest: the command's pure decision layer; the WP-CLI boundary stays a printer.

## Phase 5 — Activation scope and provider resolution

- [ ] **T034** `ActivationScope` enum with `isActive()` and a translated `label()`. (FR-005)
- [ ] **T035** `PluginActivationInspector` interface; `WpPluginActivationInspector implements
      PluginActivationInspector, SiteScoped` depending only on `MultisiteContext`; network wins over
      site; `corex_plugin_activation_scopes` filter is the only route to `MustUse`.
      (FR-006, FR-007, FR-009)
- [ ] **T036** `Boot::activePlugins()` deleted; `runtimeState(RuntimeContexts $contexts)` uses the
      inspector; `activeSlugs()` also records `array<string, ActivationScope>`.
- [ ] **T037** `AddonRuntimeState` gains optional `activationScopes` + `scopeOf()`; `isActive()`
      preserved and derived so `AddonStatusResolver` and its tests are untouched.
- [ ] **T038** `AddonBlockReason` enum and `AddonExclusion` with a translated `message()`.
- [ ] **T039** `AddonProviderResolver` two-pass fixpoint (`selfGate` + `satisfyDependencies`).
      (FR-011, FR-012)
- [ ] **T040** `AddonProviderResolution` gains `exclusions()`/`exclusionFor()`/`scopes()`;
      `reasonFor()` kept. (FR-013, FR-014)
- [ ] **T041** Pest: a network-activated add-on resolves; network wins on collision;
      `activePluginFiles()` is deduped and stable; the memo drops on `switch_blog`; single-site output
      equals today's `get_option('active_plugins')`. (FR-010)
- [ ] **T042** Pest: resolution is order-independent; cross-scope dependency satisfaction; exclusion
      messages name scope and site.

## Phase 6 — Converge every activation call site

- [ ] **T043** `AddonManager.php:90` — inspector; `AddonState` gains optional `scopes` + `scopeOf()`.
- [ ] **T044** `AdminDashboard.php:225,259` — inspector; the `advanced.multisite` row at :204 gains
      network id and site count from `NetworkContext`.
- [ ] **T045** `OverviewRenderer.php:352` — inspector.
- [ ] **T046** `AccessScreen.php:600` — inspector.
- [ ] **T047** `NewsletterSignupRenderer.php:84` — inspector.
- [ ] **T048** `BlueprintActivator.php:284` — `is_plugin_active()` → `$inspector->isActive()`.
- [ ] **T049** Delete the hand-rolled merges in `FormsFlowsScreen.php:147-150` and
      `EmailStudioScreen.php:145-148`; both take the inspector. Update their contract tests, which
      currently string-assert `'active_sitewide_plugins'`. (FR-008)
- [ ] **T050** `ResetCommand.php:123` — inspector, **plus** the network guard: one site unless
      `--network`, never touches `corex_network_*`. (plan risk 4)
- [ ] **T051** Pest: admin and runtime agree on every install shape; `ResetCommand` refuses a network
      scope without the flag.

## Phase 7 — The eager-capture conversions and the two leaks

- [ ] **T052** `DataServiceProvider.php:61` — inject `ConfigInterface` into `QueryExecutor`; read
      `query.max` where it is applied. (FR-016)
- [ ] **T053** `AssetsServiceProvider.php:30-33` — `AssetManager` becomes `SiteScoped`, memoizing the
      base URL and dropping it on switch; manifest and version stay file-derived.
- [ ] **T054** `ConfigServiceProvider.php:181-185` — `BrandingService` becomes `SiteScoped`. This is
      the canonical documented example.
- [ ] **T055** `CareersServiceProvider.php:62` — the HR email / `admin_email` is read at send time.
- [ ] **T056** Regression test pinning `corex.middleware.throttle` as `bind()`, not `singleton()` —
      it is correct today and a future "optimisation" would silently reintroduce the bug.
- [ ] **T057** `WpNotificationPreferenceStore` — blog-prefixed meta key off the main site; the main
      site keeps the unprefixed key, which is where the only correct existing rows are. (FR-044)
- [ ] **T058** `JobRunner` + the Action Scheduler payload carry `siteId`; execution and the capability
      check run inside `SiteScope::run()`. (FR-045)
- [ ] **T059** `CacheManager.php:162` — `wp_cache_flush()` gains a network-aware warning; correct the
      `CacheScope.php:30-32` docblock, which says "the site" and means the network.
- [ ] **T060** Pest for each conversion: construct → switch → assert the value re-reads from the new
      site. (FR-019)

## Phase 8 — The multisite environment

- [ ] **T061** `tests/bootstrap-multisite.php` — `wp-ms/wp-load.php`, exit-with-instructions when
      absent.
- [ ] **T062** `phpunit-multisite.xml.dist` + `tests/Integration/Multisite/TestCase.php` (skips when
      `! is_multisite()`); `composer test:multisite`, not part of `composer test`.
- [ ] **T063** `.gitignore` gains `/wp-ms/`.
- [ ] **T064** `.github/actions/provision-wordpress/action.yml` — `multisite`/`path`/`db-prefix`
      inputs; `wp core multisite-install` branch; the three-site activation fixture from the plan.
- [ ] **T065** `.github/workflows/ci.yml` — `integration-multisite` job, cloned from `integration`.
- [ ] **T066** Integration: per-site prefixes under `switch_to_blog`. (plan §Testing 1)
- [ ] **T067** Integration: network-activated provider loads on all three sites; site-activated on
      exactly one. (SC-001, SC-002)
- [ ] **T068** Integration: all seven tables exist for the site created after activation. (SC-004)
- [ ] **T069** Integration: deleting that site removes them. (SC-005)
- [ ] **T070** Integration: settings, branding and notification preferences do not leak. (SC-006)
- [ ] **T071** Integration: `BootLogger` recorded no error on any site. (SC-009)
- [ ] **T072** Land T067 first with the assertion failing against the current `Boot`, so CI history
      carries the defect. (SC-003)
- [ ] **T073** `scripts/setup-wordpress.ps1` gains an optional `-Multisite` switch; local default
      stays single-site.

## Phase 9 — Close the loop

- [ ] **T074** Correct `docs/en/06-cookbooks/multisite.md` and `docs/ar/06-cookbooks/multisite.md`:
      real precedence, real network-activation behaviour, `last_verified` set. (FR-046)
- [ ] **T075** `docs/internal/COREX-FRAMEWORK.md` — the new `Corex\Multisite` layer, the site-scope
      rule, and the config precedence (its §26 rule requires this in the same change).
- [ ] **T076** `docs-app` reference and any guide naming config precedence or add-on activation.
- [ ] **T077** `CHANGELOG.md` — including the two behaviour changes: `.env` feature flags now gate
      add-on boot, and schema self-heal no longer runs on front-end requests.
- [ ] **T078** Guard Gate — `clean-code-guard`, `wp-guard`, `test-guard`, `docs-guard`.
- [ ] **T079** `PROGRESS.md` + `DECISIONS.md`.
- [ ] **T080** Open the PR into `main`; all required checks plus `integration-multisite` green.
