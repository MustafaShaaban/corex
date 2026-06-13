# Phase 1 — Data Model: 044 Admin control panel & integrations

No new persistent storage. Captcha gains a few fields in the **existing** settings option (via `SettingsStore`).
Everything else is a **derived** value object computed at render time. Pure unless noted.

## DomainStatus (pure value — `Corex\Config\ControlPanel\DomainStatus`)

| Field | Type | Meaning |
|---|---|---|
| `domain` | string | `brand` / `mail` / `forms` / `captcha` / `insights` / `updates` / `integrations` / `addons` |
| `label` | string | translatable display title |
| `status` | enum | `configured` \| `needs_setup` \| `error` |
| `missing` | list<string> | human labels of the missing/invalid required items (empty when configured) |
| `setupLink` | string | URL/anchor to the place to fix it (and "how to get this" where relevant) |

**Rules** (per D2): `configured` = required fields present + valid; `needs_setup` = a required field empty for the
chosen feature; `error` = a recorded failed test. Honeypot/none captcha and an absent-but-optional key →
`configured`. Computed from injected Config values — pure.

## OnboardingStep (pure — `Corex\Config\ControlPanel\OnboardingChecklist`)

| Field | Type | Meaning |
|---|---|---|
| `label` | string | the step ("Set a mail from-address", "Choose a captcha driver", …) |
| `domain` | string | the domain it belongs to |
| `done` | bool | true when that domain is `configured` |
| `link` | string | deep-link to the domain card |

`OnboardingChecklist::steps(ControlPanelStatus)` returns the not-done steps + an `allSet(): bool`.

## Captcha configuration (extends the existing settings — `SettingsRegistry` captcha section)

| Config key | Type | Notes |
|---|---|---|
| `captcha.driver` | select | none / honeypot / recaptcha / turnstile / hcaptcha (existing) |
| `captcha.site_key` | text | **NEW** — public site key (shown) |
| `captcha.secret` | password | write-only (existing) |
| `captcha.score_threshold` | number | **NEW** — reCAPTCHA v3 only (0.0–1.0) |
| `captcha.action` | text | **NEW** — reCAPTCHA v3 action name |

Persisted via the existing `SettingsStore`; the screen reveals the key/v3 fields only for the chosen driver.

## CaptchaDiagnostic (pure — `Corex\Config\Captcha\CaptchaDiagnostic`)

`classify(driver, outcome): { kind, message }` where `kind ∈ { ok, missing_keys, invalid_keys, network_error,
not_applicable }`. Carries **no secret**.

## SiteUrlReachability (pure — `Corex\Config\Insights\SiteUrlReachability`)

`isPublic(string $url): bool` — false for `localhost`, `*.local`, loopback, and private IPv4/IPv6 ranges.

## PsiDiagnostic (pure — `Corex\Config\Insights\PsiDiagnostic`)

`classify(url, httpStatus, body): Diagnostic` →

| Field | Type | Meaning |
|---|---|---|
| `kind` | enum | `ok` \| `local_url` \| `http_error` \| `quota` \| `invalid_key` \| `invalid_response` |
| `message` | string | translatable, actionable user message + next action |
| `detail` | string | **admin-only** raw status/body excerpt, scrubbed of any `key=`/token (never a secret) |
| `keyAdvice` | enum | `optional` \| `recommended` — for the "is the key required?" hint + docs link |

## Addon manifest (extended — `Corex\Config\Addons\Addon`)

Existing fields **plus**: `summary`, `description`, `provides` (list of `{type, label}` for blocks/CPTs/routes/
settings/features), `requires` (list of `{type, label}` for plugins/flags/keys), `enableBehavior`,
`disableBehavior`, `needsConfiguration` (bool), `missingKeys(config)` (derived), `docsUrl`. Additive — existing
registrations default these to empty/false, so nothing breaks.

## Diagnostic result on the wire

Both test actions answer with the spec-043 `ResponseEnvelope`: success → `{ ok:true, message, data:{ kind } }`;
failure → `{ ok:false, code:<kind>, message, details:{ … admin-only … } }`. **No secret** in either. Contracts:
[contracts/test-actions.md](./contracts/test-actions.md) and [contracts/domain-status.md](./contracts/domain-status.md).
