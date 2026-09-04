---
title: Cookbook — running Corex on multisite
description: What changes when Corex runs in a WordPress multisite network.
audience: contributor
stability: stable
last_verified: 2026-09-04
---

# Cookbook — running Corex on multisite

**The problem.** WordPress multisite shares one codebase across many sites, but each site has its **own**
options, content, and active plugins. Corex's options and feature flags are per-site by default — the questions
that need care are where state lives, which capability gates a network action, and what a *network* activation
actually does.

> Every example below is asserted by the multisite integration suite (`composer test:multisite`), which runs
> against a real three-site network in CI. Before spec 100 this page was not verified against anything, and one
> of its examples was backwards.

## Example 1 — config precedence, and which layer wins

Corex resolves a setting through an ordered chain and returns the **first source that has the key**
(`Foundation/CoreServiceProvider.php`). Highest priority first:

| # | Source | Where it lives |
|---|---|---|
| 1 | `DotenvSource` | the project `.env` |
| 2 | `NetworkLockSource` | `corex_network_<key>`, for keys registered `NetworkLocked` |
| 3 | `OptionsSource` | the per-site option |
| 4 | `NetworkDefaultsSource` | `corex_network_<key>`, for keys registered `NetworkDefault` |
| 5 | `DefaultsSource` | the value in code |

Two consequences worth stating plainly, because the previous version of this page got the first one wrong:

- **A site cannot override a `.env` value.** `.env` sits above every option, so it is a network-wide *override*,
  not a network-wide *default*. If you want a value a site can change, do not put it in `.env`.
- **"Network default" and "network lock" are the same option, on opposite sides of the site option.** Both read
  `corex_network_<key>`; what decides the behaviour is the scope the key was registered with.

```php
// A network-wide DEFAULT a site may override — scope decides, not the storage.
$registry->add(new SettingDefinition('mail.queue', SettingScope::NetworkDefault));
update_network_option($networkId, 'corex_network_mail_queue', '1');
update_option('corex_mail_queue', '0');   // this site opts out — the site option wins
```

```text
other sites → 1 (network default)   ·   this site → 0 (site option)
```

```php
// A network-wide LOCK a site may not override.
$registry->add(new SettingDefinition('mail.queue', SettingScope::NetworkLocked));
update_option('corex_mail_queue', '0');   // ignored — the lock sits above the site option
```

```text
every site → 1, whatever the site option says
```

A key that is **not** registered stays site-scoped and follows the pre-existing chain, which is what keeps the
network layer non-breaking for single-site installs.

## Example 2 — network activation actually works now

Network-activating a Corex add-on used to disable it silently on every site: `Boot` read only
`active_plugins` and never `active_sitewide_plugins`, so the provider was dropped, no bindings, hooks, blocks
or REST routes registered, and the Network Plugins screen still said "active". Spec 100 replaced that with
`PluginActivationInspector`, which reads both.

```php
// Both scopes are visible, and network beats site on a collision.
$inspector->scopeOf('corex-email/corex-email.php');   // Network | Site | MustUse | None
$inspector->isActive('corex-email/corex-email.php');  // true for either active scope
```

```text
network-activated add-on → loads on every site in the network
site-activated add-on    → loads on that site only
```

## Example 3 — capabilities for network vs site admin screens

Corex admin screens gate on `manage_options` via the shared `AdminGuard`. On multisite, `manage_options` is a
**site** capability; network-level actions use `manage_network`. If you add a network-admin screen, gate it on
the network capability instead.

```php
// site-level screen (the default Corex pattern) — fine on multisite, per-site:
if (! $this->guard->authorized('manage_options')) { return; }

// a network-admin screen would instead require:
if (! current_user_can('manage_network')) { return; }
```

```text
site admins manage their own site; only network admins touch network screens
```

## Example 4 — schema across a network

Every Corex table is per-site: `Migrator::fullName()` reads `$wpdb` on each call, so `switch_to_blog(2)`
resolves `wp_2_corex_activity` with no further help. Two things follow.

**A site created later gets its tables automatically.** `SiteLifecycleSubscriber` runs the migrations on
`wp_initialize_site`, and `wp_delete_site` drops them again — the deletion is registered through
`wpmu_drop_tables`, so WordPress removes them as part of its own cleanup.

**An existing network is migrated explicitly**, because installing an update does not visit every site:

```bash
wp corex migrate --network            # every site in the network
wp corex migrate --url=site2.example  # one site
```

```text
--network walks the site list in batches and continues past a failing site,
reporting per-site outcomes rather than stopping at the first error
```

## Pitfalls

- **Uploads and paths**: multisite stores uploads under `wp-content/uploads/sites/<id>/` — relevant when you
  back up or offload (see [secrets & backups](../05-deployment/secrets-backups-zero-downtime.md)).
- **CoreX will not flush the object cache for you when a persistent one is installed**, and on a network it
  says why: the flush would empty the object cache for *every* site, and take CoreX rate limits and
  spent-token records with it. `wp cache flush` still does it — that is WordPress's operation, with its own
  consequences, and CoreX is not claiming the result was safe.
- **`wp corex reset` requires `--network` on a network.** Without it the command refuses, and the message says
  which of the two reasons applies: a full reset would touch the whole Multisite database, or a
  network-activated add-on is in scope. Wiping every site is not something a command should do because a flag
  was left off.
- **Notification preferences are per-site**, stored under a blog-prefixed user-meta key off the main site.

## Running the multisite suite

```bash
powershell -File .\scripts\setup-wordpress.ps1 -Multisite   # builds ./wp-ms, three sites
composer test:multisite
```

The suite skips itself with instructions when `./wp-ms` is absent, so it is safe to run without the network.

## See also

- [What's in the box](../../README.md#whats-in-the-box) — where `AdminGuard` and the rest live. The previous
  version of this line pointed at `#what-lives-where`, an anchor `README.md` does not have.
- The generated `FeatureFlags` and `SettingScope` references in docs-app.
