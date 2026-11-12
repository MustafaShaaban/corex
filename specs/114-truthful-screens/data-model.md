# Data model: Screens that tell the truth

No table changes.

## Insight result (option `corex_insights`, `provider => {latest, history[]}`)

| Field | Completed run | Failed run (new) |
|---|---|---|
| `provider`, `label`, `summary`, `checkedAt` | as before | `summary` is the reason |
| `score` | 0–100 | `null` |
| `grade` | A–F | `null` |
| `status` | a health status | `failed` |
| `failure` | absent | the kind: `local_url`, `http_error`, `quota`, `invalid_key`, `invalid_response`, `transport` |
| `metrics`, `recommendations` | lists | empty lists |

A stored row from an earlier release with `score` 50, empty `metrics` and a `summary` that is a failure message is
**read** as a failed row (`failure: legacy`); it is not rewritten.

## Activity events (existing table, new kinds)

| Kind | Area | Outcome | Actor | Target | Context |
|---|---|---|---|---|---|
| `operations.mode.changed` | operations | success | the person (or system) | `mode` / new mode | `from`, `to` |
| `access.denied` | access | denied | the refused person | `screen` / page slug | none |
| `security.login.locked` | security | denied | system | `login` | `locked_until` |
| `addon.enabled`, `addon.disabled` | addons | success or failure | the person | `addon` / slug | `message` on failure |

Sensitivity: security for the first three, public-admin for add-ons. Retention: 180 days, as access events.

## Option `corex_security_events_since`

A Unix time, autoloaded, written once with `add_option` (never updated).

## Overview row

`{label, note, done, tone, href?}`; `note` is "Could not check" when the fact was unreadable.
