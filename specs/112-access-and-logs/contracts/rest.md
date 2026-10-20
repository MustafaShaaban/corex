# REST contracts: Access and logs

All routes are under `corex/v1`, need the REST nonce, and answer the CoreX envelope used by spec 079's access routes.
Unchanged routes are not repeated.

## Access requests

### `POST /access/requests` (changed)

A request whose expanded abilities include a locked or critical ability, or an area that contains one, is refused
before anything is stored:

```json
{ "code": "rest_invalid_param", "message": "<translated>", "data": { "status": 400,
  "details": { "code": "ability_not_requestable", "message": "An administrator grants this through the role matrix." } } }
```

### `POST /access/requests/{id}/preview` (new) · `manage_options`

Answer:

```json
{ "request_id": 41, "requester": { "id": 7, "name": "Sara Ahmed" }, "reason": "<the requester's words>",
  "abilities": [ { "key": "corex_manage_data", "label": "Manage data", "risk": "dangerous" } ],
  "area": null, "blockers": [], "requires_typed_name": true, "typed_name": "Manage data",
  "expiry_choices": [7, 30, 90], "expiry_default": 30, "target_hash": "<sha256>" }
```

### `POST /access/requests/{id}/decision` (changed) · `manage_options`

Body on approval: `{ "approved": true, "note": "<non-empty>", "expiry_days": 30, "confirmation": { ... } }`, where
`confirmation` has operation kind `access.request.approve`, the preview's `target_hash`, and `phrase` equal to
`typed_name` when `requires_typed_name` is true. Refusals: 400 `note_required`, 400 `expiry_invalid`, 409
`confirmation_mismatch` (wrong phrase or stale hash), 409 `policy_blocked` (any `AccessPolicy` blocker). Body on
refusal of the request: `{ "approved": false, "note": "<optional>" }`.

## Grants (new) · `manage_options`

### `GET /access/grants`

```json
{ "grants": [ { "id": 41, "person": { "id": 7, "name": "Sara Ahmed" }, "abilities": ["corex_manage_forms"],
  "approver": { "id": 1, "name": "Admin" }, "approved_at": "2026-10-20T10:00:00Z", "note": "Covering forms this month",
  "expires_at": "2026-11-19T10:00:00Z", "in_effect": true, "state_note": null } ] }
```

`expires_at` is `null` for grants approved before this release (shown as "no expiry (granted before expiries
existed)"). `state_note` is `removed_outside_corex` or `overridden_by_role` when `in_effect` is false.

### `POST /access/grants/{id}/revoke`

Body `{}`. Answer `{ "revoked": true, "removed_abilities": ["corex_manage_forms"], "kept_abilities": [] }`;
`kept_abilities` lists any ability still given by another live grant.

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
{ "code": "data_forbidden", "message": "<translated>", "data": { "status": 403 } }
```

An export set run by such an actor leaves the model out and its result says, for each one, its label and
"left out because it needs <ability label>".

## Inbox resend

### `POST /submissions/{id}/resend` (changed)

Body `{ "attempt_id": "..." }` (unchanged). Answer adds `recipients` (full addresses, shown to the inbox user) and
`basis`: `recorded` or `rechecked`. Refusal 409 `recipients_unknown` when the email's recipients are neither recorded
nor confirmed by re-checking.

### Submission payload (changed)

Each related email binding gains `kind` (`confirmation`, `team_notification`, `failure_notice`), `resendable`
(boolean) and `not_resendable_reason` (`not_retryable`, `recipients_unknown`, or null).
