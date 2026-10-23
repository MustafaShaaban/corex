# REST contract: Email Studio routes changed or added by spec 120

All under `corex/v1`, with Email Studio's permission callback and, for POST, the REST nonce (`X-WP-Nonce`), as every
Email Studio route. Envelope: `{ ok, data, error }` (`Corex\Http\ResponseEnvelope`).

## POST `/email-studio/templates/{id}/preview` (new)

Draws the draft being edited with sample values, as it would be sent.

Body: the draft fields `subject`, `from_name`, `from_address`, `html_body`, `plain_text`, `plain_text_mode`,
`layout_id`, `layout_version`; optional `samples` (object, key → string, used for this draw only); optional
`direction` (`ltr` or `rtl`).

200: `{ subject, html, text, samples: [{ key, label, value }], warnings: { field: sentence } }`

422 `email_template_unrenderable`: `details.fields` names each problem (`variables`: "Unknown variable: nonsense.key.";
`partials`: "The partial \"promo\" is not available."; `fromAddress`, `fromName`, `html`, `subject`).

404 `email_template_not_found`.

## POST `/email-studio/test` (changed)

With `template_id`: body as the preview, plus `to` (one address, or a list of one). Draws the same email, puts
"[Test] " before its subject, and sends it from the template's From with its plain text under the site's delivery rules.

Without `template_id`: the earlier free-body test (`to`, `subject`, `body`), kept for callers that post one.

200: `{ result: MailResult, summary: "Captured: nothing left this site", reason, log_url }`

422 `email_test_invalid_address` ("Enter a valid email address: not-an-address is not one."), or
`email_template_unrenderable` as above. Nothing is sent in either case.

## POST `/email-studio/templates/{id}/reset` (new)

Saves the default template's shipped content as a new version, and activates it when the template is active.

200: `{ template, version }` · 404 when the template is not a default (`email_template_not_default`) or not found.

## POST `/email-studio/templates/{id}/draft` (changed)

`from_address` is optional. 422 field errors name the field and, for variables, each unknown key. 201 adds
`warnings`: `{ "variables": "user.name is not available in a form notification." }`, `{ "fromAddress": <transport
warning> }`. `variable_keys` is ignored: the keys are read from the content.

## GET `/email-studio` (changed)

`variables` becomes a list of groups: `[{ key, label, variables: [{ key, label, sample }] }]`. Each attempt gains
`missing_variables` (list of keys). Each template gains `is_default`.
