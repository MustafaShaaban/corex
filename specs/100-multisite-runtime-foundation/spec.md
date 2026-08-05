# Feature Specification: The network CoreX has been claiming to support

**Feature Branch**: `spec/100-multisite-runtime-foundation`

**Created**: 2026-08-05

**Status**: Draft

**Input**: Owner initiative — make CoreX a genuinely production-capable foundation for WordPress
Multisite projects. This spec is the runtime floor; the Network Admin product is spec 101 and the
CLI is spec 102.

## Why this spec exists

`README.md` advertises Multisite. `docs/en/06-cookbooks/multisite.md` gives operators a page of
advice about it. Neither is backed by code, and one of them is actively wrong.

Across the whole framework — 4 plugins, 12 add-ons, a theme — there are exactly **three** runtime
references to Multisite, and one of those is a diagnostics row that renders the word "Yes". There is
no `switch_to_blog` handling, no network option, no site-lifecycle hook, no super-admin check, no
network-admin screen, and no test that has ever run against a network.

That is not, by itself, the problem. A framework can be honestly single-site. The problem is what
happens when somebody follows the documentation:

**Network-activating a CoreX add-on silently disables it.** `Boot::activePlugins()`
(`plugins/corex-core/src/Boot.php:145-151`) reads `get_option('active_plugins')` and nothing else.
`active_sitewide_plugins` — where WordPress records a network activation — is never consulted. So
`AddonProviderResolver::blockedReason()` returns `'inactive'`, the provider is dropped, and the
add-on registers no bindings, no hooks, no blocks and no REST routes **on every site in the
network**. There is no notice and no error. The Network Plugins screen says the plugin is active.

The codebase already knows the correct answer, in two places:

```php
// plugins/corex-config/src/Forms/FormsFlowsScreen.php:147-150
$active  = array_map('strval', (array) get_option('active_plugins', []));
$network = array_keys((array) get_site_option('active_sitewide_plugins', []));

return in_array(self::FORMS_PLUGIN, [...$active, ...$network], true);
```

So on a network install the admin UI reports Forms as available while its provider never booted. The
runtime and the product disagree about the same fact, and each is internally consistent.

**And the cookbook inverts the config precedence.** `multisite.md:32-42` tells an operator to put a
network-wide default in `.env` "and let a site override it with its own option". The opposite is
true: `CoreServiceProvider.php:36-40` orders the sources `.env` → options → defaults, and
`Repository::resolveSource()` returns the *first* source that has the key. A site cannot override a
`.env` value. The page carries `last_verified: null`, which is the only honest thing about it.

### What is already correct, and must stay that way

The data layer is switch-safe today, by construction rather than by accident. `Migrator::fullName()`
(`plugins/corex-core/src/Database/Schema/Migrator.php:20-27`) reads `global $wpdb` **on every call**,
and every `Wp*Repository` does the same inside each method. `switch_to_blog(2)` therefore resolves
`wp_2_corex_activity` with no further help. Nothing in this spec may regress that, and the design
leans on it: the multisite defect surface is not "the container is wrong", it is three specific
things.

1. Boot reads the wrong activation source.
2. About five singleton factories **capture** site-scoped values at construction.
3. Nothing installs schema for a site created after activation, and nothing cleans up on delete.

Object caching is also already correct — the `corex` cache group is never registered as a global
group, so WordPress prefixes it with the blog id, and every transient is site-scoped.

### The two leaks that are real bugs today

- **Notification preferences are network-global.** `WpNotificationPreferenceStore`
  (`plugins/corex-config/src/Notifications/WpNotificationPreferenceStore.php:24,32,43`) stores under
  the unprefixed usermeta key `corex_notification_preferences`. `usermeta` is a network-wide table,
  so muting a category on one site mutes it on every site.
