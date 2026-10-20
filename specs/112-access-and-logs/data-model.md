# Data model: Access and logs

**Spec**: [spec.md](spec.md) · **Research**: [research.md](research.md)

## Access request (table `{prefix}corex_access_requests`, foundation schema 3 → 4)

Existing columns: `id`, `requester_id`, `ability_key`, `area_key`, `reason`, `state`, `reviewer_id`, `review_note`,
`reviewed_at`, `notification_attempt_id`, `created_at`, `expires_at` (the request's own seven-day life).

New nullable columns:

| Column | Type | Meaning |
|---|---|---|
| `grant_expires_at` | datetime | When an approved request's grant ends. `NULL` on rows approved before this release: "no expiry (granted before expiries existed)" (FR-007) |
| `revoked_at` | datetime | When an administrator revoked the grant |
| `revoked_by` | bigint unsigned | Who revoked it |
| `ended_reason` | varchar(20) | `revoked` or `expired` |
| `cancel_reason` | varchar(64) | Why a pending request was cancelled (`not_requestable` for FR-008) |

States (unchanged names): `pending` → `approved` | `denied` | `expired` (the request's own life) | `cancelled`.
An approved row is a **grant** while `revoked_at` is null and (`grant_expires_at` is null or in the future).

Validation:

- On approval: `review_note` non-empty; `expiry_days` ∈ {7, 30, 90}; `grant_expires_at = reviewed_at + expiry_days`.
- A request whose expanded abilities include a locked or critical ability is never stored (FR-001).
- Removing a granted ability first checks no other live grant gives it to the same person.

## Access-denied entry (option `corex_access_audit_log`, autoload off)

| Field | Meaning |
|---|---|
| `user` | the refused person |
| `kind` | `screen` (a CoreX admin page) or `data` (a restricted Data screen model) |
| `section` | the page slug, or the data source key |
| `first`, `last` | first and last refusal of the window, Unix time |
| `count` | refusals in the window |

Merged when `user`, `kind` and `section` match and the refusal is within 3,600 seconds of `first`. At most 20 entries
per person, then the existing global cap; entries older than 30 days are pruned (DECISIONS #114). Entries in the old
shape `{time, user, kind, section}` are read as `first = last = time`, `count = 1`.

## Submission email record (submission meta, key unchanged)

Each binding (`submitter_confirmation`, `team_notification`, `admin_failure`) gains:

| Field | Meaning |
|---|---|
| `recipients` | the resolved addresses the email was sent to, as a list (FR-032) |

Bindings written before this release have no `recipients`; Resend re-resolves and checks them against the email
attempt's stored hash (FR-032a). The field is personal data of the submission: removed by retention, trash and
permanent delete, included in the personal-data export.

## Managed table declaration (`CORE/Database/Schema/ManagedTable.php`)

New optional last constructor argument `ownerAbility` (string or null). Set to `corex_manage_access` for the role
ability grants and access requests tables, `corex_manage_operations` for sign-in attempts; `null` everywhere else.
Read by the Data layer through a `RestrictedDataSource` interface.

## Events and notifications

| Event | When | Notifies |
|---|---|---|
| `AccessGrantEndedEvent` (`ended_reason` `revoked` or `expired`) | a grant is revoked or expires | for `expired`: the person and the approver (approver skipped if deleted) |
| Activity kinds `access.grant.revoked`, `access.grant.expired`, `access.request.cancelled` | same | recorded in the activity stream, area `access` |
