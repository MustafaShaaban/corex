# Contract: `wp corex reset`

## Synopsis

```
wp corex reset [--hard] [--yes-i-mean-it] [--dry-run] [--yes]
```

## Options

| Flag | Effect |
|---|---|
| _(none)_ | **Soft reset** (default): deactivate Corex add-ons, clear `corex_*` options + flags, remove seeded demo content. |
| `--hard` | Select **full/hard reset** (DB wipe → fresh Corex starter). Destructive. |
| `--yes-i-mean-it` | The **typed safeguard** required for `--hard`. Without it, `--hard` refuses and changes nothing. |
| `--dry-run` | Print the ordered plan and perform nothing (works for both modes). |
| `--yes` | WP-CLI's standard non-interactive confirmation (still required for the wipe, in addition to the safeguard). |

## Behaviour

- **Soft** (`wp corex reset`): runs the soft plan; prints a summary of deactivated add-ons, cleared options, and
  removed demo content. No safeguard needed. No effect on non-Corex content.
- **Full** (`wp corex reset --hard --yes-i-mean-it --yes`): wipes the DB and restores the *fresh Corex starter*
  (see spec). Without `--yes-i-mean-it`, it **refuses** (prints what it would do, exits without changing
  anything).
- **Dry-run** (`... --dry-run`): prints the plan for the selected mode and exits, changing nothing.
- **WP-CLI absent**: the command is not registered (planner/gate still unit-tested).

## Exit / output

- Success: a `WP_CLI::success` summary of actions performed (or, for dry-run, the planned actions).
- Gate refusal (full without safeguard): a `WP_CLI::warning`/`error` explaining the missing `--yes-i-mean-it`,
  with **no** side effects. Non-zero exit.
- The command never performs the DB wipe unless `ResetGate::permits()` is true.

## Examples

```bash
wp corex reset --dry-run                          # preview the soft reset
wp corex reset                                    # soft reset
wp corex reset --hard --dry-run                   # preview the full reset (no wipe)
wp corex reset --hard                              # REFUSED — missing --yes-i-mean-it
wp corex reset --hard --yes-i-mean-it --yes       # full reset → fresh Corex starter
```
