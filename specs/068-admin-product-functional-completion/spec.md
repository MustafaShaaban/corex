# Feature Specification: CoreX Product Functional Completion

**Feature Branch**: `fix/067-admin-shell-and-completion`

**Created**: 2026-07-03

**Status**: Ready for Planning

**Input**: Owner directive to implement the complete approved CoreX admin and product design as real, secure, tested behavior. The design is the functional contract: approved screens and controls may not remain sample, planned, future, reference-only, read-only, placeholder-only, or dead.

## Product Contract

This specification supersedes the scope deferrals recorded for Specs 063, 065, and the early Spec 067 audit wherever they conflict with the owner directive. Existing completed work remains valid only where it satisfies this specification. A visible control is acceptable only when it performs its stated action or when a genuinely optional dependency is absent and the screen provides a real activation or installation path.

The authoritative visual references are the approved files in `F:\Work\CoreX.zip`, led by `Corex Final Design Gap-Closure Package.dc.html`, `Corex Design Closure & Freeze Pack.dc.html`, `Corex Final Design Inventory.dc.html`, and the area-specific design files. The owner directive changes approved current product surfaces from presentation references into required functionality.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Operate CoreX from one complete command center (Priority: P1)

An administrator opens CoreX and sees a consistent product shell, truthful site state, real launch readiness, usable navigation, real integrations, and a complete recent-activity history. They can move between every product area without encountering unfinished WordPress-looking pages, inactive controls, or misleading data.

**Why this priority**: Every other workflow starts here. If the shell, overview, navigation, permissions, or status data are unreliable, the product cannot be operated safely.

**Independent Test**: Open every CoreX route in dark and light modes, LTR and RTL, at desktop and mobile widths; verify the correct rail item and breadcrumb, real status values, keyboard-visible focus, stable hover, and complete empty/loading/error/permission behavior.

**Acceptance Scenarios**:

1. **Given** a configured site with activity, **When** an administrator opens Overview, **Then** all status cards, readiness items, summaries, integrations, and recent events reflect current site state and link to working destinations.
2. **Given** a new site with no activity, **When** Overview loads, **Then** it shows a truthful empty state and never invents counts or events.
3. **Given** any CoreX subpage, **When** it renders, **Then** the CoreX shell fills the available workspace, the active rail item and breadcrumb are correct, and no generic unfinished inner page appears.

---

### User Story 2 - Build, publish, and run a form flow (Priority: P1)

An administrator creates a flow visually, configures its form schema, validation, routing, emails, success behavior, and test mode, publishes it, and places it on the front end. A visitor submits it and the complete validation, spam, storage, routing, email, inbox, and timeline workflow executes.

**Why this priority**: Forms and submissions are a central product workflow and currently contain the largest gap between the approved design and working behavior.

**Independent Test**: Create a flow containing representative field types and routing rules, publish it, render it through a supported block, submit valid and invalid requests, and verify storage, assignment, email capture or delivery, inbox visibility, and timeline events.

**Acceptance Scenarios**:

1. **Given** an administrator with Forms access, **When** they add, edit, reorder, and remove fields and save a draft, **Then** the schema persists and restores exactly.
2. **Given** a complete draft, **When** it is published, **Then** supported front-end blocks render the published version and submissions use that version.
3. **Given** a visitor submission, **When** validation and anti-spam checks pass, **Then** the submission is stored, routed, emailed or captured according to environment, shown in the Inbox, and recorded in a timeline.
4. **Given** test mode, **When** the flow is exercised, **Then** the same pipeline runs with a test marker and test records remain excluded from normal metrics and exports by default.

---

### User Story 3 - Work a submissions inbox safely (Priority: P1)

An authorized team member searches and filters submissions, opens a complete detail view, assigns ownership, changes status, records internal notes, follows the event timeline, performs safe email actions, and exports only the records and fields they are allowed to access.

**Why this priority**: A stored form submission is useful only when teams can act on it and personal data is handled safely.

**Independent Test**: Seed accessible and inaccessible submissions, exercise every filter and bulk action, inspect detail metadata and timeline, add notes, assign ownership, resend a related email, and verify exports and compliance audit records.

**Acceptance Scenarios**:

1. **Given** mixed submissions, **When** a user filters by flow, status, owner, and date, **Then** only matching accessible records appear and pagination preserves filters.
2. **Given** selected submissions, **When** a bulk action is confirmed, **Then** exactly those records change and each change appears in history.
3. **Given** an export containing personal information, **When** it completes, **Then** scope, actor, time, columns, and record selection are written to export history and the activity log.

---

### User Story 4 - Explore and manage registered data (Priority: P1)

An authorized operator browses registered data sources and models, searches and filters real records, views schemas and details, creates or edits records through write-capable models, previews destructive changes, imports validated data, exports controlled data, and manages migrations with snapshots and rollback where supported.

**Why this priority**: The approved Data and Data Models screens promise management workflows, not read-only demonstrations.

**Independent Test**: Register one read-only and one write-capable source; verify query controls, record operations, dry-run import, rejected-row reporting, audited export, migration snapshot, apply, and rollback boundaries.

**Acceptance Scenarios**:

