# Data Model: Notification Center & Dashboard

Two new managed tables and a set of pure value objects. Everything is immutable unless noted. The
vocabularies are closed, validated in constructors, and secret-free by construction (reusing the
discipline of `Corex\Activity\ActivityEvent::assertNoSecretKeys()`).

## Value objects (`Corex\Notifications\*`, corex-core)

### Notification

The shared record — the *condition*, not one person's view of it.

| Field | Type | Notes |
|---|---|---|
| `id` | `?int` | DB id; null before persistence. `withId()` returns a copy. |
| `uuid` | `string` | v4 UUID; unique. |
| `type` | `string` | Producer-defined typed identifier, `/^[a-z][a-z0-9_.-]*$/` (e.g. `submission.new`). |
| `category` | `NotificationCategory` | One of the closed category set. |
| `severity` | `NotificationSeverity` | critical/error/warning/action/info/success. |
| `sourceModule` | `string` | e.g. `forms`, `email`, `jobs`. |
| `sourceType` | `?string` | e.g. `submission`, `job`. |
| `sourceId` | `?string` | Safe source identifier, or null. |
| `titleKey` / `messageKey` | `string` | i18n keys. |
| `rendered` | `array{title:string,body:string}` | Safe pre-rendered projection for display. |
| `dedupKey` | `string` | Stable grouping key (e.g. `mail.provider.failure:{route}`); unique index. |
| `occurrences` | `int` | ≥1. |
| `firstOccurredAt` / `latestOccurredAt` | `DateTimeImmutable` | latest ≥ first. |
| `createdAt` / `updatedAt` | `DateTimeImmutable` | |
| `expiresAt` | `?DateTimeImmutable` | Retention hint. |
| `resolvedAt` | `?DateTimeImmutable` | Set when the condition ends (≠ user dismissal). |
| `resolutionReason` | `?string` | Safe reason. |
| `environment` | `?string` | local/development/staging/production. |
| `actorId` | `?int` | Who/what caused it, when relevant. |
| `recipient` | `NotificationRecipient` | Targeting (below). |
| `action` | `?NotificationAction` | Direct navigation (below). |
| `metadata` | `array` | Safe structured data; `assertNoSecretKeys()` on construction. |

`record()`-style factory generates `uuid`/timestamps; `withOccurrence(DateTimeImmutable)` increments
`occurrences` + updates `latestOccurredAt`; `resolved(reason, at)` sets resolution.

### NotificationSeverity / NotificationCategory / NotificationStatus (closed enums)

- **Severity**: `critical`, `error`, `warning`, `action`, `information`, `success`.
- **Category**: `submissions`, `email`, `jobs`, `security`, `access`, `operations`, `readiness`,
  `imports_exports`, `editorial`, `setup`, `system`.
- **Status** (per-user, derived): `unread`, `read`, `snoozed`, `dismissed`, `resolved`, `expired`.

Each is a small validated value object (or `const` set + validator) rejecting out-of-vocabulary values
with `InvalidArgumentException`, mirroring `MailResult`/`ActivityEvent`.

### NotificationRecipient

How a notification is targeted — never hard-coded roles (FR-006).

| Kind | Payload | Visible to |
|---|---|---|
| `user` | one user id | that user |
| `users` | list of user ids | those users |
| `ability` | a `corex_*` ability key | holders of that ability |
| `assigned` | source type + id | the assigned user(s) + permitted managers |
| `category_admins` | a category | admins responsible for that category |

`canBeSeenBy(int $userId, callable $userCan): bool` — the single visibility predicate every read and
count re-checks (FR-002). Knowing an id grants nothing.

### NotificationAction

`{ label_key: string, url: string, ability: ?string }` — navigation only (FR-012). The drawer never
mutates; a link renders only if the actor passes `ability`.

### NotificationPreference

Per-user, per-category delivery choice within policy: `inApp: bool`, `criticalEmail: bool`,
`digest: 'off'|'daily'`, `severityFloor: NotificationSeverity`. Mandatory system notifications ignore
the floor (FR-020) and the UI marks them non-disableable.

## Tables (`Corex\Config\Notifications\*`, corex-config)

Built with the fluent `Table`/`Migrator` primitives, registered as `ManagedTable`s, following
`Activity/ActivityTable.php` exactly.

### `notifications` (physical `{prefix}corex_notifications`)

Columns: `id`, `uuid`(36), `type`, `category`, `severity`, `source_module`, `source_type`(nullable),
`source_id`(nullable), `title_key`, `message_key`, `rendered_json`(text), `dedup_key`,
`occurrences`(int), `first_occurred_at`, `latest_occurred_at`, `created_at`, `updated_at`,
`expires_at`(nullable), `resolved_at`(nullable), `resolution_reason`(nullable), `environment`(nullable),
`actor_id`(nullable), `recipient_json`(text), `action_json`(nullable text), `metadata_json`(text).

Indexes: **unique** `uuid`; **unique** `dedup_key` (drives grouping/upsert); `category`; `severity`;
`latest_occurred_at`; `expires_at`; `resolved_at`; `actor_id`.

### `notification_user_state` (physical `{prefix}corex_notification_user_state`)

Columns: `id`, `notification_id`(int), `user_id`(int), `read_at`(nullable), `dismissed_at`(nullable),
`snoozed_until`(nullable), `acknowledged_at`(nullable), `created_at`, `updated_at`.

Indexes: **unique** composite (`notification_id`,`user_id`); `user_id`; `snoozed_until`.

**Schema install:** add both schemas to `installFoundationSchema([...])` in `ConfigServiceProvider::boot()`,
register both `ManagedTable`s, and bump `FOUNDATION_SCHEMA_VERSION` `'2'` → `'3'` — else the early
return skips creation.

## Repository contract (`NotificationRepository`, corex-core)

- `upsertByDedupKey(Notification): Notification` — insert, or if the dedup key exists and is unresolved,
  increment occurrences + update latest/summary + return the merged record (FR-011).
- `find(int $id): ?Notification`
- `queryForActor(NotificationQuery $q, int $actorId, callable $userCan): NotificationPage` — bounded,
  indexed, visibility-filtered; `per_page` clamped (≤100).
- `unreadCountForActor(int $actorId, callable $userCan): int` — bounded aggregate, cache-eligible.
- Per-user mutations: `markRead/markUnread/dismiss/snooze/acknowledge(int $notificationId, int $userId)`
  — each asserts visibility first.
- `resolve(dedupKey, reason, at)` / `reopen(dedupKey)` — condition lifecycle (FR-010).
- `pruneExpired(DateTimeImmutable $now, int $limit)` — select-ids-then-delete-in, bounded
  (implements `Corex\Retention\PrunableStore`).

The WP adapter (`WpNotificationRepository`) copies `WpActivityRepository`'s prepared-statement
discipline (`%s`/`%d`/`%i`), `MAX_PAGE_SIZE = 100`, UTC handling, whitelisted filter columns, and
select-then-delete pruning.

## What is deliberately not modelled

- No per-user copy of the notification body — one shared record + a thin per-user state row.
- No raw IPs, tokens, credentials, or provider payloads (FR-004; `assertNoSecretKeys`).
- No unbounded history — permanent audit stays in Activity; notifications are pruned (FR-022).
