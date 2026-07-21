# Research: Form Delivery & reCAPTCHA v3 Reliability

Everything below was established by reading the code and the provider's public contract *before*
design, so the plan rests on verified behaviour rather than recalled behaviour. Each item names its
source.

## R1 — Why every protected submission is currently rejected

**Source:** `plugins/corex-forms/src/Submission/Stages/ProtectionStage.php:33-34`,
`plugins/corex-forms/src/Block/FlowBlockRenderer.php:92-116`,
`addons/corex-captcha/src/RemoteCaptcha.php:31-49`.

`ProtectionStage` reads `$context->values['captcha_token'] ?? ''`. `FlowBlockRenderer::flowForm()`
renders exactly one hidden input — the honeypot (`corex_hp`, L97) — and no `captcha_token` field.
Nothing enqueues a provider script anywhere in the tree (`grep` for `recaptcha/api.js`, `grecaptcha`,
`wp_enqueue_script.*captcha` → zero front-end hits). So the token is structurally always `''`, and
`RemoteCaptcha::verify()` returns `false` on an empty token (L33). The moment `captcha.driver` is
set to `recaptcha`, `turnstile`, or `hcaptcha`, every submission fails the protection stage.

**Decision:** the fix is not "loosen the check" — the check is correct. The browser must be given a
token to send. → WS2.

## R2 — The threshold and action settings have no consumer

**Source:** `addons/corex-captcha/src/RemoteCaptcha.php:46-48`,
`plugins/corex-config/src/Settings/SettingsRegistry.php:176-190`.

`RemoteCaptcha::verify()` returns `is_array($body) && ($body['success'] ?? false) === true`. It reads
neither `score`, `action`, nor `hostname`. `captcha.score_threshold` and `captcha.action` are stored
settings that no code path reads. This is the definition of a dishonest control.

**Decision:** a boolean return type has nowhere to carry a score, so the contract must widen before
the settings can mean anything. Extend, don't break: `VerifyingChallenge extends ChallengeVerifier`,
mirroring the shipped `AttemptingMailer extends Mailer` precedent
(`plugins/corex-core/src/Mail/AttemptingMailer.php`). → WS1.

## R3 — reCAPTCHA v3 verification response shape

**Source:** Google reCAPTCHA v3 public documentation (siteverify response). Linked, not paraphrased,
in the Captcha guide per docs-guard Rule 8.

A siteverify response for v3 carries `success` (bool), `score` (0.0–1.0), `action` (string),
`challenge_ts` (ISO8601), `hostname` (string), and `error-codes` (array). The action and hostname are
**attacker-influenced only through a valid token for this site**, which is why they must be checked
against server-known expectations, not trusted. `challenge_ts` gives token age; Google tokens expire
after 2 minutes, so a local expiry window of ~120s matches the provider without being stricter than
it.

**Decision:** validate in the order in the plan (§WS1 table). Replay is checked *after* authenticity
so a forged token cannot poison the store; the fingerprint is recorded only on an otherwise-passing
token so a score rejection stays retryable.

## R4 — The canonical delivery vocabulary already exists

**Source:** `plugins/corex-core/src/Mail/MailResult.php:21-29` and
`addons/corex-email/src/Delivery/EmailAttempt.php:21-28`.

`MailResult` defines `accepted, captured, queued, sending, sent, failed, rejected, bounced, opened`,
with `successful()` = {accepted, captured, queued, sent, opened} and `terminal()` = {captured, sent,
failed, rejected, bounced, opened}. `EmailAttempt` uses the same set minus `accepted`.

