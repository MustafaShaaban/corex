# Feature Specification: Operations & Security UX and Safety Completion

**Feature Branch**: `spec/077-operations-security-completion`

**Created**: 2026-07-27

**Status**: Draft

**Input**: User description: "Reorganize and complete the Operations & Security admin experience — information architecture, spacing and rhythm, WordPress environment versus CoreX Operations Mode, progressive disclosure in the mode form, client and server validation, production readiness presentation, login-protection UX and slug validation, lockouts, notices, and tab behaviour."

## Why this spec exists

`wp-admin/admin.php?page=corex-operations-security` renders five unrelated areas as one continuous
column and asks the operator to work out the hierarchy themselves. Measured on the running install
before this spec was written:

- **No tabs, no grouping.** `render()` emits a status notice, the React security app, the mode form,
  the hardening checks and the mode history in a flat stack roughly **3,500 px tall**.
- **The mode form shows every confirmation control for every mode.** The site is currently in
  **Development**, and the form still renders the *"Type PRODUCTION"* confirmation field **and** the
  *"I understand maintenance affects real visitors"* checkbox. Neither applies. An operator changing
  to Staging is asked to acknowledge consequences that cannot occur.
- **Re-applying the mode you are already in is recorded as a change.**
  `OperationsModeStore::set()` writes the option and appends a history entry unconditionally, so
  choosing Development while already in Development produces a `development → development` row in
  the audit log and a success notice. The history stops being a record of changes.

None of this is a domain problem. The domain underneath is largely honest already, and this spec
is deliberately scoped around that.

## What is already right, and therefore not in scope

Stated up front because it changes the size of the work, and because a spec that re-specifies
working code is how a polish pass turns into a rewrite:

- **CoreX Operations Mode is already distinct from the WordPress environment type.**
  `OperationsMode` documents the distinction and `OperationsModeStore` falls back to
  `wp_get_environment_type()` only when no mode has been declared. What is missing is that the
  *screen* never shows both values together, and never says anything when they conflict.
- **Readiness already separates blocking from warning from passed.** `ReadinessSnapshot` carries
  four states — `pass`, `warning`, `blocking`, `unavailable` — with `blockingChecks()` and
  `blockingKeys()`. The screen simply does not group by them.
