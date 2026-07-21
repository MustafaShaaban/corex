# Implementation Plan: Form Delivery & reCAPTCHA v3 Reliability

**Branch**: `spec/071-form-delivery-and-recaptcha-reliability` | **Date**: 2026-07-20 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/071-form-delivery-and-recaptcha-reliability/spec.md`

## Summary

Repair a live regression and close a silent data-visibility gap in the CoreX Forms pipeline, then
add real reCAPTCHA v3 support on top.

The pipeline's *stage order is already correct* — `StorageStage` runs before `EmailStage`, so
FR-012 is largely satisfied by construction and mostly needs proving. The defects are elsewhere:
the captcha contract is too weak to express a v3 verdict, the browser is never given the means to
produce a token, the flow path has no `wp_mail()` fallback, and the delivery outcome is an
unchecked string that no admin screen reads.

Approach: extend contracts rather than break them (the established `Mailer` → `AttemptingMailer`
precedent), reuse the canonical `MailResult` vocabulary rather than invent a second one, and drive
front-end asset loading from a declarative registry the renderer populates rather than from
scanning page content.

## Technical Context

**Language/Version**: PHP 8.3+, JavaScript (buildless ES5-compatible front-end runtime; `@wordpress/element` for admin)

**Primary Dependencies**: WordPress 7.0+. Google reCAPTCHA v3 is an **optional external service**, never a hard dependency (Principle IX).

**Storage**: No new tables. Submissions remain CPT `corex_submission` + post meta; one new normalised meta projection (`corex_notification_delivery`). Replay state is transient-backed and TTL-bounded.

**Testing**: Pest (unit + integration), Jest (JS), Playwright (E2E)

**Target Platform**: WordPress front end (public forms) + admin (settings, inbox), single-site and multisite

**Performance Goals**: No added latency on unprotected pages — zero provider requests, zero enqueues. One provider round trip per protected submission, 10s timeout, never on page render.

**Constraints**: reCAPTCHA v3 requires client-side execution; there is no honest server-only fallback. The verification must fail closed without ever making the provider a hard dependency.

**Scale/Scope**: 5 workstreams, ~22 source files across `corex-core`, `corex-captcha`, `corex-forms`, `corex-config`.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

Derived from `.specify/memory/constitution.md` (Corex Constitution v1.2.1).

- [x] **I. Theme is a skin** — PASS. No theme changes.
- [x] **II. Plugins boot themselves** — PASS. `corex-captcha` and `corex-email` are optional add-ons; every new seam degrades to a working, unprotected-but-honest form when they are absent (FR-006 scenario 6).
- [x] **III. Thin controllers, fat services** — PASS. `FlowSubmissionController` is unchanged; verification logic lives in the captcha add-on, delivery logic in a new `NotificationDispatcher` service, persistence in the existing repository.
- [x] **IV. Everything injected** — PASS. `RecaptchaV3Captcha`, `TokenReplayGuard`, `CaptchaHostnamePolicy`, and `NotificationDispatcher` are all container-bound. **One existing violation is fixed**: `CaptchaResolver::resolve()` (L45-53) currently constructs drivers with `new` on every call — it becomes a bound factory rather than a service that news up collaborators inside a method.
- [x] **V. Runtime tokens** — PASS. Submission-status indicators use existing `--corex-admin-*` tokens and the established `is-{tone}` row convention. No raw hex.
- [x] **VI. Conditional assets** — PASS, and this is the load-bearing requirement (FR-001/SC-003). See §Deviation below for the one hook choice that needs justifying.
- [x] **VII. Declarative security** — PASS. Fail-closed verification (FR-007); the expected action comes from server state, never the request (FR-004); the secret never reaches the client (FR-005); honeypot and throttle middleware are untouched (FR-006).
- [x] **VIII. RTL-first** — PASS. Status indicators and the delivery filter use logical properties; verified RTL (SC-009).
- [x] **IX. No optional dep is hard** — PASS. Google is optional, `corex-captcha` is optional, `corex-email` is optional. All three resolved lazily; absence produces a working form and an honest state, never a fatal.
- [x] **X. Spec is source of truth** — PASS. `spec.md` and this plan written before any code. Learning from the 070 checklist finding, `spec.md` is deliberately stakeholder-level and **all** mechanics live here.
- [x] **Guard Gate + Definition of Done** — acknowledged. `wp-guard` + `clean-code-guard` on all PHP/JS, `test-guard` on tests, `docs-guard` on docs. Tests, i18n, RTL, WCAG 2.2 AA, docs, PROGRESS, DECISIONS before any diff is presented.

**Environment Gate**: verified 2026-07-20 — `wp --path=wp theme list` shows `corex` active;
`corex-core`, `corex-blocks`, `corex-config`, `corex-forms`, `corex-captcha`, `corex-email` all
active; boots with no fatals (`COREX_CORE_VERSION=0.34.0`).

### Deviation requiring justification — Principle VI

Principle VI says block assets are declared in `block.json` and load when the block renders. The
captcha script cannot be, for a structural reason: whether a form needs it is a **runtime**
property of the resolved flow's stored configuration, not a static property of the block. A
`block.json` declaration would load the provider script on every page containing *any* form,
including unprotected ones — the exact behaviour FR-001 forbids.

Resolution: the renderer declares its need into `ProtectedFormRegistry`; a `wp_footer`-hooked
controller enqueues once if and only if the registry is non-empty. Blocks render during
`the_content`, which runs *after* `wp_enqueue_scripts`, so the footer is the earliest correct hook.
This is *more* conditional than `block.json` would be, not less. To be recorded in `DECISIONS.md`.

## Project Structure

### Documentation (this feature)

```text
specs/071-form-delivery-and-recaptcha-reliability/
├── spec.md
├── plan.md              # This file
├── research.md          # Core/provider behaviour established before design
├── data-model.md        # ChallengeVerification, NotificationDelivery, protection config
├── contracts/
│   ├── extension-api.md # VerifyingChallenge + NotificationDispatcher seams
│   └── ui-state.md      # The six delivery states and their presentation
├── quickstart.md
├── checklists/
│   └── requirements.md
├── tasks.md
└── evidence.md          # Phase A gate record
```

No `contracts/rest-api.md`: no REST route changes shape. `FlowSubmissionController::sanitizeShape()`
already whitelists `captcha_token`, and the submissions routes gain filter values, not new contracts.

### Source Code (repository root)

```text
plugins/corex-core/src/
├── Security/
│   ├── ChallengeVerifier.php          # WS1 — unchanged (BC)
│   ├── VerifyingChallenge.php         # WS1 — new, extends ChallengeVerifier
│   ├── ChallengeContext.php           # WS1 — new
│   └── ChallengeVerification.php      # WS1 — new typed result
└── Mail/MailResult.php                # read-only — the canonical vocabulary