- **Jobs run under the wrong site.** `JobRunner` (`plugins/corex-core/src/Jobs/JobRunner.php:39,71-88`)
  restores the acting user but never the blog, and the Action Scheduler payload is `[$job->id]` with
  no site id. A job queued on site 2 and executed by a cron tick on site 1 runs its capability check
  and its work against site 1.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — A network-activated CoreX add-on works (Priority: P1)

**Independent Test**: on a Multisite network with `corex-core` and `corex-ui` network-activated, open
any site's admin. The UI add-on's services resolve, its hooks are registered, and its blocks are
available — on the main site and on every subsite, including one created after activation.

### User Story 2 — A site-activated add-on stays on its own site (Priority: P1)

**Independent Test**: `corex-email` activated on site 2 only. Its provider loads on site 2 and does
not load on site 1 or site 3, and the reason it did not load on sites 1 and 3 is retrievable and
says so.

### User Story 3 — Nothing leaks across a blog switch (Priority: P1)

**Independent Test**: read branding, asset base URL, feature flags, notification preferences and a
custom-table row on site 1; `switch_to_blog(2)`; read the same five; `restore_current_blog()`; read
them again. Every value belongs to the site that was current when it was read.

### User Story 4 — A site created after activation is complete (Priority: P1)

**Independent Test**: with CoreX network-active, create a new site. Without visiting it, without
running a command and without any manual step, all seven CoreX foundation tables exist for that
site's prefix.

### User Story 5 — Deleting a site leaves nothing behind (Priority: P2)

**Independent Test**: delete a site that had CoreX tables. No `{prefix}_{id}_corex_*` table remains.

### User Story 6 — A network operator can set a default, and lock one (Priority: P2)

**Independent Test**: a setting declared `NetworkDefault` reads its network value on a site that has
not set its own, and reads the site's value once the site sets one. A setting declared
`NetworkLocked` reads the network value even when a site option exists.

### User Story 7 — Single-site is untouched (Priority: P1)

**Independent Test**: the existing single-site install behaves identically — same providers loaded,
same config resolution, same schema, same options, no new hooks, no new queries. This is proved on a
real single-site WordPress, not by stubbing `is_multisite()` false.

### User Story 8 — A site administrator is not a network administrator (Priority: P1)

**Independent Test**: a user with `manage_options` on site 2 and no network role cannot perform any
operation this spec classifies as network-level; the capability that gates it is
network-scoped, not `manage_options`.

### Edge Cases

- **`switch_to_blog(get_current_blog_id())`.** Extremely common inside loops and inside WordPress's
  own site-initialization path. It must be a hard no-op, not an invalidation.
- **Unbalanced switch/restore.** WordPress fires `switch_blog` for both directions with the same
  signature. CoreX must not track a stack it can get wrong; the only fact it acts on is "the current
  site id changed".
- **A network-activated add-on depending on a site-activated one.** Satisfied on the sites where the
  dependency is active, excluded elsewhere — and the exclusion says which site and which scope.
- **A dependency declared later in the registry than its dependent.** Must resolve. Today it does
  not: `blockedReason()` checks `in_array($dep, $includedSlugs)` against a list built during the same
  loop, so resolution is registry-order-dependent. Multisite does not cause this bug but does make it
  reachable.
- **An add-on in `mu-plugins`.** `wp_get_mu_plugins()` returns top-level files only and CoreX add-ons
  are directories, so a bundled add-on is undetectable. CoreX must not guess; it must offer a
  documented seam.
- **A site archived, then un-archived.** Its schema must still be current, so archived sites are
  included in migration enumeration. Deleted and spam sites are excluded.
- **`dbDelta` fails on one site of two hundred.** The other 199 still run, and the failure is
  returned rather than only logged — `ProviderRepository` swallows Throwables, so a silent failure is
  indistinguishable from success.
- **A front-end-only site.** Schema self-heal is narrowed to admin/cron/CLI, so a site nobody
  administers heals on its cron tick rather than on a visitor's page view.

## Requirements *(mandatory)*

### Runtime context

- **FR-001**: The framework MUST expose whether Multisite is enabled, the current site id, the
  current network id, whether execution is in Network Admin, and whether the request is inside a
  blog switch — through injected contracts, not by calling WordPress functions at each use site.
