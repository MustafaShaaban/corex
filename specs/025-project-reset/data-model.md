# Data Model: Project reset CLI (025)

All entities are pure PHP value objects / services (no WordPress). The command layer constructs them from WP
state and renders the result; only the executor mutates WP.

## ResetRequest (value object)

| Field | Type | Notes |
|---|---|---|
| `mode` | `'soft' \| 'full'` | `soft` is the default; `full` is opt-in (`--hard`) |
| `dryRun` | `bool` | when true, the command prints the plan and performs nothing |
| `confirmed` | `bool` | true only when the typed safeguard `--yes-i-mean-it` was passed |

## ResetInventory (value object)

| Field | Type | Notes |
|---|---|---|
| `addonPlugins` | `list<string>` | active `corex-*` plugin files (excludes Corex core + theme) |
| `optionKeys` | `list<string>` | every `corex_*` option to delete (incl. `corex_features_*`, `corex_setup_demo_seeded`) |
| `demoPageId` | `int\|null` | the seeded Home page id, when `corex_setup_demo_seeded` is set; else null |

## ResetAction (value object)

| Field | Type | Notes |
|---|---|---|
| `kind` | `'deactivate-addon' \| 'delete-option' \| 'remove-demo' \| 'db-wipe'` | the action category |
| `target` | `string` | plugin file / option key / page id / `'database'` |
| `label` | `string` | human-readable summary line (for dry-run + report) |

## ResetPlan (value object)

| Member | Type | Notes |
|---|---|---|
| `actions` | `list<ResetAction>` | ordered (see ordering below) |
| `isDestructive()` | `bool` | true iff any action is `db-wipe` |
| `summary()` | `string` | one line per action (used by dry-run + the post-run report) |

**Action ordering**:
- **Soft**: `deactivate-addon`* → `remove-demo` (revert front page + delete seeded page) → `delete-option`*
  (options last, so flag/marker reads during the earlier steps stay valid).
- **Full**: a single `db-wipe` action (the wipe + reinstall + theme activation supersedes the granular steps).

## ResetPlanner (pure service)

`plan(ResetRequest $request, ResetInventory $inventory): ResetPlan`
- soft → builds deactivate/remove-demo/delete-option actions from the inventory.
- full → builds the single `db-wipe` action (the inventory is not needed to enumerate granular deletes).
- `dryRun` does not change the plan — only how the command consumes it.

## ResetGate (pure service)

`permits(ResetRequest $request): bool`
- `soft` → always `true`.
- `full` → `true` **only if** `request->confirmed` is true; otherwise `false` (fail-closed).

## ResetExecutor (WP/DB boundary — integration-tested for soft; wipe gated)

`apply(ResetAction $action): void` per kind:
- `deactivate-addon` → `deactivate_plugins($file)`
- `delete-option` → `delete_option($key)`
- `remove-demo` → revert `show_on_front`/`page_on_front`, `wp_delete_post($id, true)`
- `db-wipe` → reset DB + reinstall WP core + activate the Corex theme (the *fresh starter*) — **only invoked
  for a permitted full plan**.
