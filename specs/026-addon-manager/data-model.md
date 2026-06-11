# Data Model: Add-on manager admin screen (026)

Pure value objects + services (no WordPress) except the screen + activator boundary.

## Addon (value object)

| Field | Type | Notes |
|---|---|---|
| `slug` | `string` | e.g. `corex-ui` (the plugin directory) |
| `pluginFile` | `string` | e.g. `corex-ui/corex-ui.php` |
| `label` | `string` | human label (translatable at render) |
| `flag` | `string\|null` | the feature-flag slug toggled with the plugin, or null |
| `requires` | `list<string>` | add-on slugs this one depends on |

## AddonState (value object)

| Field | Type | Notes |
|---|---|---|
| `activeSlugs` | `list<string>` | add-on slugs currently active (from `active_plugins`) |
| `enabledFlags` | `list<string>` | feature-flag slugs currently on |

- `isActive(string $slug): bool`
- `flagOn(string $flag): bool`

## AddonView (value object — one row's render model)

| Field | Type | Notes |
|---|---|---|
| `addon` | `Addon` | the add-on |
| `installed` | `bool` | the plugin file exists |
| `active` | `bool` | currently active |
| `flagOn` | `bool` | flag on (false when no flag) |
| `blockedReason` | `string\|null` | why a toggle is blocked (null = togglable) |

## AddonRegistry (pure service)

`all(): list<Addon>` — the known Corex add-ons (see plan.md registry table). `find(string $slug): ?Addon`.

## AddonManager (pure service)

- `views(AddonState $state, bool $installedFn-result …): list<AddonView>` — builds a row per add-on with its
  state + `blockedReason` (the disable-blocked-by-dependent or enable-blocked-by-missing-dependency message, or
  null). _Installed-ness is supplied by the caller (the screen checks the filesystem)._
- `canDisable(string $slug, AddonState $state): bool` — false iff some **active** add-on lists `$slug` in
  `requires`.
- `blockingDependents(string $slug, AddonState $state): list<string>` — those active dependents.
- `canEnable(string $slug, AddonState $state): bool` — true iff every `requires` slug is active.
- `missingDependencies(string $slug, AddonState $state): list<string>` — the inactive required deps.

## AddonActivator (WP boundary — integration-tested for the flag; plugin toggle reversible)

- `enable(Addon $addon): void` → `activate_plugins($addon->pluginFile)`; if `$addon->flag`,
  `update_option('corex_features_' . $addon->flag, '1')`.
- `disable(Addon $addon): void` → `deactivate_plugins($addon->pluginFile)`; if `$addon->flag`,
  `delete_option('corex_features_' . $addon->flag)`.

## AddonsScreen (admin boundary)

Registers a submenu under `corex-settings`; `render()` (AdminGuard authorized) lists the `AddonView`s with a
per-add-on enable/disable form (hidden slug + action); `maybeToggle()` (AdminGuard `verifiedPost`) validates
the requested toggle against the manager's rules, calls the activator when allowed, and reports the result or
the refusal reason.
