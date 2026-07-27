# The Access Request raw-JSON navigation, reproduced

Reproduced on the running install on 2026-07-28, signed in as a real subscriber
(`corex-e2e-subscriber`, role `subscriber`) — the actor the Access Denied screen exists for.

## What the user does

1. Opens `wp-admin/admin.php?page=corex-settings`.
2. Gets the CoreX Access Denied screen, which renders correctly:
   *"You don't have access to this area — Your role doesn't include the `manage_options` capability…"*
3. Types a reason into the form and presses **Request access**.

## What the user gets

```
URL:          http://corex.local/wp-json/corex/v1/access/requests
Content-Type: application/json
```

```json
{"data":{"result":{
  "operation_id":"fa6fe8f5-e366-4c01-87e7-78ed83c46d98",
  "state":"completed",
  "message":"The access request was created.",
  "errors":[],
  "affected_ids":[148],
  "started_at":"2026-07-27T21:20:16+00:00",
  "finished_at":"2026-07-27T21:20:16+00:00",
  "audit_event_id":4258
}}}
```

A JSON document. No CoreX design, no heading, no navigation, no way back.

## The cause, in one line

`plugins/corex-core/src/Admin/AdminPage.php:305`

```php
'</div><form class="corex-denied__request" method="post" action="'
    . esc_url(rest_url('corex/v1/access/requests')) . '">'
```

A plain HTML form whose `action` is a REST endpoint. The controller is correct — it returns JSON,
because that is what a REST endpoint does. The browser is correct — it navigates to the action and
renders what it receives. Nothing here is broken; the two are simply wired to each other, and the
result is a user reading an operation envelope.

## The part that makes it worse

**The request succeeded.** `state: completed`, `affected_ids: [148]`, and an audit event was written.
The user's access request is genuinely waiting for an administrator — and the user has no way to know
that, because the only thing they were shown was `operation_id`.

A failure would at least look like a failure. This looks like the product breaking at the exact
moment somebody is asking for help.

## Fields exposed to an end user

`operation_id`, `state`, `message`, `errors`, `affected_ids`, `started_at`, `finished_at`,
`audit_event_id` — from `OperationResult::toArray()`. Internal operation plumbing, an audit row ID,
and two raw ISO timestamps that spec 076 would have rejected on any admin screen.

## Screenshots

- `access-denied.png` — the denial screen, which is well designed
- `after-request-raw-json.png` — what the same user sees one click later
