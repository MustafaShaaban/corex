---

description: "Task list for Spec 071 — Form Delivery & reCAPTCHA v3 Reliability"
---

# Tasks: Form Delivery & reCAPTCHA v3 Reliability

**Input**: Design documents from `/specs/071-form-delivery-and-recaptcha-reliability/`

**Prerequisites**: [spec.md](./spec.md), [plan.md](./plan.md), [research.md](./research.md), [data-model.md](./data-model.md), [contracts/](./contracts/), [quickstart.md](./quickstart.md)

**Tests**: REQUIRED. The constitution's Definition of Done mandates unit + E2E tests (Pest / Jest / Playwright) that pass, plus the Guard Gate, i18n-readiness, RTL verification, and WCAG 2.2 AA for UI. Every implementation task owes corresponding test task(s).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to

---

## Phase 1: Setup

- [x] **T001** Verify the Environment Gate: `wp --path=wp theme list` shows `corex`; `corex-core`, `corex-forms`, `corex-captcha`, `corex-email` active; `http://corex.local` boots with no PHP fatals. **Blocks everything.** *(Verified 2026-07-20 during startup; re-verify if the box changes.)*
- [x] **T002** Capture a baseline suite count (integration, unit, Jest, Playwright) so any later red is attributable to this branch. Record in `evidence.md`.
- [x] **T003** [P] Confirm guard skills installed (`clean-code-guard`, `wp-guard`, `test-guard`, `docs-guard`, `ui-ux-pro-max`); install any missing.
- [ ] **T004** [P] Register a reCAPTCHA v3 key/secret for the dev host, or record in `evidence.md` that provider-live checks run against a mocked siteverify where a real key is unavailable.

---

## Phase 2: Foundational — the verdict contract (Blocking Prerequisites)

**Purpose**: A boolean cannot carry a score. Everything in US1 depends on a richer verdict existing first. No provider behaviour changes until the contract can express it.

- [x] **T005** [US1] Failing Pest unit tests for `ChallengeVerification` (outcome vocabulary, `passed()`, redaction — asserts no raw token/payload is retained). `tests/Unit/Security/ChallengeVerificationTest.php`.
- [x] **T006** [US1] Add `Corex\Security\ChallengeContext`, `Corex\Security\ChallengeVerification`, and `Corex\Security\VerifyingChallenge extends ChallengeVerifier`. `plugins/corex-core/src/Security/`.
- [x] **T007** [US1] Verify `ChallengeVerifier` is unchanged and existing captcha unit tests still green. Guards: `clean-code-guard`, `wp-guard`.

**Checkpoint**: the verdict type exists; the old boolean contract is untouched.

---

## Phase 3: US1 — A protected form accepts real people (P1) 🎯 MVP

**Goal**: A configured protected form accepts an ordinary visitor and refuses an unproven request. **Independent test**: quickstart §1.

### Tests first

- [x] **T008** [P] [US1] Failing Pest unit tests for `CaptchaAction::forFlow`/`normalise`: deterministic derivation, `corex_form_` prefix, charset normalisation, 100-char bound, override path. `tests/Unit/Captcha/CaptchaActionTest.php`.
- [x] **T009** [P] [US1] Failing Pest unit tests for `CaptchaHostnamePolicy`: exact match passes, substring (`corex.local.evil.com`) fails, allowlist honoured, default = `home_url()` host. `tests/Unit/Captcha/CaptchaHostnamePolicyTest.php`.
- [x] **T010** [P] [US1] Failing Pest unit tests for `TokenReplayGuard`: first use passes, second use of the same fingerprint fails, plaintext token never stored, TTL bounded. `tests/Unit/Captcha/TokenReplayGuardTest.php`.
- [x] **T011** [US1] Failing Pest unit tests for `RecaptchaV3Captcha::challenge` covering the full R3 matrix: valid pass; `success:false`; exact-action match; action mismatch; normalised default action; below-threshold; exact-threshold acceptance; missing token; empty token; malformed body; expired `challenge_ts`; replayed token; hostname match; hostname mismatch; provider/network failure. `tests/Unit/Captcha/RecaptchaV3CaptchaTest.php`.

### Implementation

