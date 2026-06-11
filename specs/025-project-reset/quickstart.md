# Quickstart: Project reset CLI (025)

> Validates the feature end-to-end. The destructive full reset is **not** run against the dev DB here — its
> gate is proven by the refusal path + unit tests.

## Prerequisites

- The Corex install at `./wp` (see PROGRESS "Environment quick reference"); WP-CLI with `--path=wp`.

## 1. Unit tests (pure planner + gate — no WP, no DB)

```bash
vendor/bin/pest tests/Unit/Cli/ResetPlannerTest.php tests/Unit/Cli/ResetGateTest.php
```

Expected: the planner builds the soft plan (deactivate add-ons → remove demo → delete options) and the full
plan (single `db-wipe`); the gate permits soft always and full only when `confirmed`. All green.

## 2. Dry-run (changes nothing)

```bash
wp corex reset --dry-run --path=wp
wp corex reset --hard --dry-run --path=wp
```

Expected: each prints the ordered plan for its mode and exits; `wp option list` / `wp plugin list` show **no**
change.

## 3. The safety gate (full reset refuses without the safeguard)

```bash
wp corex reset --hard --path=wp        # expect: REFUSED, "missing --yes-i-mean-it", no changes
```

Expected: a warning/error, non-zero exit, and the database untouched (verify the site still loads).

## 4. Soft reset (the everyday path)

```bash
wp corex reset --path=wp
```

Expected: Corex add-ons deactivated (`wp plugin list` shows them inactive), `corex_*` options gone
(`wp option list --search='corex_*'` empty), the seeded demo Home removed and the front-page settings reverted —
while any non-Corex post/page/user is unchanged.

## 5. Integration test (soft-reset executor on ./wp)

```bash
composer test:integration -- --filter=ResetExecutor
```

Expected: the executor deactivates a test add-on, deletes a seeded `corex_*` option, and removes a seeded demo
page on the real install — green. (The `db-wipe` path is gated and not exercised against the dev DB.)
