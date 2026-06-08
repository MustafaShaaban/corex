# Quickstart & Validation: corex-core Foundation

Runnable scenarios that prove the foundation works end-to-end. Details of types/contracts live in
[contracts/foundation-contracts.md](./contracts/foundation-contracts.md) and
[data-model.md](./data-model.md) — this file is the run/validation guide.

## Prerequisites

- The Environment Gate is satisfied: WordPress ≥ 7.0 at `./wp`, monorepo mapped via junctions,
  `corex-core` active (see `PROGRESS.md` → Environment quick reference).
- From the monorepo root:
  ```bash
  composer install            # wires psr/container, league/container, vlucas/phpdotenv + dev deps
  ```
- For `wp db` commands, prepend the MySQL client to PATH (PROGRESS quick reference).

## Run the tests

```bash
composer test                 # Pest: Unit (headless) + Integration (against ./wp)
composer test -- --group=unit # fast headless loop (no WP bootstrap, no optional plugins)
```

## Scenario 1 — Boot is single & context-independent (US1, SC-001, SC-006)

```bash
# Front-end + REST + cron + WP-CLI all boot once, no fatals:
wp --path=wp eval 'echo \Corex\Boot::app() ? "booted\n" : "no\n";'
wp --path=wp corex --help 2>/dev/null || true   # CLI context boots the framework
curl -s -o /dev/null -w "%{http_code}\n" http://corex.local/         # 200
```

**Expected**: `booted`, HTTP 200, no PHP notices in `wp/wp-content/debug.log`. Booting twice in one
request registers exactly one set of bindings (asserted in the unit suite).

## Scenario 2 — Container resolves with autowiring (US1 AS3–AS5, SC-002)

Register and resolve a dependency-injected service in ≤ 5 lines (illustrative, via the bounded
facade at a framework boundary):

```php
$c = \Corex\Boot::app()->container();
$c->singleton(\Acme\Mailer::class);          // shared
$c->bind(\Acme\Greeter::class);              // transient; ctor type-hints Mailer → auto-injected
$a = $c->make(\Acme\Greeter::class);
$b = $c->make(\Acme\Greeter::class);         // $a !== $b ; both share the one Mailer instance
```

**Expected**: singleton returns the same instance twice; transient returns new each time; the
`Mailer` dependency is supplied without a manual `new`.

## Scenario 3 — Config precedence (US2, SC-003)

```php
// defaults ship app.name = "Corex"
Config::get('app.name');                 // "Corex"  (defaults)
update_option('corex_app_name', 'Opt');  // options layer
Config::get('app.name');                 // "Opt"    (option > default)
// add APP_NAME=Env to .env at repo root
Config::get('app.name');                 // "Env"    (.env > option > default)
Config::get('does.not.exist', 'fb');     // "fb"     (missing → fallback)
```

**Expected**: value follows `.env` → option → default precedence in all four combinations; missing
key returns the fallback with no error.

## Scenario 4 — Malformed `.env` stays non-fatal (FR-014, SC-008)

```bash
printf 'THIS IS NOT VALID === \n' >> .env      # break the .env
curl -s -o /dev/null -w "%{http_code}\n" http://corex.local/   # still 200, no white screen
```

**Expected**: site boots (HTTP 200); `wp/wp-content/debug.log` records the malformed-`.env` problem;
with `WP_DEBUG` on, one dismissible admin notice appears in `/wp-admin/`. Config falls back to
options/defaults. (Restore `.env` afterward.)

## Scenario 5 — Declarative hooks (US3, SC-004)

A subscriber implementing `SubscribesToHooks::hooks()` maps `['init' => 'onInit', 'the_title' =>
['filterTitle', 20, 1]]`. After its provider boots:

**Expected**: `do_action('init')` runs `onInit`; `apply_filters('the_title', $t)` runs `filterTitle`
at priority 20 with 1 arg; the subscriber is container-resolved (its deps injected); registering the
same subscriber twice does not double-fire.

## Scenario 6 — Controller auto-discovery (US4, SC-005)

```bash
# Add a controller with zero registry edits:
#   plugins/corex-core/src/Controllers/PingController.php  (class PingController {})
wp --path=wp eval 'var_dump(\Corex\Boot::app()->container()->has(\Corex\Controllers\PingController::class));'
```

**Expected**: `true` — placing the file in `Controllers/` is sufficient for discovery + resolution.
A non-controller file dropped in the same dir is ignored; an empty `Controllers/` still boots.

## Acceptance → scenario map

| Success criterion | Scenario |
|---|---|
| SC-001 boot clean in 5 contexts | 1 |
| SC-002 DI service in ≤5 lines | 2 |
| SC-003 config precedence 100% | 3 |
| SC-004 declarative hook, 1 declaration | 5 |
| SC-005 controller, zero registry edits | 6 |
| SC-006 one boot per request | 1 |
| SC-007 headless tests, no optional plugins | `composer test -- --group=unit` |
| SC-008 malformed `.env` non-fatal + notice | 4 |
