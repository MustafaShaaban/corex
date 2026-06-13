# Feature Specification: Admin control panel & integrations

**Feature Branch**: `feature/044-admin-control-panel`

**Created**: 2026-06-13

**Status**: Draft (forward spec — precedes code; full Spec Kit flow)

**Input**: User description: "Turn Corex's settings/admin from a basic form into a professional framework control
panel, and make the integrations (captcha, PageSpeed/Insights) clear to configure and diagnose — grouped settings
with status, richer add-on explanations, a confident captcha setup with a test button, actionable PageSpeed
diagnostics, and corrected authorship metadata. Enhance the shipped specs 032/026/037/016; don't re-spec them."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A control panel that shows what's set up and what isn't (Priority: P1) 🎯 MVP

A site administrator opens **Corex** and sees a control panel, not a flat form: the settings are grouped into
clear domains (Branding, Forms, Captcha, Mail, Insights, Updates, Integrations, Add-ons), each shown as a card
with a **status** — configured, needs setup, or error — and the cards with a problem carry a plain-language
warning and a "how to set this up" link. The Corex dashboard shows an **onboarding checklist** of the few steps
that still need attention, so the admin always knows the next action.

**Why this priority**: This is the headline fix for "the admin feels like a basic settings form / I don't know
what's configured." It reframes everything and is the surface every other story plugs into.

**Independent Test**: Open the control panel with some domains configured and some not; each card shows the
correct status and a setup link where needed; the dashboard checklist lists exactly the unfinished steps.

**Acceptance Scenarios**:

1. **Given** the settings, **When** the control panel loads, **Then** they appear grouped into the named domains,
   each as a card with a status indicator (configured / needs setup / error).
2. **Given** a domain that is missing required configuration, **When** the card renders, **Then** it shows a
   plain-language warning and a "how to set this up" link — never a silent or empty state.
3. **Given** the dashboard, **When** it loads, **Then** an onboarding checklist lists the still-incomplete setup
   steps, each linking to the relevant domain; a fully-configured site shows a "you're all set" state.
4. **Given** any secret-bearing field, **When** the panel renders, **Then** the secret is **write-only** (never
   shown back); a configured secret shows only that it is set.

---

### User Story 2 - Configure captcha confidently, and prove it works (Priority: P1)

An administrator sets up spam protection without guesswork: they pick a driver (none / honeypot / reCAPTCHA v3 /
Turnstile / hCaptcha) from a clear selector, enter the site and secret keys (secret write-only), set the score
threshold and action name for reCAPTCHA v3 when that driver is chosen, and click **Test verification** to confirm
the keys actually work — getting a clear pass/fail result. Inline links tell them exactly where to get the keys.

**Why this priority**: Captcha is configured blind today; a wrong key fails silently at submit time. A driver
selector + a test button + "where to get keys" turns a frustrating guess into a confident setup.

**Independent Test**: Select each driver; the right fields show (v3 reveals threshold + action); a Test
verification with good keys reports success and with bad keys reports a specific failure — and the secret key is
never returned in any response.

**Acceptance Scenarios**:

1. **Given** the captcha settings, **When** a driver is selected, **Then** only that driver's relevant fields show
   (e.g. reCAPTCHA v3 reveals score threshold + action name; honeypot/none reveal no keys).
2. **Given** configured keys, **When** the admin clicks **Test verification**, **Then** the result reports a clear
   success or a specific failure (e.g. invalid key, network error), rendered through the standard response
   contract — and the **secret key is never present** in the response.
3. **Given** any driver requiring keys, **When** the card renders, **Then** an inline link points to where to
   obtain the keys, with brief step-by-step guidance.
4. **Given** a missing or partial key set, **When** the panel renders, **Then** the captcha card shows "needs
   setup" and names what is missing.

---

### User Story 3 - Insights/PageSpeed failures that tell you what's wrong (Priority: P1)

When a performance check fails, the administrator sees **why** and **what to do** — not "the performance result
could not be read." If the site URL is local/private (corex.local, localhost, 127.0.0.1), the panel explains that
PageSpeed cannot crawl a non-public URL. Other failures are distinguished — HTTP error, API error, quota
exceeded, invalid key, invalid response — each with a next action. A **test** action checks the API key / URL,
the key's optional-vs-recommended status is stated, there's a link to the API-key docs, and the raw diagnostic
detail is available **to admins only**.

**Why this priority**: The current vague error is a real reported pain; specific, actionable diagnostics turn a
dead end into a fix. Same priority as captcha — both are integration-trust fixes.

**Independent Test**: Run the performance check against a local URL → the local-URL explanation appears; simulate
each failure class → each renders its distinct message + next action; the raw detail is shown only to a
`manage_options` admin.

**Acceptance Scenarios**:

1. **Given** a local/private site URL, **When** the performance check runs, **Then** the panel explains PageSpeed
   cannot reach a non-public URL and suggests testing a public URL — instead of a generic failure.
2. **Given** a failure, **When** it is shown, **Then** the cause is classified (HTTP status / API error / quota /
   invalid key / invalid response) with a specific message and a next action.
