# Contract — Response Envelope (JSON wire shape)

Every response-emitting Corex endpoint MUST return one of these two shapes. Backward-compatible superset of the
current forms response (`{ ok, message, errors, values }`).

## Success

```json
{
  "ok": true,
  "message": "Thanks — your message was sent.",
  "data": {}
}
```

- `ok` MUST be `true`.
- `message` SHOULD be a human-readable, translatable string (MAY be empty).
- `data` MUST be present (object; MAY be empty `{}`).
- HTTP status: `200`.
- **Backward-compat note**: where an endpoint previously returned a `values` key (the forms submit), that value is
  carried inside `data` and ALSO mirrored at top level `values` for one release.

## Error — validation

```json
{
  "ok": false,
  "code": "validation_failed",
  "message": "Please check the highlighted fields.",
  "errors": {
    "email": "Enter a valid email address.",
    "message": "This field is required."
  },
  "details": {}
}
```

- `ok` MUST be `false`; `code` MUST be `"validation_failed"`.
- `errors` MUST be an object of `fieldName → translatable message`.
- HTTP status: `422`.

## Error — general

```json
{
  "ok": false,
  "code": "captcha_failed",
  "message": "Verification failed. Please try again.",
  "details": {}
}
```

- `ok` MUST be `false`; `code` MUST be a stable machine string.
- `errors` MAY be omitted/empty (not a field-level failure).
- `details` MAY carry explicitly-safe context; HTTP status: `403` for auth/nonce/forbidden, else `400`.

## Hard rules

- A response MUST NOT contain any secret (API key, token, raw internal exception text) — only the keys above
  (FR-004 / SC-006).
- A success MUST NOT carry `code`/`errors`; an error MUST NOT carry `data`.
- Unknown `code` values are valid; clients fall back to `message` for display.
- The shape is identical across forms, admin actions, insights, and data endpoints (SC-002).