1. **Given** a registered source, **When** query controls are applied, **Then** search, filters, sorting, and pagination change the actual result set.
2. **Given** a write-capable model, **When** a user previews and confirms a create, edit, bulk edit, or safe delete, **Then** the change persists and is audited.
3. **Given** an import file, **When** the dry run finds invalid or unknown data, **Then** no records are written and a downloadable reasoned report is available.
4. **Given** pending migrations, **When** an authorized operator confirms execution, **Then** a snapshot is created first, the migration is transactional where supported, and history records the result.

---

### User Story 5 - Protect operations, login, and access without lockout (Priority: P1)

An administrator manages environment mode, launch guardrails, login protection, CoreX abilities, access requests, and grants. Production changes require deliberate confirmation, recovery always remains possible, and every sensitive change is audited.

**Why this priority**: These controls can affect public traffic, email, indexing, and administrative access. They must be functional and cannot introduce lockout risk.

**Independent Test**: Exercise each mode transition, production blocking checks and override, maintenance access, login rate limits, recovery constant and command, role ability changes, self-lockout prevention, access request approval or denial, and external role-plugin compatibility.

**Acceptance Scenarios**:

1. **Given** blocking launch checks, **When** an administrator attempts Production, **Then** the switch is blocked unless the designed explicit override is completed.
2. **Given** Maintenance mode, **When** anonymous and administrative visitors request the site, **Then** anonymous visitors see maintenance while administrators retain access and can reverse the mode.
3. **Given** login protection is enabled, **When** repeated failures exceed policy, **Then** attempts are limited and logged without moving or renaming WordPress core files.
4. **Given** an ability edit that would remove the actor's critical access or the final full-access administrator, **When** it is submitted, **Then** the change is rejected with an explanation.
5. **Given** a user requests access, **When** an administrator approves or denies it, **Then** access state, notification, and audit history reflect the decision.

---

### User Story 6 - Run native WordPress publishing through Blog Pro (Priority: P1)

Authors and reviewers move native posts through an editorial workflow, moderate native comments, publish scheduled content, review truthful first-party analytics, manage social sharing, and use the complete approved blog front end.

**Why this priority**: Blog Pro is currently a sample/reference surface despite being an approved current product area.

**Independent Test**: Create a post, assign and review it through every editorial state, schedule and publish it, record real reading events, moderate a comment, exercise sharing, and verify the archive and single-post experience.

**Acceptance Scenarios**:

1. **Given** a native draft post, **When** it moves through review, changes, approval, scheduling, and publishing, **Then** state, assignee, notes, due date, and WordPress publication status remain consistent.
2. **Given** real reader activity, **When** an administrator selects 7, 30, or 90 days, **Then** Blog Pro calculates truthful views, reads, engagement, read time, comments, trends, and top posts without sample data.
3. **Given** a pending comment, **When** it is approved, replied to, edited, marked spam, or trashed, **Then** the native comment changes and the queue refreshes.
4. **Given** a published post, **When** a visitor opens it, **Then** metadata, sharing, related content, newsletter capture, comments, navigation, and responsive states are functional.

---

### User Story 7 - Design and deliver transactional email safely (Priority: P1)

An administrator creates and edits templates, layouts, partials, variables, routing, plain text, previews, tests, and delivery logs. Development captures mail locally, while Production requires a configured provider and deliberate live delivery.

**Why this priority**: Forms, submissions, access notifications, and system workflows depend on reliable email behavior.

**Independent Test**: Create a template, insert allowed variables, preview desktop/mobile/RTL, generate and override plain text, bind it to a flow, capture a development test, send a provider-backed test in a safe environment, and inspect or resend the logged result.

**Acceptance Scenarios**:

1. **Given** Development mode, **When** any CoreX email is dispatched, **Then** it is captured locally, not delivered to a real recipient, and logged as captured.
2. **Given** Production without a provider, **When** a live send is requested, **Then** delivery is blocked with a working setup path.
3. **Given** a template edit, **When** unsafe executable content or an unknown variable is submitted, **Then** it is rejected without changing the saved version.
4. **Given** a logged email, **When** an authorized user resends it, **Then** a new attempt follows current environment rules and both attempts remain traceable.

---

### User Story 8 - Complete setup, settings, insights, and launch readiness (Priority: P2)

An administrator completes the nine-step setup wizard, previews and safely applies a site plan, configures every approved settings area, runs real insight providers, resolves launch blockers, and can roll back the applied setup.

**Why this priority**: These surfaces turn framework capabilities into an operable site and must agree on readiness and environment state.

**Independent Test**: Complete, skip, resume, block, apply, roll back, and reset wizard scenarios; save and discard settings by role; run connected, disconnected, empty, error, and retry insight states; verify one shared readiness result.

**Acceptance Scenarios**:

1. **Given** a partially configured site, **When** the wizard opens, **Then** all nine steps show real completion, blockers, skip/resume state, and an accurate percentage.
2. **Given** an apply plan with conflicts, **When** the administrator selects keep, replace, or suffixed-slug choices, **Then** a backup is required and no existing content is overwritten silently.
3. **Given** a connected insight provider, **When** a check runs, **Then** results, recommendations, timestamp, and history come from that execution; failures offer retry and setup actions.
4. **Given** unsaved settings, **When** the administrator navigates away or discards, **Then** the UI warns or restores without silently saving.

---

### User Story 9 - Use the complete component, theme, authentication, and docs experience (Priority: P2)

Site builders and visitors use the approved block/component library, header and navigation variants, company and content pages, system states, approved account surfaces, and product documentation with consistent tokens, accessibility, responsive behavior, and RTL support.