3. **Given** the Insights settings, **When** the admin uses the **test** action, **Then** the key/URL is checked
   and the result reports specifically whether the key is missing, invalid, or working; the panel states whether
   the key is optional or recommended, with a docs link.
4. **Given** a failure, **When** raw diagnostic detail is shown, **Then** it is visible **only** to a
   `manage_options` admin and contains no secret.

---

### User Story 4 - Understand an add-on before you toggle it (Priority: P2)

Before enabling or disabling an add-on, the administrator reads what it actually does — a summary and
description, the features it adds, what it registers (blocks / content types / routes / settings), what happens
on enable and on disable, its dependencies and conflicts, whether it needs configuration (and whether required
keys are missing), and a link to its docs — all on the Add-ons screen.

**Why this priority**: Today enabling an add-on is opaque. Rich, accurate explanations make the Add-ons screen
trustworthy. P2 because the control panel (US1) and the integration fixes (US2/US3) address sharper daily pain.

**Independent Test**: Open the Add-ons screen; each add-on shows its summary/description, what it registers, its
enable/disable behavior, dependencies, a configuration-needed indicator (flagging missing keys), and a docs link
— all matching what the add-on really does.

**Acceptance Scenarios**:

1. **Given** the Add-ons screen, **When** an add-on is shown, **Then** it presents a summary, description, the
   things it registers, its enable/disable behavior, dependencies/conflicts, and a docs link.
2. **Given** an add-on that needs configuration, **When** it is shown, **Then** it indicates that, and flags any
   required keys/settings that are still missing.
3. **Given** an add-on with an unmet dependency, **When** the admin tries to enable it, **Then** the screen
   explains the dependency rather than failing silently (the existing dependency-aware behavior, now explained).

---

### User Story 5 - Honest authorship metadata (Priority: P3)

Where framework files credit a non-existent "team", the author reads as the single owner/brand instead, and the
convention is documented so future files follow it.

**Why this priority**: A small credibility/correctness fix; low urgency, easy to bundle.

**Independent Test**: Inspect the framework plugin/theme headers and authorship metadata; they name the
owner/brand consistently; the convention is written down.

**Acceptance Scenarios**:

1. **Given** the framework's plugin/theme headers, **When** inspected, **Then** the author is the owner/brand,
   consistently, with no reference to a non-existent team.
2. **Given** the contributor docs, **When** read, **Then** the authorship convention is stated.

---

### Edge Cases

- A domain whose configuration is partially complete shows "needs setup", not "configured" or "error".
- The Test verification / Test performance actions require a logged-in `manage_options` admin and a valid request
  token; an unauthorized request is refused.
- A test action that times out or hits a network error reports that distinctly, not as "invalid key".
- The control panel renders correctly when **no** integrations are configured (a fresh install) — all cards
  "needs setup", the checklist full, nothing broken.
- The onboarding checklist reflects reality: a step marked done never reappears; completing the last step shows
  the "all set" state.
- In wp-admin (where the Corex theme tokens are not loaded) the panel still styles correctly via admin-palette
  fallbacks.

## Requirements *(mandatory)*

### Functional Requirements

**Control panel & status (US1)**

- **FR-001**: The Corex settings screen MUST present the configuration grouped into the named domains (Branding,
  Forms, Captcha, Mail, Insights, Updates, Integrations, Add-ons), each as a card with a clear visual hierarchy.
- **FR-002**: Each domain card MUST show a **status** — configured / needs setup / error — derived from whether
  its required configuration is present and valid.
- **FR-003**: A card needing attention MUST show a plain-language warning and a "how to set this up" link; a card
  MUST NOT render an empty or silent problem state.
- **FR-004**: The Corex dashboard MUST show an **onboarding checklist** of incomplete setup steps, each linking to
  its domain, with an explicit "all set" state when nothing remains.
- **FR-005**: Secret-bearing fields MUST remain **write-only** — a configured secret is shown only as "set",
  never echoed back (preserves the spec-032 pattern).

**Captcha UX (US2)**

- **FR-006**: The captcha settings MUST offer a driver selector (none / honeypot / reCAPTCHA v3 / Turnstile /
  hCaptcha) and reveal only the selected driver's relevant fields (reCAPTCHA v3 → score threshold + action name).
- **FR-007**: The captcha card MUST provide a **Test verification** action that checks the configured keys and
  reports a clear success or a specific failure, returned through the standard response contract.
- **FR-008**: A captcha test or any captcha response MUST NEVER include the secret key or any secret material.
- **FR-009**: Each key-requiring driver MUST show an inline link to where to obtain the keys plus brief
  step-by-step guidance, and the card MUST flag exactly which required keys are missing.

**Insights/PageSpeed diagnostics (US3)**

- **FR-010**: A failed performance check MUST classify the cause — local/private URL, HTTP status, API error,
  quota exceeded, invalid key, invalid response — and show a specific message with a next action, replacing the
  generic "could not be read" message.