addons/corex-captcha/src/
├── CaptchaResolver.php                # WS1 — v3 routing; stop newing in resolve()
├── RemoteCaptcha.php                  # WS1 — unchanged (turnstile/hcaptcha)
├── RecaptchaV3Captcha.php             # WS1 — new
├── CaptchaAction.php                  # WS2 — new, shared by renderer + verifier
├── CaptchaHostnamePolicy.php          # WS1 — new
├── TokenReplayGuard.php               # WS1 — new
├── CaptchaAssetController.php         # WS2 — new
└── assets/corex-captcha-v3.js         # WS2 — new, buildless

plugins/corex-forms/src/
├── Flow/FlowConfiguration.php         # WS2 — 7th array, checksum-compatible
├── Flow/FlowConfigurationValidator.php# WS2 — validate/normalise protection
├── Block/FlowBlockRenderer.php        # WS2 — captcha_token input (L92-116)
├── Block/ProtectedFormRegistry.php    # WS2 — new
├── Submission/Stages/ProtectionStage.php # WS1 — typed verdict
├── Submission/Stages/EmailStage.php   # WS3 — typed persistence
├── Submission/Stages/TimelineStage.php# WS4 — shared event shape
├── Submission/FlowEmailSender.php     # WS3 — distinguish null cases
├── Submission/NotificationDispatcher.php # WS3 — new, the shared ladder
├── Submission/NotificationDelivery.php   # WS3 — new typed result
└── Listeners/SendEmailListener.php    # WS3 — reuse the shared ladder

