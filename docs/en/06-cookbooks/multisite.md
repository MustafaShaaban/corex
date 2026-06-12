---
title: Cookbook — running Corex on multisite
description: What changes when Corex runs in a WordPress multisite network.
audience: contributor
stability: stable
last_verified: null
---

# Cookbook — running Corex on multisite

**The problem.** WordPress multisite shares one codebase across many sites, but each site has its **own**
options, content, and active plugins. Corex's options and feature flags are per-site by design — but two things
need care: where state lives, and which capability gates network-level actions.

## Example 1 — per-site feature flags (the default, and why it is correct)

Corex feature flags resolve through the Config engine, which reads a **per-site** option
(`corex_features_<flag>`). On multisite this means each site flips capabilities independently — which is what
you want.

```php
// On site A:
update_option('corex_features_woocommerce_kit', '1');   // store kit on for site A only
Config::enabled('woocommerce_kit');                      // true on A, false on B
```

```text
site A → true
site B → false
```

If you need a **network-wide** default, set it in the project `.env` (read by every site) and let a site
override it with its own option:

```ini
# .env — applies to every site in the network unless a site sets its own option
FEATURES_MAIL_QUEUE=1
```

```text
all sites → mail_queue on, unless a site deletes/clears its option
```

## Example 2 — capabilities for network vs site admin screens

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

## Pitfalls

- **Activation**: network-activating `corex-core` loads the framework everywhere; activating it per-site keeps
  it to chosen sites. Decide deliberately.
- **Custom tables**: Corex custom tables (e.g. form submissions) are created per-site by the migrator — confirm
  the table exists on each site (`$wpdb->prefix` differs per site).
- **Uploads / paths**: multisite stores uploads under `wp-content/uploads/sites/<id>/` — relevant when you back
  up or offload (see [secrets & backups](../05-deployment/secrets-backups-zero-downtime.md)).

## See also

- [AdminGuard](../../README.md#what-lives-where) (the cap+nonce helper) · the generated `FeatureFlags` reference
  in docs-app.