**Why this priority**: The owner directive includes every approved design beyond wp-admin; the product is incomplete if those surfaces remain prototypes.

**Independent Test**: Render each approved page/template and component state, operate header/search/drawer/mega-menu entirely by keyboard, verify reduced motion and RTL, complete supported account flows, and navigate/search/copy within Docs.

**Acceptance Scenarios**:

1. **Given** any approved component, **When** its default, hover, focus, loading, empty, error, disabled, and success states apply, **Then** each state is visible, accessible, and behaviorally correct.
2. **Given** mobile or RTL layout, **When** navigation, search, drawers, menus, sliders, tabs, and accordions are used, **Then** focus, direction, gestures, controls, and reading order remain correct.
3. **Given** a supported account flow, **When** a visitor registers, signs in, recovers a password, edits a profile, reviews notifications, or revokes a session, **Then** the requested action completes securely with clear feedback.
4. **Given** a Docs reader, **When** they search, use the command palette, change version, copy code, or navigate previous/next and on-page links, **Then** the action works and preserves context.

---

### User Story 10 - Trust every mutation, metric, and state (Priority: P1)

Owners can trust that visible data is real, personal information is controlled, mutations are authorized and protected, dangerous actions are previewed and confirmed, and audit evidence explains what happened.

**Why this priority**: Truthfulness and safety are product-wide invariants, not individual screen features.

**Independent Test**: Scan every approved current screen for prohibited placeholder language and dead controls, execute all mutation paths with authorized, unauthorized, valid, invalid, stale, and replayed requests, and reconcile resulting data and audit history.

**Acceptance Scenarios**:

1. **Given** any visible metric or list, **When** its source has no records or is unavailable, **Then** the UI shows a truthful empty or error state rather than invented values.
2. **Given** any mutation, **When** authorization or request authenticity fails, **Then** no state changes and the response is safe and clear.
3. **Given** a destructive, production, personal-data, import, export, migration, or access action, **When** it executes, **Then** the required preview, confirmation, and audit evidence exist.

### Edge Cases

- An optional add-on or provider is missing, inactive, misconfigured, partially configured, or fails during use.
- An active add-on depends on another add-on an administrator attempts to disable.
- A request is repeated, stale, forged, interrupted after confirmation, or retried after a partial external failure.
- A user loses access between loading a screen and submitting an action.
- The current administrator is the only remaining full-access administrator.
- A flow is edited while a visitor submits an older published version.
- Routing has no match, an invalid recipient, a deleted user, an empty team, or a cyclic fallback.
- Spam, captcha, honeypot, validation, storage, routing, or email fails at different pipeline stages.
- Personal-data fields are hidden, renamed, removed, or added after older submissions exist.
- An export or import exceeds the interactive page size and must remain bounded and resumable.
- CSV input has duplicate headings, unknown columns, invalid encodings, formulas, oversized rows, or mixed personal data.
- A migration or kit apply fails after a snapshot but before completion.
- Production has no provider, indexing is discouraged, debug display is enabled, or legal pages are missing.
- Maintenance mode is enabled while an administrator's session expires.
- Login protection receives proxy addresses, IPv6, clock changes, or a high-volume distributed attack.
- Analytics consent or privacy policy disallows a measurement that a provider could otherwise collect.
- A post, comment, template, flow, user, role, or source referenced by history is later deleted.
- Browser width is 375px, zoom is 200%, direction changes to RTL, or reduced motion is requested.
- JavaScript fails to load; server-rendered navigation, forms, and safety feedback remain usable where required.

## Requirements *(mandatory)*

### Global Product and Safety Requirements

- **FR-001**: Every approved current screen, route, subpage, tab, card, table, control, modal, drawer, filter, workflow, state, and preview MUST provide its designed real behavior.
- **FR-002**: Approved current surfaces MUST contain no fake metrics, static sample records presented as live, placeholder-only UI, dead controls, or messages describing required behavior as planned, future, reference-only, coming soon, or read-only.
- **FR-003**: A control gated by a genuinely optional dependency MUST explain the current state and provide a working activation, installation, connection, or setup path.
- **FR-004**: Every mutation MUST verify the actor's current permission and request authenticity, sanitize all input, escape all output, and leave state unchanged on failure.
- **FR-005**: Sensitive configuration, access, environment, security, import, export, migration, email, submission, retention, and destructive actions MUST be audited with actor, time, kind, target, and outcome.
- **FR-006**: Destructive, production-impacting, personal-data, import, and migration actions MUST show a truthful preview and require explicit confirmation before applying changes.
- **FR-007**: Personal data MUST never be hard-deleted unless the approved workflow explicitly requests and confirms it; supported safer outcomes include archive, trash, and anonymization.
- **FR-008**: Personal-data exports MUST be permission-scoped, visibly warned, and recorded in export and activity history.
- **FR-009**: Development email MUST be captured locally and MUST NOT reach real recipients unless an administrator intentionally configures live delivery and completes the production safety path.
- **FR-010**: All product surfaces MUST support dark and light appearance, LTR and RTL direction, 200% zoom, keyboard operation, visible focus, stable hover, reduced motion, and WCAG 2.2 AA contrast and semantics.
- **FR-011**: Every CoreX route and subpage MUST use the shared shell, correct active rail state, and accurate breadcrumb without unwanted workspace whitespace or generic unfinished wp-admin styling.

### Overview and Activity Requirements