**Decision:** map form-level delivery onto `MailResult::$state` and add only `not_attempted`.
Inventing a parallel `enum {saved_sent, saved_failed, …}` would create two vocabularies for one fact,
which the owner request explicitly forbids ("If the existing CoreX Mail typed attempt system uses
canonical names, reuse them"). → WS3.

## R5 — The flow path has no wp_mail fallback; the legacy path does

**Source:** `plugins/corex-forms/src/Submission/FlowEmailSender.php:32-53`,
`plugins/corex-forms/src/Listeners/SendEmailListener.php` (detect-and-defer ladder ending in
`wp_mail()`), `plugins/corex-forms/src/Submission/Stages/EmailStage.php:95-104`.

`FlowEmailSender::send()` returns `null` when `$this->mailer === null` (L34), i.e. whenever
`RoutedMailer` is unbound — which is exactly when `corex-email` is inactive. `EmailStage::outcome()`
records that as the string `'unrouted'` and moves on. No `wp_mail()` is attempted. The legacy
event-listener path (`SendEmailListener`) *does* fall back to `wp_mail()`. So the same site sends a
notification for a legacy-registered form and silently sends nothing for a visual-builder flow.

**Decision:** extract the ladder from `SendEmailListener` into a shared `NotificationDispatcher`
service and use it from both paths. → WS3.

## R6 — The stage order already satisfies "save before send"

**Source:** `FormsServiceProvider::register()` stage wiring
(`ValidationStage → ProtectionStage → StorageStage → RoutingStage → EmailStage → InboxStage →
TimelineStage`); `EmailStage::persist()` (L126-131) updates metadata on an already-stored submission.

`StorageStage` runs before `EmailStage`, and no stage opens a transaction spanning both. FR-012 is
therefore already true by construction.

**Decision:** no reordering. Add tests that *prove* the submission survives each mail failure mode
(FR-013), because "true by construction" is only true until someone adds a transaction. → WS3 tests.

## R7 — The timeline writes a shape the admin cannot read

**Source:** `plugins/corex-forms/src/Submission/Stages/TimelineStage.php` (writes
`{kind, state, occurred_at, is_test, flow_version_id}`), `plugins/corex-config/src/Submissions/
SubmissionTimelineRepository.php` (reads `{id, submission_id, stage, outcome, summary, created_at}`),
both keyed on post meta `corex_submission_timeline`.

The admin timeline renders `stage`/`outcome`/`created_at`; the pipeline writes `kind`/`state`/
`occurred_at`. The pipeline's own `flow.submitted` event therefore renders blank. It does not error
because `nextId()` reads `(int) ($event['id'] ?? 0)` and tolerates the missing key.

**Decision:** one shared shape (the repository's, since two screens already consume it); hydrate
legacy rows of the old shape rather than dropping them. → WS4.

## R8 — Checksum stability constrains the protection field

**Source:** `plugins/corex-forms/src/Flow/FlowConfiguration.php:41-69`.

`checksum()` hashes a canonical, key-sorted document over all six arrays. Any new key changes the
hash of every published version. Stored checksums exist on live sites (the field is compared on
publish to detect drift).

**Decision:** the 7th array is omitted from the canonical document when empty, so untouched flows
keep their existing checksum; only a flow that declares protection gets a new hash. → WS2,
`DECISIONS.md`.

## R9 — Replay state must outlive a single PHP request without a new table

**Source:** owner request §4.5 ("must work across simultaneous requests, not only within one PHP
object instance"; "bounded and prunable"; "no new cleanup burden if avoidable"); existing transient
usage in `plugins/corex-core/src/Http/Middleware/ThrottleMiddleware.php`.

Transients are shared across requests (object cache or options table) and self-expire. A TTL equal to
the token expiry window + margin means a consumed token cannot be replayed within its own validity,
and expired tokens fail the age check anyway — so no separate cleanup job is needed and no table is
added.

**Decision:** `TokenReplayGuard` keys on `hash_hmac('sha256', $token, wp_salt('auth'))` (never the
plaintext token) with a bounded TTL. → WS1.

## R10 — The FluentSMTP boundary must be evidence-based, not scraped

**Source:** owner request §6; WordPress core `wp_mail_from` / `wp_mail_from_name` filters;
`is_plugin_active()`.

Reliable, non-sensitive signals exist without touching the plugin's internals: whether a
`wp_mail_from` filter is registered, whether the configured CoreX From domain matches the site
domain, and whether the plugin is active. These answer "is a sender override likely?" honestly.
Reading FluentSMTP's option rows or decrypting its credentials would answer more precisely but
violates FR-027 and the constitution's optional-dependency rule.

**Decision:** `TransportAdvisory` uses public signals only; absence of evidence yields general
guidance, never a fabricated detection. → WS5.
