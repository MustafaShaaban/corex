# Contract: Add-on Runtime Gating

## Purpose

Corex must decide which optional add-on providers are allowed to boot before those providers register behavior.

## Inputs

- Known provider manifest: slug, provider class, plugin file, dependencies, external gates, safe-disabled behavior
- Activation state: active/inactive per add-on
- Feature flags from existing Corex config
- Dependency probes, including WooCommerce availability
- Installed-file checks for first-party add-ons

## Output

```json
{
  "included_providers": ["Corex\\Ui\\UiServiceProvider"],
  "excluded_providers": {
    "Corex\\Careers\\CareersServiceProvider": "inactive",
    "Corex\\Woo\\WooServiceProvider": "woocommerce-unavailable"
  }
}
```

## Rules

- Core providers are always included.
- Optional add-on providers are excluded unless active and dependencies pass.
- Disabled add-ons register no unsafe hooks, routes, REST endpoints, blocks, admin menus, assets, migrations, tables,
  or cron jobs.
- Safe-disabled behavior must be explicitly named.
- Woo behavior requires both Corex activation state and WooCommerce availability.
- The admin Add-ons UI may display and mutate state, but runtime safety must not depend on the UI booting first.

## Required Tests

- Active provider included
- Inactive provider excluded
- Dependency-missing provider excluded with reason
- Woo inactive/missing dependency excluded
- Existing active behavior preserved
- Disabled unsafe behavior absent