- **FR-012**: Overview MUST show real Site Health, Environment, Login Protection, and Add-ons status cards plus truthful version and environment badges.
- **FR-013**: Overview MUST calculate a real launch-readiness checklist and progress count from the same checks used by Setup, Operations, Security, Email, Captcha, legal, Forms, and Insights.
- **FR-014**: Overview MUST provide a working Setup Wizard action that resumes the correct step.
- **FR-015**: Forms & Flows summary MUST show real flow totals, open submissions, assigned submissions, unread submissions, and test-mode state.
- **FR-016**: Data summary MUST show registered sources, accessible record counts, read/write capability, and a working Explorer action.
- **FR-017**: Integrations summary MUST report CoreX Mail, Captcha, Insights providers, Media, Blog Pro, and enabled add-ons from their actual registries and configuration.
- **FR-018**: Recent activity MUST use a real shared event stream covering provider bindings, configuration, cache, mode, access, import/export, email, submission, security, and other sensitive product events.
- **FR-019**: Overview MUST show an empty activity state only when the activity store contains no events accessible to the actor.

### Add-ons Requirements

- **FR-020**: Add-ons MUST show real active, installed, update, and site-kit counts without inventing update availability.
- **FR-021**: Every registered add-on card MUST show its final logo, name, slug, version, description, runtime status, edition, registrations, dependencies, documentation, and working enable/disable control when eligible.
- **FR-022**: Enable and disable actions MUST change runtime activation safely and report the real result.
- **FR-023**: An add-on required by another active add-on MUST NOT be disabled until dependents are safely addressed.
- **FR-024**: WooCommerce-specific add-ons MUST be dependency-gated when WooCommerce is missing or inactive and MUST provide the real resolution path.
- **FR-025**: Missing packages MUST identify exactly what must be installed and how the supported installation path is performed.
- **FR-026**: Update state MUST come from a real update source or explicitly state that updates are not tracked without showing a fake count.

### Forms and Flows Requirements

- **FR-027**: Forms & Flows MUST provide a searchable and filterable flow list with state, owner or routing, field count, updated time, and a working New Flow action.
- **FR-028**: Flows MUST persist Draft, Published, Closed, and Expired lifecycle states and support save, publish, unpublish, close, and preview actions.
- **FR-029**: The editor MUST present the designed Page/Post to Flow to Form to Validation to Storage to Emails to Inbox pipeline and the configuration state of every stage.
- **FR-030**: Administrators MUST be able to add, edit, reorder, and remove persisted form fields.
- **FR-031**: Field settings MUST support label, key, type, placeholder, help text, default value, required state, options, and validation rules.
- **FR-032**: Built-in fields MUST include text, email, phone, number, textarea, select, multi-select, radio, checkbox, date, time, URL, hidden, consent, rating, and step.
- **FR-033**: Built-in validation MUST include required, email, URL, minimum and maximum length, numeric, pattern, and registered custom validation.
- **FR-034**: Routing rules MUST evaluate top-down with first-match-wins semantics and a required fallback.
- **FR-035**: Routing targets MUST support fixed email, WordPress user, role, team or department, page owner, post author, flow owner, field value, and registered custom rules.
- **FR-036**: Email configuration MUST support submitter confirmation, team notification, administrator failure alert, template binding, recipient mapping, and reply-to mapping.
- **FR-037**: Success behavior MUST support inline message, page redirect, URL redirect, and registered custom success states with a visitor preview.
- **FR-038**: Test mode MUST run validation, spam, captcha, honeypot, storage, routing, environment-aware email, and timeline behavior while marking the submission as test.
- **FR-039**: Test submissions MUST remain identifiable and excluded from ordinary metrics and exports unless explicitly included.
- **FR-040**: Developers MUST be able to register field types, validations, flow actions, routing rules, email variables, and success states through stable extension contracts.
- **FR-041**: Flow, Form, Success Message, Subscribe, Survey, and CTA plus Flow blocks MUST bind to persisted published flows and render working front-end behavior.
- **FR-042**: A visitor submission MUST execute the complete validation, anti-spam, storage, routing, email, inbox, and timeline sequence with a traceable outcome at each stage.
- **FR-043**: Flow edits MUST create a version snapshot so historical submissions retain the schema and consent statement used at submission time.
- **FR-044**: Failed pipeline stages MUST preserve already-committed safe state, expose retry where appropriate, and never report success falsely.
- **FR-045**: No approved Forms & Flows control may remain code-defined-only when the design exposes an administrative workflow.

### Submissions Requirements

- **FR-046**: Submissions MUST provide a selectable inbox with unread marker, submitter name and email, flow, status, owner, date, search, pagination, and filters for flow, status, owner, and date range.
- **FR-047**: Supported statuses MUST include New, In Progress, Replied, Closed, Spam, and Archived.
- **FR-048**: Bulk actions MUST include mark read, assign, export selected, mark spam, archive, and clear selection.
- **FR-049**: Detail MUST show submitted fields, hidden metadata, UTM data, consent snapshot, form version, creation date, exported state, retention state, spam state, related emails, internal notes, and timeline.
- **FR-050**: Assignment MUST support eligible users, teams, and roles while preserving assignment history.
- **FR-051**: Internal notes MUST record author and timestamp and respect note visibility permissions.
- **FR-052**: Safe email actions MUST include reply where allowed, resend, and opening related delivery logs.
- **FR-053**: Export scope MUST support all accessible, current filter, and selected rows.
- **FR-054**: Export column options MUST support submitted fields, hidden metadata, UTM, consent snapshot, and permitted notes.
- **FR-055**: Export history and compliance logs MUST show who exported which scope and fields and when.
- **FR-056**: Retention MUST integrate with submission state and provide dry-run, archive/trash/anonymize, and confirmed execution according to policy.
- **FR-057**: Test submissions MUST be visibly marked and excluded from normal metrics and exports unless explicitly included.
- **FR-058**: Unauthorized or inaccessible records MUST never leak through counts, filters, detail, export, or related email actions.