- **Login slug validation already exists and is already strict.** `LoginSlug` owns a reserved list,
  a `[a-z0-9][a-z0-9-]{2,80}` pattern and typed rejection reasons, written after two reachable
  ways to lock an owner out of their own site were found on a real install (DECISIONS #140).
  Because `sanitize_title()` runs first and the pattern is narrow, full URLs, query strings,
  fragments, path traversal and file-like values are **already unreachable**. The gaps are the
  collisions a pattern cannot see: an existing page, an existing rewrite endpoint.
- **Maintenance behaviour is already correct.** `MaintenanceGuard` serves anonymous front-end
  visitors a branded 503 with `Retry-After`, never intercepts admin, cron, AJAX or REST, and lets a
  signed-in `manage_options` user through. The screen can state all four as fact.
- **Dates on this screen already use the shared contract** (spec 076). They need verifying here,
  not rebuilding.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - An operator can find the thing they came for (Priority: P1)

An operator opens Operations & Security to answer one question — *is this site live?*, *why is
production blocked?*, *who got locked out?* — and reaches the answer without scrolling through four
unrelated areas to find it.

**Why this priority**: it is the defect. Everything else on this screen is legible in isolation; the
problem is that five things share one column with no hierarchy, so every visit is a search.

**Independent Test**: open the screen and reach the login-protection settings without scrolling past
readiness, hardening or history; link straight to that view and land on it.

**Acceptance Scenarios**:

1. **Given** the screen loads, **When** the operator looks at it, **Then** the areas are grouped into
   named sections, each reachable directly, with the current one indicated.
2. **Given** a specific view is open, **When** the address is copied and opened elsewhere,
   **Then** the same view opens.
3. **Given** a form is submitted from within a view, **When** the page returns after the redirect,
   **Then** the same view is still open — not the first one.
4. **Given** the operator uses only a keyboard, **When** they move between views, **Then** every
   view is reachable and the active one is announced.
5. **Given** the operator opens the overview, **When** they read it, **Then** they can state the
   operations mode, the WordPress environment, whether readiness passes, whether maintenance is on,
   whether login protection is on, and whether anything is locked out — without leaving it.

---

### User Story 2 - The mode form asks only what the chosen mode requires (Priority: P1)

An operator changing the operations mode is shown the consequences of *that* mode and asked for the
confirmations *that mode* needs — nothing else.

**Why this priority**: equal to US1 because it is the one place on this screen where the current
behaviour actively misleads. Being asked to type PRODUCTION and acknowledge visitor impact while
switching to Development teaches the operator that the confirmations are noise, which is exactly the
lesson that makes them dangerous when they are real.

**Independent Test**: choose each mode in turn and confirm that the production confirmation appears
only for Production, the maintenance acknowledgement only for Maintenance, and neither for
Development or Staging.

**Acceptance Scenarios**:

1. **Given** Development or Staging is chosen, **When** the form renders, **Then** no production
   confirmation and no maintenance acknowledgement are shown, and what is shown describes that mode.
2. **Given** Production is chosen, **When** the form renders, **Then** the readiness result, its
   blockers and warnings, links to resolve them, and the typed confirmation are shown — and the
   maintenance acknowledgement is not.
3. **Given** Maintenance is chosen, **When** the form renders, **Then** what happens to visitors,
   administrators, REST, AJAX and cron is stated, along with the recovery path and the maintenance
   acknowledgement — and the production confirmation is not.
4. **Given** the chosen mode is the one already in force, **When** the operator looks at the form,
   **Then** applying is unavailable and the screen says no change is needed.
5. **Given** the chosen mode is the one already in force, **When** a submission reaches the server
   anyway, **Then** no history entry is written and no success is reported.
6. **Given** the operator has JavaScript disabled, **When** they use the form, **Then** it still
   works and still validates.

---

### User Story 3 - The screen is honest about environment, readiness and lockouts (Priority: P2)

The operator can see the WordPress environment and the CoreX mode at once, is told when they
disagree, sees blockers separated from warnings, and can act on a lockout without guessing.

**Why this priority**: these are truthfulness improvements over working domain logic rather than
missing capability, so they follow the two structural stories.

**Independent Test**: set `WP_ENVIRONMENT_TYPE` to `production` with CoreX in maintenance, and
confirm the screen shows both and says they conflict.

**Acceptance Scenarios**:

1. **Given** the environment and the mode differ, **When** the screen renders, **Then** both values
   are shown and the difference is called out — without claiming the mode changes hosting.
2. **Given** readiness has blockers and warnings, **When** the operator reads it, **Then** blockers,
   warnings and passed checks are visually separate, counted, and each blocker links to its fix.
3. **Given** a lockout exists, **When** the operator reads it, **Then** they can tell whether it is
   active or expired, when it ends, what caused it, and what to do — with no raw IP shown where the
   system stores a hash.
4. **Given** login protection is enabled, **When** the operator reads it, **Then** the address the
   login is actually served at is shown, distinct from any unsaved value in the form.

---

### User Story 4 - The page reads as one designed surface (Priority: P3)

Spacing, alerts and cards follow one rhythm, at every width, in both directions, in both colour
schemes.

**Why this priority**: real and reported, but it is the story whose absence costs comprehension
rather than correctness.

**Independent Test**: measure the gaps between consecutive sections and confirm they come from
shared tokens rather than per-section margins.

**Acceptance Scenarios**:

1. **Given** the screen renders, **When** section spacing is measured, **Then** it comes from one
   layout container using design tokens, not from margins declared per section.
2. **Given** a page-level alert is present, **When** it renders, **Then** its spacing to the content
   below is the same wherever it appears.
3. **Given** RTL, 375 px, 200 % zoom, light and dark, **When** the screen renders, **Then** nothing
   overflows horizontally beyond what stock wp-admin already costs (DECISIONS #163).

---

### Edge Cases

- **The mode is chosen but readiness is re-evaluated between render and submit.** The confirmation
  the operator saw must still describe what the server is about to do, or the submission is refused
  and re-presented — never silently applied against a different snapshot.
- **A slug that sanitises to something other than what was typed.** The operator must be shown the
  value actually in force, told it was changed, and never shown their original as if it were live.
- **A slug that is valid in form but already taken** by a published page or a rewrite endpoint.
- **Enabling login protection while signed in through the default endpoint** — the path that locks
  an owner out of their own site.
- **Every list empty**: no lockouts, no history, no security activity. Each says so in its own words.
- **A history entry whose user no longer exists.**
- **JavaScript disabled**, for every control that changes state.
- **A tab requested by URL that does not exist**, or that the actor may not see.
- **Browser Back after a mode change**, which must not re-submit.

## Requirements *(mandatory)*

### Functional Requirements

**Information architecture**

- **FR-001**: The screen MUST group its areas into named, individually reachable sections rather
  than one continuous stack. Overview, Environment & Maintenance, Login Protection, Hardening, and
  Activity, unless the plan records evidence for a different grouping.
- **FR-002**: The active section MUST be reflected in the address, restorable from it, preserved
  across form submission and redirect, and correct after Back and Forward.
- **FR-003**: Section navigation MUST be operable by keyboard, announced correctly, and correct in
  RTL and at narrow widths.
- **FR-004**: No section may render empty or as a placeholder. A section with nothing to show says
  so in words that fit it.
- **FR-005**: The overview MUST answer, without leaving it: operations mode, WordPress environment,
  whether they differ, readiness, maintenance state, login protection state, default-endpoint
  protection, active lockouts, hardening warnings, and recent activity — each linking to where it
  is managed, and each a real value.

**The mode form**

- **FR-006**: Only the confirmations the proposed mode requires may be shown. Production shows the
  typed confirmation; Maintenance shows the acknowledgement; Development and Staging show neither.
- **FR-007**: Each mode MUST state its own consequences: Development its public-site warning and
  debug status; Staging its indexing and external-service warnings; Production its readiness result,
  blockers, warnings and resolution links; Maintenance the behaviour for visitors, administrators,
  REST, AJAX and cron, plus the recovery path.
- **FR-008**: When the proposed mode equals the current mode, applying MUST be unavailable and the
  screen MUST say no change is required.
- **FR-009**: A no-change submission reaching the server MUST NOT write a history entry and MUST NOT
  report success. *(Today `OperationsModeStore::set()` writes both unconditionally.)*
- **FR-010**: Validation MUST run on the server for: requested mode, current mode, capability,
  nonce, confirmation phrase, maintenance acknowledgement, readiness target, override permission and
  override reason. Client-side validation may assist; it may never be the only check.
- **FR-011**: A failed submission MUST preserve the operator's choices, move focus to the first
  error, and explain what to change.
- **FR-012**: Duplicate submission MUST be prevented, and the flow MUST remain Post/Redirect/Get so
  Back and refresh cannot re-apply a mode.
- **FR-013**: Every state-changing control MUST work with JavaScript disabled.

**Truthfulness**

- **FR-014**: The WordPress environment type and the CoreX operations mode MUST both be shown, named
  distinctly, with a clear warning when they conflict. Nothing may imply that choosing a mode alters
  hosting, PHP, the database, deployment, or `WP_ENVIRONMENT_TYPE`.
- **FR-015**: Readiness MUST present blockers, warnings and passed checks as separate groups, each
  counted, with the time it was evaluated and a resolution link per blocker.
- **FR-016**: Login protection MUST show the address the login is actually served at, distinguished
  from any unsaved form value, with the recovery command and the default-endpoint state.
- **FR-017**: A slug that is sanitised or substituted MUST be reported as the value actually in
  force, with the substitution explained. The typed value MUST NOT be presented as live.
- **FR-018**: Slug validation MUST additionally reject collisions the existing pattern cannot see —
  an existing published page, and an existing rewrite endpoint.
- **FR-019**: Lockouts MUST distinguish active from expired, name the reason and the expiry, and
  offer recovery. No raw IP may be shown where the system stores a hash. An unlock control may
  appear only if it is real, capability-gated, nonce-protected, audited and narrow in scope.

**Presentation**

- **FR-020**: Section spacing MUST come from one layout container using design tokens, not from
  margins declared per section. No arbitrary pixel values, no collapsed or doubled margins, no
  fixed-height empty canvases.
- **FR-021**: Notices MUST be scoped to what they describe — page, section, field, or action result
  — MUST NOT duplicate one another, and dismissing one MUST NOT imply an unresolved condition was
  resolved.
- **FR-022**: The screen MUST hold together in RTL, at 375 px, at 200 % zoom, and in light and dark,
  with no horizontal overflow beyond stock wp-admin's own (DECISIONS #163).
- **FR-023**: Every visible date MUST come from the shared date contract (spec 076) — verified here,
  not reimplemented.

### Key Entities

- **Operations mode**: the operator-declared CoreX state. Distinct from, and never a proxy for, the
  WordPress environment type.
- **WordPress environment type**: what the host and configuration declare. Read-only to CoreX.
- **Readiness snapshot**: the evaluated checks, already grouped by state, with the moment evaluated.
- **Login protection policy**: the saved settings, and separately the address actually in force.
- **Lockout**: an identity, a reason, a window, and whether it is still in effect.
- **Mode change**: a real transition from one mode to a different one — by definition not a
  re-application of the same one.

## Success Criteria *(mandatory)*

- **SC-001**: An operator can answer "is this site live, and is anything blocking it?" from the
  overview alone, without scrolling to another area.
- **SC-002**: Reaching any one of the screen's areas takes one action from arrival, against a
  baseline of scrolling a ~3,500 px column.
- **SC-003**: Zero confirmation controls appear for a mode that does not require them. The current
  count on a Development site is two.
- **SC-004**: Re-applying the current mode produces no history entry and no success message.
  Currently it produces both.
- **SC-005**: The environment and the mode are both visible at all times, and a conflict between
  them is stated rather than inferable.
- **SC-006**: Every state-changing control works with JavaScript disabled.
- **SC-007**: No slug can be saved that collides with an existing page or rewrite endpoint, and no
  substituted slug is ever displayed as though it were the one typed.
- **SC-008**: Section spacing derives from one token-based container; measured gaps between sections
  are uniform.
- **SC-009**: The full acceptance matrix — RTL, 375 px, 200 % zoom, light and dark, keyboard only —
  passes with no horizontal overflow beyond wp-admin's own and no console error.

## Assumptions

- **A tabbed architecture is the intended grouping**, per the brief, unless the plan finds evidence
  against it. The alternative worth weighing is progressive disclosure within one column; the plan
  decides and records it in DECISIONS.
- **Progressive disclosure in the mode form needs a JavaScript enhancement**, because the form is
  currently a plain server-rendered POST with no client behaviour at all. FR-013 therefore matters:
  the no-JS path must still present and validate every confirmation, which means the server must
  render what the *currently saved* mode implies and validate whatever arrives.
- **No new capability is introduced.** Every control this spec describes already has a real
  implementation behind it; the work is arrangement, disclosure, and the two truthfulness fixes.
- **The Cache & Performance section is not added here.** It arrives with spec 078, against the
  architecture this spec establishes. An empty tab now would be exactly the dead end the mandate
  forbids.
- **The unlock control stays out** unless the audit finds a real, audited unlock path already
  implemented. Offering one that does not exist is the defect this project keeps removing.
