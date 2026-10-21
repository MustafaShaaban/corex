# REST contracts: Access and logs

All routes are under `corex/v1`, need the REST nonce, and answer the CoreX envelope used by spec 079's access routes.
Unchanged routes are not repeated.

## Access requests

### `POST /access/requests` (changed)

A request whose expanded abilities include a locked or critical ability, or an area that contains one, is refused
before anything is stored, in the route's existing 400 shape:

```json
{ "code": "rest_invalid_param", "message": "<translated>", "data": { "status": 400,
  "params": { "ability": "<translated>" },
  "details": { "ability": { "code": "ability_not_requestable", "message": "<translated>", "data": null } } } }
```

For an area the parameter is `area` and the code `area_not_requestable`.

### `POST /access/requests/{id}/preview` (new) · `manage_options`

Answer (`preview` is `null` when the request is no longer pending):

```json
{ "data": { "preview": { "request_id": 41, "requester": { "id": 7, "name": "Sara Ahmed" },
  "reason": "<the requester's words>", "area": "data",
  "abilities": [ { "key": "corex_manage_data", "label": "Manage data", "risk": "dangerous" },
                 { "key": "corex_export_personal_data", "label": "Export personal data", "risk": "sensitive" } ],
  "blockers": [], "requires_typed_name": true, "typed_name": "corex_manage_data",
  "expiry_choices": [7, 30, 90], "expiry_default": 30, "target_hash": "<sha256 of the request id and its abilities>" } } }
```

The typed name is the ability's key: the same in every language, and what the denied page names.

### `POST /access/requests/{id}/decision` (changed) · `manage_options`

Body on approval: `{ "approved": true, "note": "<non-empty>", "expiry_days": 30, "confirmation": { "operation_kind":
"access.request.approve", "target_hash": "<from the preview>", "actor_id": 1, "expires_at": "<ISO 8601>",
"required_phrase": "<typed_name, only when requires_typed_name>" } }`. Body on refusal of the request:
`{ "approved": false, "note": "<optional>" }`.

The answer is `200` with `data.result`; a refusal has `state` `blocked` and one of these error codes, and grants
nothing: `note_required`, `expiry_invalid`, `confirmation_invalid` (wrong or missing typed name, another request, or
expired), `access_change_blocked` (an `AccessPolicy` blocker), `request_not_requestable` (the request is cancelled),
`request_not_pending`, `request_expired`. A confirmation that cannot be read is `400` `confirmation_malformed`.

## Grants (new) · `manage_options`

### `GET /access/grants`

```json
{ "data": { "grants": [ { "id": 41, "person": { "id": 7, "name": "Sara Ahmed" }, "area": null,
  "abilities": [ { "key": "corex_manage_forms", "label": "Manage forms and flows", "risk": "sensitive" } ],
  "approver": { "id": 1, "name": "Admin" }, "approved_at": "2026-10-20T10:00:00+00:00",
  "note": "Covering forms this month", "expires_at": "2026-11-19T10:00:00+00:00",
  "in_effect": true, "state_note": null } ] } }
```

`expires_at` is `null` for grants approved before this release ("no expiry (granted before expiries existed)").
`state_note` is `removed_outside_corex` when `in_effect` is false. A grant to an account that no longer exists is not
listed.

### `POST /access/grants/{id}/revoke`

Body `{}`. Answer `200` with `data.result`: `state` `completed`, or `blocked` with `grant_not_standing` when it had
already ended. An ability another standing grant still gives the same person is kept.

## Public submissions in Maintenance (changed behaviour, no new route)

Routes that declare the public-submission marker: `forms/{slug}`, `flows/{id}/submit`, `newsletter/subscribe`,
`bookings/request`, `careers/apply`, `account/register`. In Maintenance, for anyone but a signed-in administrator (and
unless the `corex_maintenance_bypass` filter passes the request):

```http
HTTP/1.1 503 Service Unavailable
Retry-After: 3600
```

```json
{ "code": "site_in_maintenance", "message": "<translated: the site is in maintenance; try again later>",
  "data": { "status": 503 } }
```

No handler runs: nothing is stored, mailed or counted. `account/login`, `account/reset-request` and `account/reset`
are not marked and keep working. In Coming soon every route behaves as today.

## Blog Pro (changed permissions)

| Route | Permission |
|---|---|
| every route except `share-click` | `edit_posts` (base) |
| `GET blog/analytics`, `GET blog/share-controls`, `GET blog/comments` (one post) | also `edit_post` on that post |
| `POST blog/editorial/{id}/transition` | also `edit_post` on the post; to `published` or `scheduled` also `publish_post` |
| `POST blog/comments/{id}/moderate`, `action` `approve`, `spam`, `trash` or `reply` | also `moderate_comments` |
| `POST blog/comments/{id}/moderate`, `action` `edit` | also `edit_comment` on that comment |
| `GET blog/authors` | `edit_others_posts` for every row; otherwise only the viewer's own row |
| `POST blog/share-click` | unchanged access; answers `{ "recorded": false }` and stores nothing |

A refusal is `403` with a translated message naming what is not allowed.

## Data screen

Sources declared with an owner ability (role ability grants, access requests, sign-in attempts) are omitted from the
catalogue for an actor without that ability, and every list, detail, query, export and download of them answers:

```json
{ "ok": false, "code": "data_forbidden", "message": "<translated>" }
```

An export set run by such an actor keeps the model as a part with `left_out: true`, `left_out_because: "restricted"`
and the model's `label`, and never reads it; the file's summary says "Left out, because it belongs to a screen you do
not have access to" with the labels. An export set made only of such models is refused `403` `data_forbidden` with a
message naming them (CoreX makes no file for an export that would give nothing).

## Inbox resend

### `POST /submissions/{id}/resend` (changed)

Body `{ "attempt_id": "..." }` (unchanged). Answer adds `recipients` (full addresses, shown to the inbox user) and
`basis`: `recorded` or `rechecked`. Refusal 409 `recipients_unknown` when the email's recipients are neither recorded
nor confirmed by re-checking.

### Submission payload (changed)

Each related email binding gains `kind` (`confirmation`, `team_notification`, `failure_notice`), `resendable`
(boolean) and `not_resendable_reason` (`not_retryable`, `recipients_unknown`, or null).
