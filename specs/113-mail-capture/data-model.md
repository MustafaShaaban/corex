# Data model: Mail stays where it should

No table or schema version changes.

## Site environment (computed, not stored)

| Field | Values | Notes |
|---|---|---|
| `environment` | `development`, `staging`, `production` | research R1 |
| `decidedBy` | `dotenv`, `network`, `option`, `mode`, `wordpress` | the layer that answered |
| `locked` | bool | true for `dotenv`, `network`, `option` |

## Operations mode (option `corex_operations_mode`, unchanged)

Five values. `current()` applies the lock (research R2). The mode log (`corex_operations_mode_log`, `{time, user, from,
to}`) is unchanged and records every change.

## Capture scope (option `corex_mail_capture_scope`, new, autoloaded)

`all` (default when missing) or `corex`. Config key `mail.capture_scope`.

## Captured email record (`corex_email_asset`, type `captured_email`)

Existing payload: `capture_id`, `attempt_id`, `to`, `cc`, `bcc`, `reply_to`, `subject`, `body`, `html_body`,
`plain_text`, `headers`, `captured_at`, `retention_until`. New:

| Field | Values | Default when missing (older records) |
|---|---|---|
| `path` | a path name, at most 191 characters | `""`, shown as "Email Studio" |
| `state` | `captured`, `held_for_test` | `captured` |

Deleted by the daily clean-up once the post is more than 30 days old (research R7), and with its submission's
attempt ids on permanent delete (unchanged).

## Mail outcome (computed, sent with the Email Studio index)

`{state: delivered|captured|blocked, environment, decided_by, locked, provider, provider_configured, live, scope:
all|corex, exceptions: list<string>}` (research R9).

## Refused mail (activity event)

Area `email`, kind `mail.refused`, outcome `denied`, sensitivity `personal`, target type `mail`, target id and label the
path name, context `{to: list<string>, subject: string}`, retention 30 days.

## Flow test result

`SubmissionPipelineResult` gains `heldEmails: list<{to: list<string>, subject: string, capture_id: string, path:
string}>`, empty outside a test.
