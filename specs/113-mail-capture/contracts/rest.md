# REST changes: Mail stays where it should

## `GET corex/v1/email-studio` (changed)

The response is CoreX's envelope (`{"ok": true, "data": {...}}`); `data` gains:

```json
{
  "outcome": {
    "state": "captured",
    "title": "Captured: nothing leaves this site",
    "detail": "Each email is kept under Delivery logs instead of being sent, and nobody receives it.",
    "facts": ["Environment: Development (set by the operations mode)", "Live delivery: on", "Provider: wp-mail", "Captures: all mail from this site"],
    "environment": "development",
    "decided_by": "mode",
    "locked": false,
    "provider": "wp-mail",
    "provider_configured": true,
    "live": true,
    "scope": "all",
    "exceptions": []
  }
}
```

`delivery.environment` now carries the resolver's answer (`development`, `staging` or `production`).
`counts.window` is `100`: the counts are of the last 100 attempts. Each item of `captures` gains `path` and `state`.
`captures` and `attempts` are the newest 100, newest first.

## `POST corex/v1/email-studio/capture-scope` (new)

Permission: Email Studio's manage check and the REST nonce (as the other Email Studio writes).

Body: `{"scope": "all" | "corex"}`. Answers `200 {"scope": "...", "outcome": {...}}`; `400 invalid_capture_scope` for
any other value.

## `POST corex/v1/flows/{id}/test` (changed)

The response's result gains `held_emails`: `[{to, subject, capture_id, path}]`. The email stage's message says the
emails were held, not sent.
