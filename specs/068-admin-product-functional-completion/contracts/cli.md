# CLI Contract

## `wp corex security reset-login`

Purpose: restore a safe login path without loading the protected custom route.

Behavior:

- requires an administrative CLI context
- disables CoreX custom-login endpoint blocking and active lockouts
- preserves unrelated users, roles, passwords, and security settings
- records a security activity event
- reports the restored login URL and whether the unguard constant is active
- is idempotent

## `wp corex media regenerate`

Purpose: run or resume existing-media optimization using current Media settings.

Options:

- `--dry-run`
- `--batch=<count>`
- `--resume=<job-id>`
- `--format=table|json`

Behavior: preserves originals, reports converted/skipped/failed counts, stores resumable job state, and never treats unsupported files as successful conversions.

## `wp corex jobs status [<job-id>]`

Purpose: inspect bounded import/export/media/migration/setup jobs.

Behavior: shows kind, state, progress, result artifact, safe error summary, actor, and timestamps without exposing secrets or restricted payloads.

## `wp corex jobs run <job-id>`

Purpose: process the next bounded step for a queued/paused job.

Behavior: enforces operation authorization recorded at creation, idempotency, current configuration safety, and terminal-state protection.

## Existing Commands

Existing doctor, readiness, mode, setup, version, migration, and generator commands must use the same underlying services as admin actions so results cannot diverge.