plugins/corex-config/src/
├── Settings/SettingsRegistry.php      # WS5 — captcha section (L117-190)
├── Email/TransportAdvisory.php        # WS5 — new
└── Submissions/…, src/admin/Submissions/… # WS4 — status surfacing
```

**Structure Decision**: Existing layout. Contracts land in `corex-core` so `corex-forms` never
depends on the optional `corex-captcha` or `corex-email` packages directly.

## Workstreams

### WS1 — A verdict richer than a boolean (FR-003/004/007/010/011, P1)

`ChallengeVerifier::verify(string $token): bool` cannot express *why* a submission failed, which is
why the threshold and action settings have no consumer — a boolean has nowhere to put a score.

Following the `Mailer` → `AttemptingMailer` precedent exactly:

- `ChallengeVerifier` is **unchanged**. `HoneypotCaptcha`, `NullCaptcha`, and `RemoteCaptcha` keep
  working untouched.
- `VerifyingChallenge extends ChallengeVerifier` adds
  `challenge(string $token, ChallengeContext $context): ChallengeVerification`.
- `ProtectionStage` uses `challenge()` when the resolved driver implements it and falls back to
  `verify()` otherwise.

`RecaptchaV3Captcha` validates **in this order**, failing closed at the first failure:

| # | Check | Failure outcome |
|---|---|---|
| 1 | token non-empty | `token_missing` |
| 2 | transport succeeded (`is_wp_error`) | `provider_error` |
| 3 | body decodes to an array | `malformed_response` |
| 4 | `success === true` | `provider_rejected` (carries `error-codes`) |
| 5 | `hostname` in the exact allowlist | `hostname_mismatch` |
| 6 | `action` === server-derived expectation | `action_mismatch` |
| 7 | `challenge_ts` within the expiry window | `token_expired` |
| 8 | fingerprint not already consumed | `token_replayed` |
| 9 | `score >= effectiveThreshold` | `score_below_threshold` |

Order matters: replay is checked *after* the token is proven authentic, so a forged token cannot
poison the replay store; and the fingerprint is only recorded on an otherwise-passing token, so a
token rejected for score can be retried after a settings change.

`ChallengeVerification` carries `outcome`, `score`, `effectiveThreshold`, `expectedAction`,
`hostname`, and a `safeReason`. It **never** carries the raw token or the raw provider payload
(FR-019).

**Threshold** defaults to `0.3`, clamped to `0.0..1.0`, per-form override resolved as
`form threshold ?? global threshold ?? 0.3`. Recorded in the submission's evidence so an
administrator can see which value was actually applied.

**Hostname** uses `CaptchaHostnamePolicy` — exact normalised comparison against an explicit
allowlist defaulting to `home_url()`'s host. Never `str_contains`; a substring match would accept
`corex.local.evil.com`.

**Replay** uses `TokenReplayGuard`: key `hash_hmac('sha256', $token, wp_salt('auth'))`, stored as a
transient with TTL = expiry window + margin. No new table, no cleanup job — the TTL *is* the bound,
and transients are shared across concurrent requests, satisfying "not only within one PHP object
instance". The plaintext token is never stored.

**`CaptchaResolver` fix**: `resolve()` currently returns `new RemoteCaptcha(...)` inline (L45-53), a
Principle IV violation. It becomes a factory whose collaborators are injected.

### WS2 — Give the browser something to prove (FR-001/002/005/008/009/023/024/025, P1)

**The declaration.** `FlowConfiguration` gains a 7th public readonly array, `protection`, defaulted
to `[]` so every existing positional construction still compiles:

```php
'protection' => [
    'captcha'   => 'inherit'|'on'|'off',  // default 'inherit'
    'action'    => ?string,                // null → derived
    'threshold' => ?float,                 // null → global
]
```

**Checksum backward compatibility is load-bearing.** `FlowConfiguration::checksum()` (L41-53) hashes
a canonical document; adding a key changes every published version's SHA-256 and would invalidate
stored checksums across every live site. `checksum()` therefore **omits `protection` entirely when
it is `[]`**, so untouched flows keep their existing hash. Only a flow that actually declares
protection gets a new one. To be recorded in `DECISIONS.md`.

**The action.** `CaptchaAction::forFlow(string $slug, ?string $override): string` normalises to
`[A-Za-z0-9_/-]`, bounds length at 100, and prefixes derived values with `corex_form_`. The *same
function* is called by the renderer and by `ProtectionStage`, which is what makes FR-004 structurally
true — the browser and the server cannot disagree because they compute the same value from the same
input. The server never reads an action from the request.

**Conditional loading.** `FlowBlockRenderer::flowForm()` (L92-116) calls
`ProtectedFormRegistry::declare($slug, $action)` only when the flow resolves as protected *and* the
provider is configured, then renders one extra input beside the existing honeypot (L97):

```html
<input type="hidden" name="captcha_token" value="" data-corex-captcha-action="…">
```

`captcha_token` is already whitelisted in `FlowSubmissionController::sanitizeShape()`, and
`corex-runtime.js`'s `collect()` harvests `input[name]`, so the value reaches the server with no
transport changes.

`CaptchaAssetController` hooks `wp_footer` and, only if the registry is non-empty, enqueues
`https://www.google.com/recaptcha/api.js?render={siteKey}` **once** plus the buildless
`corex-captcha-v3.js`, localised with `{siteKey, forms: {slug: action}}` — site key only, never the
secret (FR-005).

**The token.** `corex-captcha-v3.js` intercepts submit, calls `grecaptcha.execute(siteKey, {action})`
for *that form's* action, writes the fresh token into the hidden input, and submits. A per-form
in-flight flag prevents double submission. Failure to obtain a token produces a recoverable message
via `Corex.notices.status()` and leaves the form submittable (FR-009). No token is ever generated at
page load, so FR-002 holds by construction.