### Data Explorer and Data Models Requirements

- **FR-059**: Data Explorer MUST list registered sources and models with real schema, access, and read/write capability.
- **FR-060**: Search, filters, sorting, and pagination MUST operate on the real source result set.
- **FR-061**: Record tables and detail drawers MUST render source-defined fields and truthful loading, empty, error, and permission states.
- **FR-062**: Export MUST support current filter, selected rows, and all accessible rows with history and personal-data warnings.
- **FR-063**: Safe bulk actions MUST be source-declared; delete MUST require preview, confirmation, and audit.
- **FR-064**: New and Edit Record actions MUST appear only for sources with a real write adapter and MUST persist through that adapter.
- **FR-065**: Data Models MUST provide model catalog, model detail, records, search, selection, bulk edit, safe delete, create, edit, and record detail for write-capable models.
- **FR-066**: Import MUST support CSV upload, column mapping, dry-run validation, rejected reasons, unknown-column policy, personal-data detection, downloadable report, and confirmed commit through a model write adapter.
- **FR-067**: Import commit MUST write only rows and values approved by the dry run and MUST audit counts and rejected outcomes.
- **FR-068**: Export MUST support filtered, selected, and all scopes; column selection; CSV; XLSX when offered; history; and personal-data warning.
- **FR-069**: Migrations MUST show pending plans, production warning, pre-migration snapshot, transactional execution where supported, rollback where supported, and history.
- **FR-070**: Every model mutation MUST have a dry-run or preview before apply.

### Operations and Security Requirements

- **FR-071**: Operations MUST display active environment, platform environment type, and mode-specific guardrails.
- **FR-072**: Development, Staging, Production, and Maintenance mode selection MUST persist and change actual product behavior.
- **FR-073**: Production MUST be blocked while blocking launch checks remain unless the designed explicit override is completed.
- **FR-074**: Production confirmation MUST explain live email, analytics, indexing, and public impact and require the administrator to type `PRODUCTION`.
- **FR-075**: Maintenance MUST affect anonymous visitors, preserve administrator access, and remain reversible.
- **FR-076**: Security Center MUST show and manage login-protection state, custom login URL, failed-login limiting, threshold and window, and configured Captcha integration.
- **FR-077**: When enabled, protected default login endpoints MUST return an honest not-found response to unauthenticated visitors without renaming or moving core files.
- **FR-078**: Login activity MUST report failed attempts, lockouts, and supported successful sign-ins with an enforceable retention policy.
- **FR-079**: Recovery MUST support the documented unguard constant and a `security reset-login` administrative command.
- **FR-080**: Recovery paths MUST be tested and MUST not depend on the protected login URL.
- **FR-081**: Hardening checks MUST include HTTPS, disabled file editor, hidden debug display, absence of a default administrator account, and every other check shown in the approved design.
- **FR-082**: Operations, login, recovery, and hardening changes MUST be audited.
- **FR-083**: Login protection MUST account for trusted proxy configuration without accepting spoofed client addresses.

### Access and Abilities Requirements

- **FR-084**: Access & Abilities MUST provide an editable role matrix for CoreX abilities.
- **FR-085**: Abilities MUST be grouped by CoreX admin, Forms & Flows, Submissions, Data, Data Models, Email Studio, Blog Pro, Operations/Security, Setup Wizard, and Settings.
- **FR-086**: Matrix states MUST include Allowed, Denied, Inherited, and Locked by code or configuration.
- **FR-087**: Critical permissions for CoreX admin access, dangerous actions, and access management MUST remain protected.
- **FR-088**: The system MUST prevent removing the current actor's critical access and MUST preserve at least one full-access administrator.
- **FR-089**: External role or capability plugins MUST be detected; when present, CoreX MUST manage only `corex_*` abilities and show platform capabilities as read-only with a conflict explanation.
- **FR-090**: Access audit MUST record role and ability changes, actor, time, kind, and old/new values.
- **FR-091**: Denied access MUST return a proper forbidden response, write an audit entry, and offer a safe Overview or dashboard destination.
- **FR-092**: Users MUST be able to request access to a CoreX area and administrators MUST be able to approve or deny requests.
- **FR-093**: Administrators MUST be able to grant access to a user or role through the designed send/grant workflow.
- **FR-094**: Access request, approval, denial, and grant notifications MUST use Email Studio when enabled and follow environment delivery rules.
- **FR-095**: Every CoreX screen and dangerous action MUST enforce its declared ability.

### Blog Pro and Blog Theme Requirements