- **FR-002**: Each contract MUST have a single-site implementation that answers from constants and
  makes zero WordPress calls.
- **FR-003**: The contracts MUST be resolvable from the container by every CoreX module, and MUST be
  constructible by `Boot` before the container exists.
- **FR-004**: A single-site request MUST NOT register any hook, run any query, or perform any
  network enumeration on account of this spec.

### Activation scope

- **FR-005**: Plugin activation MUST be classified as one of: inactive, site-active, network-active,
  must-use.
- **FR-006**: A network-activated CoreX add-on MUST be recognised as active on every site of the
  network, and its service provider MUST load.
- **FR-007**: Where a plugin file appears in both the site and the network list, network MUST win —
  WordPress does not permit a per-site deactivation of a network-activated plugin.
- **FR-008**: There MUST be exactly one service that answers "is this plugin active, and how". Every
  current caller of `get_option('active_plugins')`, `is_plugin_active()`, or a hand-rolled merge of
  the two MUST use it, so the runtime and the admin can no longer disagree.
- **FR-009**: Must-use detection MUST be exposed through a documented filter rather than guessed,
  because directory-shaped add-ons behind an mu-plugin loader are not enumerable.
- **FR-010**: On a single-site install, the set of active plugin files this service reports MUST
  equal what `get_option('active_plugins')` reports today.

### Provider resolution

- **FR-011**: Dependency satisfaction MUST be independent of the order providers appear in the
  registry.
- **FR-012**: Dependency satisfaction MUST ask whether the dependency will load in this request on
  this site, never where its activation record is stored.
- **FR-013**: For every excluded provider, diagnostics MUST report the slug, the reason, the
  activation scope, the site id, and — for a missing dependency — which dependency.
- **FR-014**: The existing `reasonFor(string $slug): ?string` accessor MUST keep working for its
  current callers.

### Site-scoped lifecycle

- **FR-015**: There MUST be one coherent site-scope strategy, defined and tested as a contract, not
  a scatter of manual resets across modules.
- **FR-016**: A service MUST NOT read site-scoped state in its constructor. Where a service memoizes
  site-scoped state, it MUST discard that memo when the current site changes.
- **FR-017**: The invalidation MUST treat a switch and a restore identically, and MUST be a no-op
  when the site id has not actually changed.
- **FR-018**: Running work under another site MUST restore the previous site even when that work
  throws. CoreX code MUST NOT call `switch_to_blog()`/`restore_current_blog()` directly outside this
  one mechanism.
- **FR-019**: After a switch, none of the following may still describe the previous site: option
  values, feature flags, table names, branding, asset base URLs, mail/API configuration, registries,
  cache namespaces, site URLs, job or notification context, permission decisions.

### Configuration

- **FR-020**: Current single-site resolution MUST be unchanged for every key that resolves today.
- **FR-021**: A network default MUST be overridable by a site; a network lock MUST NOT be.
- **FR-022**: A deployment-enforced environment value MUST remain the highest precedence — a
  database row a network administrator can write MUST NOT override the deployment file.
- **FR-023**: Modules MUST be able to declare a setting's scope without core knowing what the setting
  means, and without the declaring module editing a central list.
- **FR-024**: A setting with no declaration MUST behave exactly as it does today.
- **FR-025**: `corex_features_*` MUST have one truthiness rule. Three incompatible rules exist today
  (`Boot.php:216`, `AddonManager.php:100`, `FeatureFlags.php:22`).
- **FR-026**: A feature flag set in `.env` MUST gate add-on boot as well as `FeatureFlags::enabled()`.
  It currently gates only the latter, because `Boot` reads `getenv()` while `DotenvSource` is
  array-backed and never populates it.
- **FR-027**: Secrets MUST remain suitable for environment-based configuration; no requirement here
  moves a secret into the database.

### Migrations

