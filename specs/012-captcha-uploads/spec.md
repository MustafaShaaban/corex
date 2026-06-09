# Feature Specification: Captcha drivers + Secure uploads (MVP)

**Feature Branch**: `012-captcha-uploads`

**Created**: 2026-06-10

**Status**: Draft

**Input**: Two anti-abuse enablers for Newsletter/Careers — a captcha driver system (honeypot + remote
providers behind one interface) and a path-safe, MIME/size-validated file-upload validator. Captcha is an
add-on (`corex-captcha`); the upload validator is a core security util. Verification logic is unit-tested;
the remote HTTP call is the only boundary.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Validate an uploaded file safely (Priority: P1) 🎯 MVP

A handler accepting a file (e.g. a CV) validates it before it touches the filesystem — an allowed MIME type
and extension, within a size cap, with no upload error and no caller-supplied path.

**Why this priority**: File uploads are the single most dangerous input; safe validation is the prerequisite
for Careers.

**Independent Test**: Validate a file descriptor headlessly — a good PDF passes; an executable, an
oversized file, and an upload error each fail with a reason.

**Acceptance Scenarios**:

1. **Given** a PDF within the size cap, **When** validated, **Then** it passes.
2. **Given** a disallowed type/extension, an oversized file, or a PHP upload error, **When** validated,
   **Then** it fails with a specific reason and is never stored.

---

### User Story 2 - Verify a captcha behind one interface (Priority: P1)

A form verifies an anti-bot challenge through a single `Captcha` interface; the configured driver
(none / honeypot / a remote provider) decides. Switching providers is configuration.

**Why this priority**: Anti-spam is required for public Newsletter/Careers submissions; the driver
abstraction keeps it provider-agnostic.

**Independent Test**: Each driver verifies headlessly — honeypot passes when empty and fails when filled;
the remote driver passes/fails by the provider's `success` response (HTTP stubbed); the resolver selects
the configured driver.

**Acceptance Scenarios**:

1. **Given** no captcha configured, **When** verified, **Then** it passes (the honeypot still guards).
2. **Given** the honeypot driver, **When** the honeypot field is empty, **Then** it passes; filled, it fails.
3. **Given** a remote provider, **When** the provider returns `success:true`, **Then** it passes; `false`
   or an error, it fails (fail-closed).

---

### Edge Cases

- An upload with a spoofed extension vs MIME → rejected (both must match the allowlist).
- A remote captcha provider timeout/error → fail-closed (treated as not verified).
- No secret configured for a remote driver → fail-closed, logged.
- A zero-byte or missing tmp file → rejected.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST validate an uploaded file against an MIME-type allowlist, a matching
  extension, and a maximum size, and MUST reject any PHP upload error — before the file is stored.
- **FR-002**: The upload layer MUST NOT accept a caller-supplied filesystem path; it operates on the
  validated upload descriptor only (path-traversal safe).
- **FR-003**: The system MUST provide a `Captcha` interface with a `none` (pass), a `honeypot`, and a remote
  provider driver (reCAPTCHA/Turnstile/hCaptcha — all `{success}` shaped), selected by configuration.
- **FR-004**: A remote captcha verification MUST be fail-closed — a non-success response, a transport error,
  or a missing secret means "not verified".
- **FR-005**: The captcha resolver MUST select the configured driver and supply its secret/site key from the
  Config engine; no secret is hardcoded or logged.
- **FR-006**: The captcha system ships as an add-on (`corex-captcha`); the upload validator lives in
  corex-core. Neither hard-depends on an optional plugin.

### Key Entities *(include if feature involves data)*

- **UploadValidator**: validates a file descriptor → a result (valid/reason). Pure.
- **Captcha**: verifies a challenge token → bool. Drivers: none, honeypot, remote.
- **CaptchaResolver**: selects the configured driver with its keys.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A disallowed, oversized, or errored upload is rejected 100% of the time, with a reason, before storage.
- **SC-002**: A valid file passes validation.
- **SC-003**: Each captcha driver verifies correctly headlessly; the remote driver is fail-closed.
- **SC-004**: Switching captcha provider is configuration only (no code change).
- **SC-005**: The validation + driver-selection logic is fully unit-tested; only the provider HTTP call is a boundary.

## Assumptions

- **MVP scope.** One remote provider shape (`{success: bool}`, covering reCAPTCHA v2/v3, Turnstile,
  hCaptcha by endpoint+secret); honeypot + none always available. Score thresholds (v3), Akismet, and
  per-action captcha are deferred. Upload: validate + a protected-dir store helper; virus scanning and image
  processing are deferred.
- **Packaging.** `addons/corex-captcha` (`Corex\Captcha`) for captcha; `Corex\Security\Upload` in corex-core.
- **Consumers.** Newsletter (013) and Careers (014) use these; the upload store integrates with mail
  attachments later.
