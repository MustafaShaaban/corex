# Quickstart: Add-on manager admin screen (026)

## Prerequisites

- The Corex install at `./wp`; `corex-config` active. The rendered screen needs a browser (Apache); the data +
  rules are verifiable headlessly.

## 1. Unit tests (pure registry + manager — no WP)

```bash
vendor/bin/pest tests/Unit/Config/AddonRegistryTest.php tests/Unit/Config/AddonManagerTest.php
```

Expected: the registry lists the known add-ons with correct plugin files + `requires` edges; the manager
builds a view per add-on, blocks disabling a dependency that an active add-on needs (naming the dependent), and
blocks enabling an add-on whose dependency is inactive (naming the missing dep). All green.

## 2. Integration (activator flag toggle on ./wp)

```bash
composer test:integration -- --filter=AddonActivator
```

Expected: `AddonActivator::enable()`/`disable()` set/clear the `corex_features_<flag>` option for a flagged
add-on on the real install — green. (Plugin activation is exercised reversibly.)

## 3. Screen registration (real WP)

```bash
wp eval 'do_action("admin_menu");' --path=wp   # or load /wp-admin/admin.php?page=corex-addons in a browser
```

Expected: a "Add-ons" submenu under the Corex menu; the page lists every add-on with its state and the
dependency reasons where a toggle is blocked.

## 4. Browser smoke (needs Apache)

Open `/wp-admin/admin.php?page=corex-addons`, toggle an add-on, and confirm its plugin + flag changed together;
try to disable `corex-ui` while a kit is active and confirm it is refused with the dependent named.