- [x] **T012** [US1] Add `Corex\Forms\Submission\CaptchaAction`. **Placed in corex-forms, not the captcha add-on** — the action is a Forms concept and the verifier only compares it, so this keeps corex-forms free of a hard add-on dependency (Principle IX). `plugins/corex-forms/src/Submission/CaptchaAction.php`.
- [x] **T013** [P] [US1] Add `Corex\Captcha\CaptchaHostnamePolicy`. `addons/corex-captcha/src/CaptchaHostnamePolicy.php`.
- [x] **T014** [P] [US1] Add `Corex\Captcha\TokenReplayGuard` (transient-backed, HMAC fingerprint, TTL bound). `addons/corex-captcha/src/TokenReplayGuard.php`.
- [x] **T015** [US1] Add `Corex\Captcha\RecaptchaV3Captcha implements VerifyingChallenge`, validating in the R3 order and failing closed. `addons/corex-captcha/src/RecaptchaV3Captcha.php`.
- [x] **T016** [US1] Route `recaptcha` to the v3 driver in `CaptchaResolver`; keep `turnstile`/`hcaptcha` on `RemoteCaptcha`. **Fix the Principle IV violation**: `resolve()` becomes a factory with injected collaborators (replay guard, hostname policy, config) rather than newing them inline (L45-53). `addons/corex-captcha/src/CaptchaResolver.php`, `CaptchaServiceProvider.php`.
- [x] **T017** [US1] Rewrite `ProtectionStage`: call `challenge()` when the driver implements `VerifyingChallenge`, else `verify()`; build `ChallengeContext` from the flow's server-side config (action via `CaptchaAction`, threshold `form ?? global ?? 0.3`, hostnames from policy); persist the typed outcome + effective threshold into `spam` metadata. Honeypot + throttle untouched. `plugins/corex-forms/src/Submission/Stages/ProtectionStage.php`.
- [ ] **T018** [US1] Failing Pest integration test through the real pipeline: a valid v3 token is accepted; a below-threshold token is rejected; honeypot + throttle still fire. `tests/Integration/Forms/ProtectionStageV3Test.php`. *(Unit-level pipeline coverage done in `ProtectionStageTest`; full-pipeline integration deferred to the gate.)*

**Checkpoint**: server-side verification is correct and fully typed. The browser still cannot produce a token — that is Phase 4.

---

## Phase 4: US1 (cont.) — Give the browser something to prove (P1)

### Tests first

- [x] **T019** [P] [US1] Failing Pest unit tests for `FlowConfiguration` checksum stability: a config with `protection === []` hashes identically to the pre-feature six-array config; a declared protection changes the hash. `tests/Unit/Forms/FlowConfigurationProtectionTest.php`.
- [x] **T020** [P] [US1] Failing Jest tests for `corex-captcha-v3.js`: a fresh token is requested per submit (never at load); double-submit is locked; a token-generation failure yields a recoverable status and leaves the form submittable. `tests/corex-captcha-v3.test.js`.
- [x] **T021** [US1] Failing Pest unit test that `ProtectedFormRegistry` deduplicates by slug and reports slug→action.

### Implementation

- [x] **T022** [US1] Extend `FlowConfiguration` with the 7th `protection` array (default `[]`); omit it from `checksum()`'s canonical document when empty. `plugins/corex-forms/src/Flow/FlowConfiguration.php`.
- [x] **T023** [US1] Validate/normalise `protection` (captcha enum, action via `CaptchaAction::normalise`, threshold clamp). **Landed in a dedicated `Corex\Forms\Flow\FlowProtection::normalize()`** called by both the stored-payload reader (`FlowRepository`) and the request mapper (`FlowRestInputMapper`), rather than in `FlowConfigurationValidator` — normalisation at the serialisation boundary keeps the empty-array-omitted checksum guarantee in one place. `plugins/corex-forms/src/Flow/FlowProtection.php`.
- [x] **T024** [US1] Add `Corex\Forms\Block\ProtectedFormRegistry` (request-scoped, container singleton). `plugins/corex-forms/src/Block/ProtectedFormRegistry.php`.
- [x] **T025** [US1] In `FlowBlockRenderer::flowForm()` (L92-116): resolve protection; when protected and configured, `declare()` into the registry and render `<input type="hidden" name="captcha_token" value="" data-corex-captcha-action="…">` beside the honeypot. `plugins/corex-forms/src/Block/FlowBlockRenderer.php`.
- [x] **T026** [US1] Add `Corex\Captcha\CaptchaAssetController`: on `wp_footer`, if the registry is non-empty, enqueue `recaptcha/api.js?render={siteKey}` **once** + `corex-captcha-v3.js`, localise `{siteKey, forms}`. **Site key only, never the secret.** `addons/corex-captcha/src/CaptchaAssetController.php`, wired in `CaptchaServiceProvider`.
- [x] **T027** [US1] Add buildless `corex-captcha-v3.js`: intercept submit, `grecaptcha.execute(siteKey, {action})`, write fresh token, submit; per-form in-flight lock; recoverable error via `Corex.notices.status()`. `addons/corex-captcha/assets/corex-captcha-v3.js`.
- [ ] **T028** [US1] Playwright: a protected page loads `recaptcha/api.js` and submits successfully; an unprotected page loads no provider script; two submits present different tokens; the secret appears in no page or network payload. `tests/e2e/forms-flow.spec.js`.

