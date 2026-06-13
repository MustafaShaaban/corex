# Phase 0 — Research & Design Decisions: 044 Admin control panel & integrations

Resolves the spec's Assumptions into concrete design choices, grounded in the existing code. No
`NEEDS CLARIFICATION` remained.

## D1 — Domain status is derived, not stored

**Decision**: a pure `DomainStatus` maps each settings domain (the existing `SettingsRegistry` sections — brand,
mail, forms, captcha, insights — plus Updates/Integrations/Add-ons which are their own screens) to a status
(`configured` / `needs_setup` / `error`) computed from the Config values already persisted, a list of missing
items, and a setup link. `ControlPanelStatus` aggregates them for the cards; `OnboardingChecklist` turns the
not-`configured` domains into checklist steps. All three are pure (Config values injected) → headless-testable.

**Rationale**: FR-002/FR-004 + Assumption "derived, nothing new persisted". Reuses the SettingsRegistry as the
source of domains so cards never drift from the real settings.

**Alternatives**: a stored "setup state" flag per domain — rejected (drifts from reality, needs migration).

## D2 — Status rules per domain

**Decision**: `configured` = all the domain's required fields present + valid; `needs_setup` = a required field
empty (e.g. captcha driver = recaptcha but no site/secret key; mail from-address empty); `error` = a recorded
failed test result (captcha/insights test that failed). Honeypot/none captcha needs no keys → `configured`. A
domain with only optional fields (e.g. Insights, where the PSI key is optional) is `configured` when usable and
`needs_setup` only when a chosen feature lacks its requirement.

**Rationale**: matches the spec's edge cases (partial → needs_setup; failed test → error) and Principle IX
(optional keys don't force "needs setup").

## D3 — Captcha settings extension

**Decision**: extend the `SettingsRegistry` captcha section with `captcha.site_key` (text), and for reCAPTCHA v3
`captcha.score_threshold` (number) + `captcha.action` (text); keep `captcha.secret` write-only (existing
`password` type from spec 032). The driver `select` already lists none/honeypot/recaptcha/turnstile/hcaptcha. The
screen reveals key fields only for key-requiring drivers (a presentation concern in `AdminDashboard`/`SettingsForm`,
driven by the driver value).

**Rationale**: FR-006; reuses the spec-032 field-type controls + write-only secret; adds only data the v3 flow
needs. No new store.

**Alternatives**: a separate captcha screen — rejected (the control panel groups everything; a card suffices).

## D4 — Captcha "Test verification" action

**Decision**: a thin `CaptchaTestController` REST route, `AdminGuard`-gated (manage_options + nonce), runs the
**configured driver's** verify against a caller-supplied test token (or a synthetic probe for honeypot/none) and
returns a `ResponseEnvelope`. A pure `CaptchaDiagnostic` classifies the driver outcome into
`ok` / `missing_keys` / `invalid_keys` / `network_error` / `not_applicable`. The response carries only the
classification + a human message — **never the secret**.

**Rationale**: FR-007/FR-008/SC-003; reuses the spec-012 `RemoteCaptcha`/`CaptchaResolver`; envelope + runtime from
043; AdminGuard per Principle VII.

## D5 — PSI diagnostics: local-URL detection + failure classification

**Decision**: a pure `SiteUrlReachability::isPublic(string $url): bool` detects local/private hosts
(`localhost`, `*.local`, `127.0.0.0/8`, `10/8`, `172.16/12`, `192.168/16`, `::1`). A pure `PsiDiagnostic` maps a
PSI attempt to a kind: `local_url` (caught before the call), `http_error` (4xx/5xx — read
`wp_remote_retrieve_response_code`, currently unchecked), `quota` (429 / quota message), `invalid_key`
(400/403 with a key error), `invalid_response` (non-array / missing lighthouse), `ok`. `PerformanceProvider.run()`
checks reachability first, then the response code, then hands a good body to the existing `PsiNormalizer`; on
failure it produces an `InsightResult` whose recommendation is the classified, actionable message and whose
**admin-only** diagnostic detail carries the raw status/body excerpt (no secret). A `test` action on
`InsightsController` returns the same classification as an envelope.

**Rationale**: FR-010/FR-011/FR-012/FR-013/SC-004; the current `unavailable()` catch-all (PerformanceProvider L66)
is replaced by classification; the response code is currently ignored (L51-57), the root cause of the vague error.

**Alternatives**: a third-party reachability ping — rejected (the host check is deterministic and offline).

## D6 — Admin-only raw diagnostic detail

**Decision**: the classified result carries two layers — a public user message (always shown) and a `detail`
(raw status, a bounded body excerpt) rendered **only** when `current_user_can('manage_options')`, and scrubbed of
any `key=`/token query parameter. The envelope's `details` is populated only for admins by the controller.

**Rationale**: FR-013/SC-006 — useful for an admin to debug, never a secret, never shown to lower roles.

## D7 — Rich add-on manifest

**Decision**: extend the spec-026 `Addon` value with `summary`, `description`, `provides` (a small typed list:
blocks/CPTs/routes/settings/features), `requires` (plugins/flags/keys), `enableBehavior`, `disableBehavior`,
`needsConfiguration` (bool) + `missingKeys()` (derived from Config), and `docsUrl`. `AddonView`/`AddonsScreen`
render them; the dependency-aware refusal (026) is now explained, not silent.

**Rationale**: FR-014/FR-015; additive to the existing manifest — existing add-on registrations gain fields with
safe defaults so nothing breaks.

**Alternatives**: a separate manifest file per add-on — rejected (the registry already centralises them; extend it).

## D8 — Authorship metadata

**Decision**: set the `Author:` header in every framework plugin (`corex-*`) + the theme `style.css` to the single
owner/brand (the value already in the repo's primary metadata), and document the convention in `CONTRIBUTING` /
the docs handbook. A purely textual change; no behavior.

**Rationale**: FR-016 — credibility/correctness; low risk, bundled as US5.

## D9 — Reuse the 043 runtime for the test buttons

**Decision**: the test buttons call `window.Corex.api.post(...)` and render the returned envelope's
message/details via `Corex.notices`; the screens declare `corex-runtime` as a script dependency (the spec-043
pattern already applied to Insights/Data). No bespoke fetch.

**Rationale**: FR-017; consistency + the contract is already proven (spec 043).