- **FR-028**: A migration runner MUST be able to migrate one site, a given list of sites, or the
  whole network in batches.
- **FR-029**: Schema version MUST be recorded per site.
- **FR-030**: The runner MUST be idempotent — a second run against a current site MUST perform no
  writes.
- **FR-031**: A site-specific failure MUST NOT stop the run when continuation is requested, MUST be
  reported per site, and MUST be distinguishable from success by the caller's exit path.
- **FR-032**: Per-site results MUST name the site, the outcome, what was migrated, and the error if
  any.
- **FR-033**: The existing schema-version option name MUST be preserved verbatim, so no existing
  install re-runs its migration.
- **FR-034**: Tables MUST remain per-site. This spec MUST NOT introduce a shared global table.
- **FR-035**: Schema MUST NOT be written from a front-end request.

### Site lifecycle

- **FR-036**: A site created after CoreX activation MUST receive the CoreX foundation through the
  WordPress site-initialization lifecycle, with no manual step.
- **FR-037**: Deleting a site MUST remove its CoreX tables, through the WordPress table-drop filter
  rather than by dropping them directly.
- **FR-038**: Site creation, foundation installation, and blueprint application MUST remain separate
  operations. This spec owns only the second.
- **FR-039**: Internal events MUST be dispatched for site created, schema installed, migrated,
  migration failed, deleting, deleted, and site scope switched.
- **FR-040**: Event payloads MUST be scalars — no `WP_Site` object — so listeners stay
  headless-testable.
- **FR-041**: Event dispatch MUST NOT construct an event nobody listens for.

### Permissions

- **FR-042**: Network-level operations MUST be gated on a network capability, never on
  `manage_options`.
- **FR-043**: Super-administrator checks MUST be explicit and injected, not inlined as
  `is_super_admin()` at each call site.

### The two leaks

- **FR-044**: Notification preferences MUST be per-site. Existing main-site preferences MUST keep
  resolving without a data migration.
- **FR-045**: A queued job MUST execute under the site it was queued on, including its capability
  check.

### Documentation

- **FR-046**: `docs/en/06-cookbooks/multisite.md` and its Arabic mirror MUST state the real config
  precedence and the real network-activation behaviour, and MUST carry a `last_verified` value
  backed by the suite this spec adds.

### Out of scope

- Network Admin screens, the site registry UI, blueprints, the site factory — spec 101.
- `wp corex multisite` / `wp corex site` commands — spec 102, except the one migration command
  needed to run this spec's own migrations.
- Multisite browser coverage. The integration suite loads WordPress in-process; a subdirectory
  network served over HTTP needs rewrite handling the e2e job does not have, and no browser
  assertion in the suite is multisite-specific.
- Cross-site data sharing of any kind.

## Success Criteria

- **SC-001**: A network-activated add-on's provider loads on the main site, on a subsite, and on a
  site created after activation — proved on a real Multisite install in CI.
- **SC-002**: A site-activated add-on's provider loads on exactly one site, and the exclusion reason
  on the others names the scope and the site.
- **SC-003**: The `integration-multisite` CI job is **red against the current `Boot`** and green
  after the fix, so the defect is demonstrated in CI history rather than asserted.
- **SC-004**: Every one of the seven foundation tables exists, with the correct prefix, on all three
  CI sites — including the one created after activation, with no manual step.
- **SC-005**: Deleting that site removes every one of its CoreX tables.
- **SC-006**: A settings value, a branding value and a notification preference set on one site are
  invisible on another.
- **SC-007**: Config resolution for a matrix of keys — present in each layer, absent everywhere,
  stored as null — is byte-identical before and after the source list grows, when no network value
  is stored.
- **SC-008**: On a single-site install, `add_action('switch_blog', …)` is never called by CoreX, and
  the existing single-site integration suite passes unchanged.
- **SC-009**: `BootLogger` records no error on any of the three CI sites after boot.
- **SC-010**: Pest unit coverage exists for every contract introduced here, running headless in the
  existing `test` job with no new infrastructure.