**Checkpoint**: US1 independently shippable — a configured protected form accepts real people and the regression is closed.

---

## Phase 5: US2/US3 — Truthful delivery outcome (P1)

### Tests first

- [x] **T029** [P] [US2] Failing Pest unit tests for `NotificationDelivery` status mapping: every `MailResult` state maps; `wp_mail()` true → `accepted` (never `sent`); `not_attempted` reason recorded; redaction of sensitive fields. `tests/Unit/Forms/NotificationDeliveryTest.php`.
- [x] **T030** [US2] Failing Pest integration tests: submission persists before the mail attempt; submission survives a CoreX Mail failure; submission survives a `wp_mail()` failure; captured/queued/rejected/accepted outcomes each recorded; safe reason persisted; legacy submission hydrates as outcome-unavailable. `tests/Integration/Forms/NotificationDeliveryTest.php`.
- [x] **T031** [P] [US3] Failing Pest test for the unified timeline shape: a `notification` event is readable by `SubmissionTimelineRepository`; a legacy `{kind,state,occurred_at}` row hydrates rather than dropping. `tests/Integration/Submissions/TimelineShapeTest.php`.

### Implementation

- [x] **T032** [US2] Add `Corex\Forms\Submission\NotificationDelivery` (typed, redacting VO) mapped from `MailResult::$state`. `plugins/corex-forms/src/Submission/NotificationDelivery.php`.
- [x] **T033** [US2] Add `Corex\Forms\Submission\NotificationDispatcher` — the RoutedMailer → AttemptingMailer → Mailer → `wp_mail()` ladder, capturing `wp_mail_failed`. `plugins/corex-forms/src/Submission/NotificationDispatcher.php`.
- [x] **T034** [US2] Rewire `EmailStage` to dispatch via `NotificationDispatcher`, distinguish `FlowEmailSender`'s three null reasons (no mailer → not_attempted; no recipients → rejected), and persist the `corex_notification_delivery` projection alongside the unchanged `corex_email_json`. `plugins/corex-forms/src/Submission/Stages/EmailStage.php`, `FlowEmailSender.php`.
- [x] **T035** [US2] Reuse `NotificationDispatcher` from `SendEmailListener`, removing the duplicated ladder. `plugins/corex-forms/src/Listeners/SendEmailListener.php`.
- [x] **T036** [US3] Unify the timeline shape onto the repository's; add the `notification` stage; hydrate legacy rows. `plugins/corex-forms/src/Submission/Stages/TimelineStage.php`, `plugins/corex-config/src/Submissions/SubmissionTimelineRepository.php`.

**Checkpoint**: outcomes are checked, persisted, and never lose a submission. Not yet visible in the UI — Phase 6.

---

## Phase 6: US3 — Make it visible (P1)

### Tests first

- [x] **T037** [P] [US3] Failing Jest tests for the inbox delivery status: all seven presented states render with text + icon + accessible name; the attempt link is gated on permission + presence. `plugins/corex-config/src/Submissions/__tests__` (or the co-located suite).

### Implementation

- [x] **T038** [US3] Surface delivery status in the submissions list, detail, and timeline; add the `CorexSelect` delivery filter; gate the attempt link on `attemptId` + Email Studio view ability, reusing `SubmissionEmailService::resend()`/`assertRelated()`. `plugins/corex-config/src/Submissions/*`, `plugins/corex-config/src/admin/Submissions/*`.
- [x] **T039** [US3] Style the seven states with existing `is-{tone}` tokens (no new colours); ensure never colour-alone. `plugins/corex-config/assets/*submissions*`.
- [ ] **T040** [US3] Playwright: cause each outcome, assert list distinguishability and redacted detail. `tests/e2e/submissions-inbox.spec.js`.

**Checkpoint**: US3 independently shippable.

---

## Phase 7: US4 — Per-form protection (P2)