- **FR-011**: The system MUST detect a local/private site URL (e.g. `.local`, `localhost`, `127.0.0.1`, private IP
  ranges) and explain that PageSpeed cannot crawl a non-public URL.
- **FR-012**: The Insights settings MUST provide a **test** action for the API key / URL that reports specifically
  whether the key is missing, invalid, or working, and MUST state whether the key is optional or recommended, with
  a link to the API-key documentation.
- **FR-013**: Raw diagnostic detail MUST be shown **only** to a `manage_options` administrator and MUST contain no
  secret.

**Add-on manifests (US4)**

- **FR-014**: Each add-on MUST expose a richer manifest — summary, description, the things it registers
  (features / blocks / content types / routes / settings), enable behavior, disable behavior,
  dependencies/conflicts, a configuration-needed flag, and a docs link.
- **FR-015**: The Add-ons screen MUST present that manifest for each add-on, flagging when required configuration
  or keys are missing, and MUST explain an unmet dependency rather than failing silently (the existing
  dependency-aware behavior, now surfaced).

**Authorship (US5)**

- **FR-016**: Framework plugin/theme authorship metadata MUST name the owner/brand consistently (no non-existent
  team), and the convention MUST be documented for contributors.

**Cross-cutting**

- **FR-017**: All admin test actions MUST be gated to `manage_options` with a valid request token, MUST route
  through the shared admin-security helper (not hand-rolled checks), and MUST return results through the standard
  response contract (the spec-043 envelope) via the shared client runtime — no bespoke fetch.
- **FR-018**: All panel styling MUST be token-only with wp-admin palette fallbacks, use logical/RTL CSS, meet
  WCAG 2.2 AA (status not conveyed by color alone; accessible warnings/links), and be translation-ready.
- **FR-019**: No new hard dependency MUST be introduced — captcha drivers and external services remain optional,
  detected/adapted behind their existing abstractions (Principle IX); the framework runs fully with none set up.

### Key Entities *(include if feature involves data)*

- **Domain status**: for each configuration domain, a status (configured / needs setup / error) + a list of
  missing or invalid items + a setup link — derived from the existing settings values (no new storage).
- **Onboarding step**: a checklist item (label, the domain it belongs to, done/not-done, link), computed from the
  domain statuses.
- **Captcha configuration**: driver choice + per-driver fields (site key, secret [write-only], v3 score threshold,
  v3 action) — extends the existing captcha settings (spec 012/032), not a new store.
- **Diagnostic result**: a classified outcome of a test/check (kind: ok / local-url / http-error / api-error /
  quota / invalid-key / invalid-response, a user message, an admin-only raw detail with no secret).
- **Add-on manifest (extended)**: the spec-026 manifest plus summary, description, provides[], requires[],
  enable/disable behavior, needs-configuration flag, docs URL.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An administrator can tell, at a glance from the control panel, which of the named domains are
  configured, which need setup, and which have an error — **100%** of domains show an accurate status.
- **SC-002**: For every domain that needs setup, the admin reaches the place to fix it via the card's setup link
  or the dashboard checklist in **one click**.
- **SC-003**: An administrator can verify captcha keys with a single **Test verification** action and get a
  pass-or-specific-failure result **without** the secret key ever appearing in a response.
- **SC-004**: A failed performance check yields a **specific, actionable** cause (including the local-URL case) in
  **100%** of the classified failure types — the generic "could not be read" message no longer appears.
- **SC-005**: For every add-on, the Add-ons screen states what it does, what it registers, its enable/disable
  behavior, its dependencies, whether it needs configuration, and links to docs — accurately (matches the add-on).
- **SC-006**: **No secret** appears in any control-panel or test-action response, and every test action is
  refused for a non-`manage_options` or unauthenticated request.

## Assumptions

- Builds on and **reuses** the shipped settings/registry (spec 032), add-on manager (spec 026), insights engine +
  providers (spec 037), branding (spec 016), captcha drivers (spec 012), the shared `AdminGuard` (Principle VII
  scope), and the spec-043 response envelope + `window.Corex` runtime — this feature is the **configuration UX and
  diagnostics layer** over them, adding no new settings store and no new captcha driver.
- Domain statuses are **derived** from existing setting values at render time; nothing new is persisted beyond the
  already-stored settings.
- "Configured / needs setup / error" is computed per domain from its required fields (e.g. Captcha with a driver
  that needs keys but none set → needs setup; a Test verification that failed → error).
- The captcha drivers available are those spec 012 ships; the selector lists exactly those plus none/honeypot.
- The owner/brand for authorship is the project owner already recorded in the repository's existing metadata.
- Out of scope (explicitly): Data-tab search/filter/export (spec 045); the REST resource generator / headless
  (spec 046); a visual redesign of the editor/blocks/Design Language System (spec 051); implementing new captcha
  drivers (spec 012 owns drivers — this is UX over them).
- Browser-visual confirmation of the panel/cards/test buttons requires a running server + browser; per the
  project-wide environment gate, automated unit coverage is authoritative and the live browser smoke runs when the
  environment is available.
