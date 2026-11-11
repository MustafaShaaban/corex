# Data model: Settings that save what you changed

No table changes. Stored values keep their option names and formats.

## Settings page (`SettingsPages`, code only)

| Field | Meaning |
|---|---|
| key | `general`, `email`, `captcha`, `media`, `insights`, `guides`, `advanced` |
| title | translated name, shown in the sub-navigation and as the page heading |
| description | one translated line under the heading |
| sections | registry section keys saved by this page, in order |
| addon | for `captcha`, `media`, `guides`: the plugin file, the add-on's slug and its translated name |

Rules: a page belongs to exactly one key; every registry section is on exactly one page; `corex_tab=<section>`
resolves to the page that holds it.

## Field state (`FieldState`, per request)

| Field | Meaning |
|---|---|
| shown | what the control shows (the value in force when locked; a refused value as typed after a save) |
| saved | the stored value Discard restores (`''` for a secret) |
| lock | a `SettingLock` or null |
| secretSaved | a secret has a stored value |

## Setting lock (`SettingLock`)

| Field | Meaning |
|---|---|
| by | `dotenv` or `network` |
| value | the value in force, as a string (never drawn for a secret) |

## Save result (`SettingsSaveResult`, per-user transient `corex_settings_saved_<user id>`, 120 s)

| Field | Meaning |
|---|---|
| page | the page key saved |
| refused | list of `{name, label, type, typed}` (as spec 106 story 5) |
| removed | list of removed secret keys |

Read once by the next render of that page, then deleted.

## Provider test record (option `corex_provider_tests`, not autoloaded)

`{ "<provider>": { "at": <unix time>, "worked": bool, "reason": "<words>", "fingerprint": "<hmac>" } }` for
`captcha`, `pagespeed`, `cloudflare`. Values used per provider: captcha `captcha.driver`, `captcha.site_key`,
`captcha.secret`; pagespeed `insights.psi.key`; cloudflare `insights.cloudflare.token`,
`insights.cloudflare.account_id`. A record whose fingerprint differs from the values in force is treated as absent.

## Library conversion run (option `corex_media_conversion`, not autoloaded)

`{ "job_id": <int>, "failures": [ { "file": "<basename>", "reason": "<words>" } ] }`, failures capped at 100. The job
itself is a `BoundedJob` of kind `media-webp-regeneration` (total, processed, succeeded, failed, state, finished at);
skipped = processed − succeeded − failed.

States shown: never run; queued or running (with counts); completed; cancelled; failed (with the job's error).

## Activity events (area `settings`)

| Kind | Target | Context |
|---|---|---|
| `settings.secret_removed` | the setting key, its label | none (never the value) |
| `media.conversion_started` | `media-library`, job id | total |
| `media.conversion_finished` | job id | state, converted, skipped, failed |
| `media.conversion_cancelled` | job id | converted, skipped, failed |