### WS3 — An outcome that was actually checked (FR-012–FR-018/FR-020, P1)

**`FlowEmailSender::send()` returns `null` for three different reasons** (L34-36 no mailer, L40-42 no
recipients, and by fall-through), and `EmailStage::outcome()` (L95-104) flattens all of them to the
string `'unrouted'`. Those are not the same event: no mailer is *not attempted*, no recipients is
*rejected*. Separating them is most of FR-014.

`NotificationDispatcher` becomes the single detect-and-defer ladder, extracted from
`SendEmailListener::dispatch()` where it already exists and *added* to the flow path where it does
not:

```
RoutedMailer          → dispatch(trigger, context)
AttemptingMailer      → attempt(request)          → MailResult
Mailer                → send(request)             → accepted
wp_mail()             → true → accepted | false → failed
```

**This closes the silent-no-send gap.** Today, with `corex-email` inactive, `FlowEmailSender` returns
`null` at L34 and the flow simply sends nothing (SC-006).

`NotificationDelivery` maps `MailResult::$state` onto the persisted vocabulary and adds
`not_attempted`. **The canonical vocabulary is reused, not re-invented** — `MailResult` already
defines `accepted, captured, queued, sending, sent, failed, rejected, bounced, opened`
(`plugins/corex-core/src/Mail/MailResult.php` L21-29). `wp_mail()` returning `true` maps to
**`accepted`, never `sent`** (FR-015): it means PHP handed the message off, nothing more.

Persisted to a new normalised meta key `corex_notification_delivery` — status, attempt id, provider,
attempted-at, retryable, safe reason, reason code. The existing `corex_email_json` is written
unchanged for backward compatibility. Absent both keys ⇒ `outcome unavailable` (FR-018), never
success.

FR-013 needs no code change but does need proof: `StorageStage` precedes `EmailStage` in the
provider's stage wiring, and no stage wraps the pipeline in a transaction. Tests must assert the
submission survives each failure mode rather than assuming it.

### WS4 — Make it visible (FR-021/FR-022, US3, P1)

**The timeline is broken today and nobody noticed** because the entry it writes renders blank rather
than erroring. `TimelineStage` writes `{kind, state, occurred_at}`; `SubmissionTimelineRepository`
reads `{id, submission_id, stage, outcome, summary, created_at}` — same meta key
(`corex_submission_timeline`), incompatible shapes. `nextId()` reads `(int) ($event['id'] ?? 0)`, so
it tolerates the foreign shape silently.

Collapse onto the repository's shape (it is the one two screens already read), add a `notification`
stage with typed outcomes, and hydrate legacy rows of the old shape rather than dropping them.

Inbox surfacing distinguishes all six states plus legacy-unavailable, using the established
`is-{tone}` row convention with text **and** icon **and** accessible name — never colour alone
(FR-Sc, SC-007). The attempt link renders only when an attempt exists *and* the actor passes the
ability check (FR-022), reusing the existing `SubmissionEmailService::resend()` + `assertRelated()`
guards rather than adding a parallel permission path.

### WS5 — Say who owns what (FR-026/027/028/029, P3)

`TransportAdvisory` reads **only** public, documented signals: the configured CoreX From domain
versus `home_url()`'s host, `has_filter('wp_mail_from')` / `wp_mail_from_name`, and plugin-active
state. No option decryption, no table reads, no private APIs, no credential access whatsoever
(FR-027). Where evidence is absent it returns general guidance and never a fabricated "detected"
state (FR-029).

Captcha settings (`SettingsRegistry` L117-190) gain a hostname allowlist and an expiry window, and
the threshold help is corrected — it currently says "0.5 is a common starting point" for a setting
that has never been read by anything.

## Out of scope

- Turnstile and hCaptcha keep `RemoteCaptcha`'s current `success`-only semantics. Extending them to
  typed verdicts is mechanical once `VerifyingChallenge` exists, but neither is v3-shaped and neither
  was reported broken. Flagged, not absorbed.
- The `Mail rejected: Illegal characters in the subject field.` warning inherited from 070. WS3
  touches the code path that logs it; if the delivery work surfaces the cause it is reported, not
  silently fixed under this spec's banner.

## Complexity Tracking

One deviation, justified above: **Principle VI**, the `wp_footer` enqueue hook. The chosen approach
is strictly more conditional than a `block.json` declaration would be.

Two standing violations are **removed**: `CaptchaResolver::resolve()`'s in-method `new` (Principle
IV), and a settings surface presenting two controls that nothing reads (the honesty requirement in
the Definition of Done).
