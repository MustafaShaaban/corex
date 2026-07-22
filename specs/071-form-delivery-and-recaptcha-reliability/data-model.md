# Data Model: Form Delivery & reCAPTCHA v3 Reliability

No new database tables. Two new value objects, one new persisted meta projection, one extended
configuration array, and one transient-backed guard. Everything below is immutable unless noted.

## ChallengeContext (new — `Corex\Security\ChallengeContext`)

The server-known facts a verification is judged against. Constructed by `ProtectionStage` from the
resolved flow, never from the request.

| Field | Type | Notes |
|---|---|---|
| `expectedAction` | `string` | From `CaptchaAction::forFlow($slug, $override)`. The value the browser must have used. |
| `threshold` | `float` | `form ?? global ?? 0.3`, clamped `0.0..1.0`. |
| `allowedHostnames` | `list<string>` | Normalised exact hostnames; defaults to `[home_url() host]`. |
| `remoteIp` | `?string` | For the provider call; never persisted. |

## ChallengeVerification (new — `Corex\Security\ChallengeVerification`)

The typed verdict `challenge()` returns. Carries no raw token and no raw provider payload (FR-005/019).

| Field | Type | Notes |
|---|---|---|
| `outcome` | `string` | One of the outcomes below. |
| `score` | `?float` | Present for v3 passes and score failures; null otherwise. |
| `effectiveThreshold` | `float` | The threshold actually applied — persisted as evidence. |
| `expectedAction` | `string` | Echoed for the audit trail. |
| `hostname` | `?string` | The hostname the provider reported (safe to store). |
| `safeReason` | `string` | Admin-facing, redacted; e.g. "Score 0.10 below threshold 0.30." |

**Outcomes:** `passed`, `token_missing`, `provider_error`, `malformed_response`,
`provider_rejected`, `hostname_mismatch`, `action_mismatch`, `token_expired`, `token_replayed`,
`score_below_threshold`, `not_configured`.

`passed()` ⇒ `outcome === 'passed'`. Only a `passed` verdict lets the pipeline proceed; every other
outcome fails the protection stage closed (FR-007).

## FlowConfiguration.protection (extended — `Corex\Forms\Flow\FlowConfiguration`)

A 7th public readonly array, appended last with a `[]` default so existing positional construction
is unaffected (FR-025).

```php
protection: [
    'captcha'   => 'inherit'|'on'|'off',   // default 'inherit' — follow global captcha.driver
    'action'    => ?string,                 // null → CaptchaAction derives from slug
    'threshold' => ?float,                  // null → global captcha.score_threshold
]
```

**Checksum rule (R8):** omitted from `checksum()`'s canonical document when `=== []`. A flow that has
never declared protection keeps its existing SHA-256; only an explicit declaration changes it.

`FlowConfigurationValidator` normalises: `captcha` to the enum (unknown → `inherit`); `action` through
`CaptchaAction::normalise()` or null; `threshold` clamped to `0.0..1.0` or null.

## NotificationDelivery (new — `Corex\Forms\Submission\NotificationDelivery`)

The typed outcome of one notification attempt. Mapped from `MailResult::$state`; the vocabulary is
`MailResult`'s, extended only by `not_attempted` (R4).

| Field | Type | Notes |
|---|---|---|
| `status` | `string` | `not_attempted`, `accepted`, `captured`, `queued`, `sending`, `sent`, `failed`, `rejected`, `bounced`, `opened`. |
| `attemptId` | `?string` | v4 UUID linking to the Email Studio attempt when one exists (FR-016). |
| `provider` | `?string` | e.g. `corex-mail`, `wp-mail`. Not a hostname. |
| `attemptedAt` | `?DateTimeImmutable` | Null iff `not_attempted`. |
| `retryable` | `bool` | Whether a resend is offered. |
| `safeReason` | `string` | Redacted admin-facing reason (FR-019). |
| `reasonCode` | `string` | Stable machine code for filtering/producers. |

### Status mapping

| `MailResult::$state` / condition | `NotificationDelivery::$status` |
|---|---|
| no mailer bound, no recipients required | `not_attempted` |
| `wp_mail()` → `true` | `accepted` *(never `sent` — FR-015)* |
| `wp_mail()` → `false` | `failed` |
| capture driver stored it | `captured` |
| queue accepted the job | `queued` |
| routing/policy refused before an attempt | `rejected` |
| `MailResult` any other state | passed through unchanged |

### Persistence

Post meta `corex_notification_delivery` on `corex_submission` — a JSON projection of the fields above.
The existing `corex_email_json` is still written unchanged (backward compatibility). A submission with
**neither** key hydrates as `outcome unavailable` (FR-018), never success.

## Timeline event (unified — meta `corex_submission_timeline`)

One shape, the repository's, replacing the two incompatible ones (R7):

```php
['id' => int, 'submission_id' => int, 'stage' => string, 'outcome' => string,
 'summary' => array, 'created_at' => string]
```

New `stage` value `notification` with `outcome` ∈ the delivery statuses. Legacy rows written in the
old `{kind, state, occurred_at}` shape are hydrated on read (mapped `kind→stage`, `state→outcome`,
`occurred_at→created_at`), never dropped.

## TokenReplayGuard state (new — transient, not a table)

| Key | Value | TTL |
|---|---|---|
| `corex_captcha_seen_{hash_hmac('sha256', token, wp_salt('auth'))}` | `1` | expiry window + margin (~150s) |

The plaintext token is never stored. Bounded by TTL; no cleanup job (R9).

## What is deliberately *not* modelled here

- No duplicate of Email Studio attempt data inside the submission — only the `attemptId` linkage
  (owner request §5.3).
- No new submission store — CPT + meta is unchanged.
- No persisted captcha token, score history, or provider payload beyond the safe fields above.