- **FR-096**: Blog Pro MUST operate on native WordPress posts, comments, users, taxonomies, statuses, schedules, and metadata rather than replacing them.
- **FR-097**: Analytics MUST report real views, reads, available unique readers, average read time, comments, trends, 7/30/90-day ranges, a views/reads chart, and top posts.
- **FR-098**: When no external provider is available, privacy-friendly first-party counters MUST provide truthful measurements subject to privacy policy.
- **FR-099**: Top posts MUST show title, views, engagement or click-through measure, average read time, comments, and trend.
- **FR-100**: Editorial states MUST include Draft, Ready for Review, Needs Changes, Approved, Scheduled, and Published and remain synchronized with native post state.
- **FR-101**: Editorial workflow MUST support review notes, owner or assignee, and due date when configured.
- **FR-102**: Scheduled posts MUST use native scheduling and publish through the normal platform process.
- **FR-103**: Comment moderation MUST support pending, approved, spam, trash, first-comment, likely-spam, held-for-review, approve, reply, edit, spam, and trash actions where applicable.
- **FR-104**: Authors MUST show name, role or title, post count, views, and engagement based on real data.
- **FR-105**: Social sharing MUST support configured platforms, share controls, copy link, labels, and real share-click logging when enabled.
- **FR-106**: Blog settings shown in the approved design MUST persist and affect the blog experience.
- **FR-107**: The front-end blog MUST provide index, single, category, tag, date, and author archives.
- **FR-108**: Blog templates MUST provide featured image, author/date/read time, tags, comments, moderation notice, sharing, related content, newsletter capture, previous/next, no-results, and pagination.
- **FR-109**: Analytics unavailable or provider-not-configured states MUST explain the real state without sample metrics.
- **FR-110**: No Blog Pro surface may identify the product as future, reference-only, or sample data.

### Email Studio Requirements

- **FR-111**: Email Studio Overview MUST show real delivery mode, provider state, template count, delivered/captured/failed counts, recent test sends, and health checks.
- **FR-112**: Development MUST visibly state that outgoing mail is captured locally and not delivered.
- **FR-113**: Production live delivery MUST require a configured provider and protection against accidental sends.
- **FR-114**: Template list MUST show name, subject, status, edited time, and working open/edit action.
- **FR-115**: Template editor MUST persist subject, from name, from address, sanitized HTML body, and safe merge-variable insertion without executable server code.
- **FR-116**: Template detail MUST provide working Edit, Preview, Plain Text, Test Send, Routing, and Delivery Logs tabs.
- **FR-117**: Variables MUST be grouped by Site, Recipient, Submission, and Links and validated against the registered variable catalog.
- **FR-118**: Preview MUST support desktop, mobile, and RTL views using clearly identified preview context data that cannot be mistaken for live metrics or delivery.
- **FR-119**: Plain text MUST support automatic generation and a persisted manual override.
- **FR-120**: Test send MUST capture or deliver according to environment and write a truthful result log.
- **FR-121**: Routing MUST bind flow triggers to templates, recipient rules, and reply-to rules.
- **FR-122**: Delivery logs MUST show recipient, subject/template, state, time, supported delivery events, and a working resend action.
- **FR-123**: Default transactional, minimal system, newsletter, and dependency-gated Woo layouts MUST be available when shown, with editable email-safe header, accent, body, button, and footer.
- **FR-124**: Reusable header, footer, unsubscribe, preferences, and privacy partials MUST be editable and usable by templates where applicable.
- **FR-125**: Health checks MUST detect missing variables, missing plain text, required unsubscribe/preference links, invalid reply-to, and missing provider.

### Insights Requirements

- **FR-126**: Insights MUST expose a provider registry with Connected, Disconnected, Empty, Error, and Setup Required states based on real provider state.
- **FR-127**: Insights MUST run PageSpeed and Core Web Vitals checks and retain result history.
- **FR-128**: Cloudflare readiness and checks shown in the approved design MUST use real configuration and provider responses.
- **FR-129**: Website, AI-agent, release, performance, security, and operational readiness cards MUST use real checks and no fake score.
- **FR-130**: Recommendations and What to Fix Next MUST derive from current failed or warning checks.
- **FR-131**: Every result MUST show its last checked time and provide working run, retry, and setup actions as applicable.
- **FR-132**: Environment-gated checks MUST explain why they cannot run and provide a real verification path.
- **FR-133**: Insights MUST not present required current widgets as Planned.

### Setup Wizard Requirements

- **FR-134**: Setup Wizard MUST provide the approved nine-step flow, percentage, step list, begin, skip, resume, blocked, and unsafe-to-launch states.
- **FR-135**: Brand setup MUST persist company name, tagline, phone, email, address, primary action label/link, social links, and designed service/keyword/tag data.
- **FR-136**: Kit selection MUST support Company, Portfolio, and dependency-gated WooCommerce when shown.
- **FR-137**: Demo content levels MUST include Minimal, Standard, and Full Demo with a real live preview.
- **FR-138**: Apply planning MUST identify created, adopted, skipped, and conflicting pages or content.
- **FR-139**: Conflicts MUST support Keep Mine, Replace, and Create Suffixed Slug choices.
- **FR-140**: Apply MUST require a successful backup and explicit confirmation.
- **FR-141**: Rollback and reset MUST reverse only tracked setup changes and preserve unrelated owner content.
- **FR-142**: Final summary MUST report actual outcomes and a launch checklist covering indexing, debug, environment, email, captcha/security, legal, Forms testing, and performance/readiness.
- **FR-143**: No setup action may overwrite existing content silently.

### Settings Requirements

