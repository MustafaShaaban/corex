# Feature Specification: Careers (MVP)

**Feature Branch**: `014-careers`

**Created**: 2026-06-10

**Status**: Draft

**Input**: Job postings + a secure application flow (apply with a CV upload), an application pipeline, and
notifications. Add-on `corex-careers`. Jobs are a CPT; applications are a custom table (011). Uses the
upload validator + captcha (012), Mail (008), Forms validation (007). Application logic is unit-tested; the
data path is integration-tested.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - List and view open jobs (Priority: P1) 🎯 MVP

A visitor sees open jobs (a `corex/jobs` block + a single-job page), each with department/location/type.

**Why this priority**: No careers section without job listings; this is the visible minimum.

**Independent Test**: The jobs block renders open jobs as accessible cards (linked title + meta) from an
injected provider; an empty state when there are none — headless.

**Acceptance Scenarios**:

1. **Given** open jobs, **When** the block renders, **Then** each is an accessible card with a linked title
   and its department/location/type; none → an accessible empty state.

---

### User Story 2 - Apply with a CV, safely (Priority: P1)

An applicant submits name, email, cover letter, and a CV file. The application is validated (required
fields, a safe CV, captcha/honeypot), stored, and HR is notified + the applicant gets a confirmation.

**Why this priority**: The application is the point of careers; safe file handling is the security-critical
core.

**Independent Test**: With fakes, applying with valid data + a valid CV stores one application and sends two
emails; a missing field, a bad CV (type/size), or a failed captcha rejects it with zero side effects.

**Acceptance Scenarios**:

1. **Given** valid data + a valid CV, **When** applied, **Then** an application is stored and HR + the
   applicant are emailed.
2. **Given** a disallowed/oversized CV, a missing required field, or a failed captcha, **When** applied,
   **Then** it is rejected and nothing is stored or sent.

---

### User Story 3 - Move an application through the pipeline (Priority: P2)

A recruiter advances an application through statuses (new → reviewing → interviewed → offer → hired /
rejected); only valid transitions are allowed.

**Why this priority**: Managing applicants is the operational value; applying works without it (P2).

**Independent Test**: The status flow allows valid transitions and rejects invalid ones (pure).

**Acceptance Scenarios**:

1. **Given** a `new` application, **When** advanced to `reviewing`, **Then** it is allowed; **When** jumped
   to `hired`, **Then** it is rejected.

---

### Edge Cases

- A CV that is an executable or oversized → rejected (spec 012), no storage.
- An application to a closed/missing job → rejected.
- A captcha/honeypot failure → rejected, no side effect (spec 012/007).
- A status transition to the same or a non-adjacent status → rejected.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST provide a `corex_job` job type with department/location/type taxonomies, an
  open/closed state, a `corex/jobs` listing block, and a single-job view.
- **FR-002**: The system MUST accept an application (name, email, cover letter, CV) only when every required
  field is present, the CV passes the upload validator (spec 012), and the captcha/honeypot pass (spec 012/007).
- **FR-003**: A valid application MUST be stored in a custom table (job, applicant fields, CV reference,
  status, timestamps) and MUST notify HR + confirm the applicant via Mail (spec 008).
- **FR-004**: A rejected application MUST have zero side effects (nothing stored, nothing sent).
- **FR-005**: Applications MUST move only through valid pipeline transitions (new → reviewing → interviewed
  → offer → hired/rejected); invalid transitions are rejected.
- **FR-006**: The add-on MUST NOT hard-depend on an optional plugin; it builds on corex-core + uses
  Mail/Captcha when present.

### Key Entities *(include if feature involves data)*

- **Job** (`corex_job` CPT): title, description, department/location/type, open state.
- **Application** (custom table): job id, name, email, cover letter, CV reference, status, timestamps.
- **ApplicationService**: validates → stores → notifies. **StatusFlow**: valid transitions. Pure.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: The jobs block renders accessible job cards (and an empty state) from data (headless).
- **SC-002**: A valid application stores one record + sends two emails; an invalid one has zero side effects.
- **SC-003**: A disallowed/oversized CV is never stored (reuses spec 012).
- **SC-004**: Only valid pipeline transitions are permitted.
- **SC-005**: The application + status + render logic is fully unit-tested; the data path is integration-tested.

## Assumptions

- **Packaging.** `addons/corex-careers` (`Corex\Careers`). Jobs = `corex_job` CPT (content, not high-volume).
  Applications = `corex_applications` custom table (011). CV stored via the boundary upload store
  (wp_handle_upload to a protected location); the validated reference is kept, never a caller path.
- **MVP scope.** Listing/single + apply + pipeline statuses + notifications. A recruiter admin screen
  (the admin dashboard, spec 017), CV virus scanning, and scheduled interviews are deferred.
