---
title: Cookbook — building a paid (Pro) add-on
description: Gate commercial features on the Pro edition flag without forking the framework.
audience: contributor
stability: stable
last_verified: null
---

# Cookbook — building a paid (Pro) add-on

**The problem.** You want to ship commercial (Pro) features that are off in the free build and on for paying
customers — without a separate codebase. Corex's edition gate rides on the `pro` feature flag, and add-ons are
ordinary service-provider packages, so a Pro add-on is a normal add-on that checks one flag.

## Example 1 — gate a feature on the Pro edition

The `pro` flag (`features.pro`) is false in the free build. A Pro capability checks it and self-disables
otherwise — the same detect-and-defer shape used for optional dependencies.

```php
public function boot(): void
{
    if (! Config::enabled('pro')) {
        return;                          // free build → the Pro feature never registers
    }
    // ... register the Pro-only blocks / services / REST routes ...
}
```

```text
free build  → feature absent
pro enabled → feature active
```

The flag flips by option (`corex_features_pro`) or env (`FEATURES_PRO`) — so a licence check can enable it at
runtime without shipping different code.

## Example 2 — structure the Pro add-on as a normal package

A Pro add-on is a Composer package with a service provider, registered in `Boot` like any add-on. Keep its
public surface behind the gate, and keep it **optional** — the free framework must not depend on it.

```php
// addons/corex-pro-analytics/src/ProAnalyticsServiceProvider.php
final class ProAnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // bind services unconditionally (cheap), but...
    }

    public function boot(): void
    {
        if (! Config::enabled('pro')) {
            return;                      // ...only wire hooks/routes when Pro is on
        }
        add_action('init', [$this, 'registerProBlocks']);
    }
}
```

```text
the add-on can be installed everywhere; it only "turns on" where pro is enabled
```

The two examples are different shapes: Example 1 gates a feature **inside** an existing module; Example 2
packages a whole **Pro add-on** that ships separately and self-activates on the flag.

## Pitfalls

- Do not make the free framework `use`/`require` a Pro class — that breaks the free build. Pro depends on the
  framework, never the reverse.
- A licence check should **set the flag**, not scatter `if (licensed())` through the code — one gate, read via
  `Config::enabled('pro')`, keeps it testable.
- Pro UI still obeys the constitution: token-only styling, escaping, i18n, RTL, and the Guard Gate.

## See also

- [WooCommerce detect-and-defer](./woocommerce-detect-and-defer.md) (the same gating shape) ·
  [Feature flags](../../README.md#what-lives-where) · `COREX-FRAMEWORK.md §14` (commercial architecture).