- **FR-144**: Settings MUST provide General, Appearance, Operations/Security, Email/Captcha, Media, Retention, Advanced, Architecture, Data Sources, and Design Tokens sections from the approved design.
- **FR-145**: Settings MUST support save, discard, unsaved-change detection, validation errors, and role-based read-only state.
- **FR-146**: Secret fields MUST remain write-only and preserve existing values when left empty.
- **FR-147**: Media settings MUST manage WebP enablement, JPEG/PNG conversion, quality, minimum saving threshold, server support, regeneration, and safe originals.
- **FR-148**: Regeneration MUST have a working administrative action and matching command guidance, progress, failure reporting, and resumability.
- **FR-149**: Retention MUST cover submissions, email logs, activity logs, consent snapshots, and export logs.
- **FR-150**: Retention MUST provide dry-run and safe prune, trash, or anonymize behavior according to data type and policy.
- **FR-151**: Advanced MUST provide debug output, feature flags, webhook signing secret, container diagnostics, and reset developer settings.
- **FR-152**: Danger-zone actions MUST explain impact and require explicit confirmation.
- **FR-153**: Architecture, source, and design-token settings MUST reflect and update real registries or configuration and never act as inert display-only controls.

### Blocks, Theme, Navigation, Authentication, and Docs Requirements

- **FR-154**: The product MUST provide the approved Blocks & Components inventory and working rich tabs, accordions, sliders/carousels, form states, admin components, and core UI components.
- **FR-155**: Header variants, top bar, sticky/scrolled states, search overlay, mobile drawer, and mega menu MUST be functional, keyboard operable, accessible, RTL-correct, and reduced-motion safe.
- **FR-156**: Theme templates MUST cover Home, About, Services, Contact, Landing, Blog, Single Post, Portfolio/Project, Search, No Results, 404, Maintenance, Loading, Comments, Newsletter, and Footer as approved.
- **FR-157**: Theme templates MUST use real site content or truthful empty states and MUST not embed business logic in the theme.
- **FR-158**: Approved authentication/profile surfaces MUST provide login, registration, forgot/reset password, profile, notifications, and active-session workflows through the appropriate product layer.
- **FR-159**: Authentication workflows MUST keep back-office and front-office concerns separate and preserve lockout-safe recovery.
- **FR-160**: Approved Docs UI MUST provide sidebar, search, command palette, version selector, API/class reference, copy actions, previous/next, and on-page navigation.
- **FR-161**: Every block and component MUST use product design tokens, direction-aware layout, conditional assets, accessible semantics, and resilient no-content/error states.
- **FR-162**: Sliders and motion MUST avoid autoplay by default, expose controls and status, pause appropriately, and honor reduced motion.
- **FR-163**: Tabs, accordions, drawers, overlays, menus, modals, and command palettes MUST manage focus, Escape, outside interaction, and return focus correctly.
- **FR-164**: Mobile layouts MUST avoid document-level horizontal scrolling at 375px while preserving intentional contained table or code scrolling.
- **FR-165**: Docs and user-facing guidance MUST match shipped behavior and remove stale future/deferred statements for completed required features.
- **FR-166**: Design inventory and roadmap MUST identify every approved current area as implemented only after its functional and visual evidence passes.
- **FR-167**: No client/company site may be started or recommended as the next step until this specification's completion audit passes.

### Key Entities

