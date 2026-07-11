# REST API Contract

Namespace: `corex/v1`. Every mutation requires authentication, its declared CoreX ability, and request authenticity middleware. Controllers validate request shape, call one service operation, and return the common envelope.

## Common Envelope

```json
{
  "data": {},
  "meta": {
    "requestId": "uuid",
    "page": 1,
    "perPage": 20,
    "total": 0
  },
  "errors": []
}
```

Error items contain a stable code, safe message, optional field path, and retryable flag. Secret values, raw credentials, hidden personal data, stack traces, and provider secrets are forbidden.

## Activity

- `GET /activity` — query accessible events by area, kind, outcome, actor, date, and page
- `GET /activity/{id}` — accessible event detail with redaction

## Abilities and Access

- `GET /abilities` — grouped definitions and current actor state
- `GET /access/roles` — role matrix for CoreX abilities
- `POST /access/preview` — preview grant/revoke changes and lockout risks
- `POST /access/apply` — apply a matching confirmed preview
- `GET /access/requests` — accessible requests
- `POST /access/requests` — create request
- `POST /access/requests/{id}/decision` — approve or deny pending request
- `POST /access/grants` — preview or apply send/grant workflow

## Add-ons

- `GET /addons` — descriptors, real runtime/update/dependency state
- `POST /addons/{slug}/preview` — enable/disable dependency impact
- `POST /addons/{slug}/state` — apply matching confirmed state change
- `POST /addons/{slug}/update-check` — refresh a real configured update source

## Flows

- `GET /flows` — search/filter/page flows
- `POST /flows` — create draft
- `GET /flows/{id}` — draft/published summary and permissions
- `PATCH /flows/{id}` — optimistic draft update using version/checksum
- `POST /flows/{id}/preview` — render visitor preview
- `POST /flows/{id}/publish` — validate and publish immutable version
- `POST /flows/{id}/unpublish`
- `POST /flows/{id}/close`
- `POST /flows/{id}/test` — run full marked-test pipeline
- `GET /flows/extensions` — registered fields, rules, actions, routes, variables, success states

Concurrent draft updates return `409` with the current version and no overwrite.

## Submissions

- `GET /submissions` — query by flow/status/owner/date/search/test with permission-scoped counts
- `GET /submissions/{id}` — complete accessible detail
- `PATCH /submissions/{id}` — status/read/assignment update with optimistic timestamp
- `POST /submissions/{id}/notes` — add internal note
- `POST /submissions/{id}/reply` — safe reply through Email Studio
- `POST /submissions/{id}/resend` — resend related attempt
- `POST /submissions/bulk/preview` — preview selected mutation
- `POST /submissions/bulk/apply` — apply matching preview
- `POST /submissions/exports` — create bounded audited export
- `GET /submissions/exports` — export history

## Data and Models

- `GET /data/sources` — source schemas/capabilities/permissions
- `GET /data/{source}` — query rows
- `GET /data/{source}/{id}` — record detail
- `POST /data/{source}/mutations/preview`
- `POST /data/{source}/mutations/apply`
- `POST /data/{source}/imports` — upload/start mapping
- `PATCH /data/{source}/imports/{id}` — mapping/unknown-column choice
- `POST /data/{source}/imports/{id}/dry-run`
- `POST /data/{source}/imports/{id}/commit`
- `GET /data/{source}/imports/{id}/report`
- `POST /data/{source}/exports`
- `GET /data/{source}/exports`
- `GET /data/migrations`
- `POST /data/migrations/preview`
- `POST /data/migrations/apply`
- `POST /data/migrations/{run}/rollback`

## Email Studio

- `GET /email/overview`
- `GET|POST /email/templates`
- `GET|PATCH /email/templates/{id}`
- `POST /email/templates/{id}/activate`
- `POST /email/templates/{id}/preview`
- `POST /email/templates/{id}/test`
- `GET|POST /email/layouts`
- `GET|PATCH /email/layouts/{id}`
- `GET|POST /email/partials`
- `GET|PATCH /email/partials/{id}`
- `GET /email/variables`
- `GET|POST /email/routes`
- `PATCH /email/routes/{id}`
- `GET /email/attempts`
- `GET /email/attempts/{id}`
- `POST /email/attempts/{id}/resend`
- `GET /email/health`

## Blog Pro

- `GET /blog/analytics` — period and post/author query over real aggregates
- `POST /blog/analytics/event` — public rate-limited consent-aware event; no admin nonce requirement, strict validation and anti-abuse apply
- `GET /blog/editorial`
- `PATCH /blog/editorial/{postId}` — transition/assignment/due date
- `POST /blog/editorial/{postId}/notes`
- `GET /blog/comments`
- `POST /blog/comments/{id}/moderate`
- `GET /blog/authors`
- `GET|PATCH /blog/settings`

## Insights

- `GET /insights/providers`
- `GET /insights/runs`
- `POST /insights/{provider}/run`
- `POST /insights/runs/{id}/retry`
- `GET /insights/recommendations`

## Operations and Security

- `GET /operations`
- `POST /operations/preview`
- `POST /operations/apply`
- `GET /security/login`
- `POST /security/login/preview`
- `POST /security/login/apply`
- `GET /security/login/activity`
- `POST /security/login/lockouts/{id}/release`

## Setup and Settings

- `GET /setup`
- `PATCH /setup/progress`
- `POST /setup/preview`
- `POST /setup/backup`
- `POST /setup/apply`
- `POST /setup/{plan}/rollback`
- `POST /setup/reset/preview`
- `POST /setup/reset/apply`
- `GET /settings`
- `POST /settings/validate`
- `POST /settings/save`
- `POST /settings/actions/{action}/preview`
- `POST /settings/actions/{action}/apply`

## Jobs

- `GET /jobs/{id}` — owner/authorized progress
- `POST /jobs/{id}/cancel` — supported non-terminal jobs only
- `POST /jobs/{id}/retry` — retryable failed jobs only

## HTTP Outcomes

- `200` successful read/update
- `201` created resource or job
- `202` accepted bounded job
- `400` invalid shape
- `401` unauthenticated
- `403` authenticated but denied
- `404` inaccessible or absent resource without existence leakage
- `409` optimistic conflict, stale preview, dependency conflict, or illegal state transition
- `422` semantic validation failure
- `429` throttled public/security endpoint
- `500/502/503` safe internal/provider failure with retry metadata where applicable