- [x] **T041** [P] [US4] Failing Jest test for the builder Protection panel (uses `CorexSelect`, not a native select). 
- [x] **T042** [US4] Add the Protection panel to the flow builder tabs, reading/writing `protection`. `plugins/corex-config/src/admin/Forms/*`.
- [x] **T043** [US4] Integration test: per-form threshold override applied; unrelated forms not affected; pre-feature flow submits unchanged with an unchanged checksum. `tests/Integration/Forms/PerFormProtectionTest.php`.

**Checkpoint**: US4 independently shippable.

---

## Phase 8: US5 — Transport boundary (P3)

- [x] **T044** [P] [US5] Failing Pest unit tests for `TransportAdvisory`: public signals only; a safe warning when `wp_mail_from` is filtered or the From domain differs; general guidance when no evidence; never reads plugin internals. `tests/Unit/Email/TransportAdvisoryTest.php`.
- [x] **T045** [US5] Add `Corex\Config\Email\TransportAdvisory` + `TransportAdvisoryResult` VO. `plugins/corex-config/src/Email/TransportAdvisory.php`.
- [x] **T046** [US5] Surface guidance in Email Studio help (a "Sending & transport" panel on `EmailStudioScreen`). The Forms notification-routing help is covered by the docs (T049); the canonical mail-settings surface is Email Studio. `plugins/corex-config/src/Email/EmailStudioScreen.php`.

---

## Phase 9: Settings & Captcha UI

- [x] **T047** [US1] Update the captcha settings section: threshold default `0.3` + corrected help; hostname allowlist field; expiry window; honest configured/unconfigured/unavailable/error/disabled states; "only protected CoreX forms are covered"; "the secret never reaches the browser". `plugins/corex-config/src/Settings/SettingsRegistry.php`.
- [x] **T048** [P] [US1] Failing test for the settings state resolution (configured vs unconfigured vs unavailable).

---

## Phase 10: Documentation (Definition of Done — same change)

- [x] **T049** [P] Forms, Captcha, Email Studio, and Submissions guides + module READMEs: reCAPTCHA action derivation, threshold/hostname/replay behaviour, the six delivery states, the FluentSMTP boundary, and the **support boundary** (official CoreX Forms only; not third-party plugins, not bypassing code, not direct `wp_mail()`, not WP login). Never claim inbox delivery from `accepted`. `docs-app/src/content/docs/guides/*`, plugin/addon READMEs, `docs-app/astro.config.mjs` sidebar if a page is added.
- [x] **T050** [P] Update root `README.md` feature inventory where capability changed; state each capability honestly (implemented/backend-only/env-gated).

---

## Phase 11: Phase A gate (blocks Phase B / spec 072)

- [x] **T051** Run and record **exact counts** in `evidence.md`: `composer validate`, `php -l` sweep, `composer test`, `composer test:integration`, `npm run test:js`, `npm run lint:js`, `npm run lint:css`, `npm run build`, `npm run verify:dependencies`, `git diff --check`. Report the known pre-existing segfault honestly, do not absorb it.
- [x] **T052** Playwright: `forms-flow`, `submissions-inbox` (+ `console`) green; record counts.
- [x] **T053** Guard Gate on the full diff: `clean-code-guard`, `wp-guard`, `test-guard`, `docs-guard`, `ui-ux-pro-max`. No diff ships until each runs clean.
- [x] **T054** Render-verify inbox + captcha settings in dark, light, RTL, 375px, keyboard-only, reduced-motion. Screenshots from controlled fixtures into `evidence.md`.
- [x] **T055** Determine whether the delivery work surfaces the inherited "Illegal characters in the subject field" warning; report the finding in `evidence.md` (do not silently absorb).
- [x] **T056** Update `PROGRESS.md`; append `DECISIONS.md` (checksum-compatibility rule; MailResult reuse; transient replay guard; footer-hook enqueue vs Principle VI; the `CaptchaResolver` factory fix). Mark the Phase A gate green in `evidence.md`.

**Checkpoint**: Phase A complete and verified. Only now does spec 072 (Notification Center + Dashboard) begin.

---

## Dependencies & parallelism

- Phase 2 (T005-T007) blocks all of US1.
- Within US1: T012-T016 (drivers) block T017 (stage); T022-T024 block T025-T027 (front-end).
- US2/US3 delivery (Phase 5) is independent of the captcha work and may proceed in parallel with Phase 4 by a second pass, but both must be green before the Phase 11 gate.
- `[P]` tasks touch different files with no shared state.
- The Phase 11 gate is the hard boundary the owner mandated: **no Phase B file is touched until it is green.**