- **Activity Event**: A durable, permission-aware record of a CoreX action or system outcome, including actor, kind, target, time, context, and result.
- **Add-on**: An installable CoreX capability with identity, version, edition, dependencies, registrations, runtime state, documentation, and update state.
- **Flow**: A versioned lifecycle object connecting placement, form schema, validation, storage, routing, emails, success behavior, owner, and test state.
- **Flow Field**: A versioned form input definition with type, display settings, defaults, options, validation, and extension metadata.
- **Routing Rule**: An ordered condition and destination definition evaluated for a submission, with fallback and traceable result.
- **Submission**: A stored visitor or test response tied to a flow and version, with status, assignment, fields, metadata, consent, spam state, retention, emails, notes, and timeline.
- **Submission Note**: An internal, permission-scoped note with author and timestamp.
- **Data Source**: A registered readable dataset that declares schema, permissions, query abilities, record actions, personal-data fields, and optional write adapter.
- **Data Model**: A manageable data contract supporting records, validation, import/export, and migrations where declared.
- **Import Run**: A dry-run and optional confirmed commit with mapping, validation results, rejected rows, personal-data findings, actor, and outcome.
- **Export Run**: A permission-scoped export with source, filter or selection, columns, format, personal-data classification, actor, and outcome.
- **Migration Run**: A planned or applied schema change with snapshot, transaction/rollback support, actor, state, and history.
- **Operations Mode**: The persisted Development, Staging, Production, or Maintenance state with readiness, confirmation, behavior, and audit history.
- **Login Security Policy**: Login route, rate limit, captcha, retention, recovery, and trusted-network configuration.
- **CoreX Ability**: A product-area permission with role grant state, inheritance, lock state, and audit history.
- **Access Request**: A user's request for a CoreX ability or area, with reason, reviewer decision, notification, and history.
- **Editorial Item**: Native post state plus CoreX review state, assignee, due date, notes, and analytics relationship.
- **Reading Event**: A privacy-aware first-party or provider event used to calculate truthful blog analytics.
- **Email Template**: A persisted safe subject/from/body/plain-text definition with variables, layout, partials, status, and edit history.
- **Email Route**: A trigger-to-template binding with recipient and reply-to rules.
- **Email Attempt**: A captured, queued, sent, failed, bounced, opened, or other supported delivery event with provider context and retry relationship.
- **Insight Provider**: A registered check provider with configuration state, run capability, result history, errors, and setup path.
- **Setup Plan**: A previewable and reversible set of brand, kit, content, configuration, conflict, backup, apply, and rollback decisions.
- **Retention Policy**: Per-data-type duration and safe disposition rules with dry-run and audit history.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: All approved current routes, subpages, tabs, controls, and actions in the owner requirement matrix have direct functional evidence; none are evidenced solely by static markup or screenshots.
- **SC-002**: A repository and rendered-screen scan finds zero prohibited current-product messages describing required behavior as planned, future, reference-only, sample-data, coming-soon, placeholder-only, or read-only.
- **SC-003**: A control inventory finds zero dead enabled buttons, links, filters, toggles, drawers, modals, or actions across approved current surfaces.
- **SC-004**: Every visible count, metric, score, chart, record, status, and event is traceable to a real store, provider result, or runtime fact; unavailable sources produce truthful states.
- **SC-005**: The complete Forms workflow succeeds from visual creation through front-end submission, validation, anti-spam, storage, routing, environment-aware email, Inbox, and timeline.
- **SC-006**: The complete Submissions workflow succeeds for search/filter, detail, assignment, status, notes, related email, bulk actions, retention, export, and audit without exposing inaccessible records.
- **SC-007**: Data query controls alter real result sets, write-capable models complete previewed mutations, imports write nothing before approval, exports are logged, and migrations snapshot before apply.
- **SC-008**: Production, maintenance, login protection, recovery, and access workflows pass lockout tests and retain at least one recovery-capable full-access administrator.
- **SC-009**: Blog Pro contains zero sample analytics; native editorial, scheduling, comments, author, sharing, and first-party/provider analytics workflows complete with real records.
- **SC-010**: Email Studio can persist and render templates/layouts/partials, validate variables, capture Development sends, require a Production provider, route from flows, and log/retry outcomes truthfully.
- **SC-011**: Setup completes all nine steps, requires backup before apply, resolves conflicts explicitly, supports rollback, and reports actual outcomes.
- **SC-012**: Overview, Setup, Operations, Security, Email, Captcha, legal, Forms, and Insights agree on one launch-readiness result at the same point in time.
- **SC-013**: Every mutation rejects unauthorized or invalid-authenticity requests with no state change, and every sensitive successful or failed action produces its required audit evidence.
- **SC-014**: Personal-data export, retention, anonymization/trash, and consent-history tests pass without unconfirmed hard deletion or data leakage.
- **SC-015**: Every CoreX admin screen and approved front-end/docs surface passes dark, light, LTR, RTL, keyboard, focus, hover, reduced-motion, 200%-zoom, 375px, loading, empty, error, and permission-state checks applicable to it.
- **SC-016**: All relevant PHP, integration, JavaScript, browser, build, dependency, distribution, and documentation checks pass with no ignored product-critical failure.
- **SC-017**: Clean-code, WordPress, WooCommerce where applicable, test, and documentation guards pass on the final diff.
- **SC-018**: `PROGRESS.md`, `ROADMAP.md`, design inventory/status, relevant READMEs, product docs, and decisions accurately describe the implemented behavior and remaining genuinely optional dependencies.
- **SC-019**: No approved screen contains fake data, dead controls, or placeholder-only UI, and no required workflow remains deferred.
- **SC-020**: The final completion report maps every requirement in this specification to current source, automated test, runtime evidence, or rendered evidence and identifies no missing or indirect proof.

## Assumptions

- The owner has approved automatically following the recommended safe implementation choice for routine decisions; only a true external dependency or irreversible scope expansion requires interruption.
- Existing completed CoreX behavior is reused where it satisfies this specification and is replaced where it preserves a deferred or presentation-only end state.
- WordPress-native posts, comments, users, roles, schedules, media, and site configuration remain authoritative for their domains.
- Optional integrations remain optional; missing dependencies are resolved through truthful, working setup paths rather than fabricated behavior.
- Current product work remains on the active PR branch and in the normal repository root; no client-site work or generated runtime directory is used as source.
- The design package is authoritative for layout and interaction, while runtime values always come from current site state.
- Where the design offers multiple safe implementations, the recommended choice is the simplest one that provides the full required behavior, preserves extension contracts, and avoids lockout or data loss.
- The system may process large imports, exports, analytics, and email work asynchronously, provided progress, retries, results, and audit history remain truthful.

## Scope Boundaries

- **In scope**: Every feature, workflow, state, and product surface explicitly required in this specification and the owner directive, including the approved admin, theme, component, account, and Docs surfaces.
- **Dependency-gated, not deferred**: WooCommerce-specific behavior and third-party provider results when their dependency is absent. The product surface and real activation/setup path remain required.
- **Out of scope**: A client/company-site project, invented marketplace purchasing, fabricated licensing state, or replacing WordPress core domain systems. These exclusions do not permit hiding or disabling an approved current CoreX workflow.

## Superseded Decisions

The following earlier limitations are no longer valid for approved current surfaces: code-first-only Forms, read-only Access, sample/reference Blog Pro analytics, read-only Data Model management where the design shows writes, disabled Email Studio editing/test/routing, deferred login protection, three-step-only Setup, and planned Insights widgets. Their historical records remain, but this specification is the current product contract.
