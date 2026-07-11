# Research Decisions: CoreX Product Functional Completion

## Decision 1 — Program shape

**Decision**: Use one parent product contract with independently shippable vertical batches and a single requirement traceability matrix.

**Rationale**: The screens are not independent mockups. Forms depend on email, access, activity, data, setup, and Overview. Shared foundations prevent incompatible implementations while vertical batches remain testable and reviewable.

**Alternatives considered**:

- One giant implementation patch: rejected because failures, ownership, verification, and rollback would be unbounded.
- Fourteen unrelated feature specs: rejected because shared contracts would drift and the owner requires one completion definition.
- Cosmetic screen-first work: rejected because it preserves the wrong presentation-only end state.

## Decision 2 — Storage split

**Decision**: Keep WordPress-native content in native records; use revision-oriented private records for flow/template configuration; use CoreX managed tables for high-volume operational data such as activity, timelines, access requests, analytics events, and resumable jobs.

**Rationale**: Native posts/comments/users/schedules already provide interoperability. Operational logs need bounded indexed queries and retention that post-meta cannot provide cleanly at scale. Existing CoreX `Migrator`, `ManagedTable`, and repository foundations support the table boundary.

**Alternatives considered**:

- Store everything in options: rejected because collections, history, permissions, and retention would be unsafe and unbounded.
- Store everything as posts/meta: rejected for event/query volume and multi-column filtering.
- Replace native posts/comments/users with custom records: rejected because Blog Pro must remain native-first.

## Decision 3 — Long-running work

**Decision**: Represent import, export, media regeneration, analytics collection, bulk mutation, and migration as bounded resumable jobs. Dispatch through Action Scheduler when available and a WP-Cron/CLI-compatible fallback otherwise.

**Rationale**: Interactive requests must not time out or apply an unknown partial set. A durable cursor/result model supports progress, retries, cancellation, and audit evidence without making Action Scheduler a hard dependency.

**Alternatives considered**:

- Synchronous unbounded loops: rejected for timeout, memory, and partial-failure risk.
- Require Action Scheduler: rejected by the optional-dependency constitution rule.
- Browser-only progress with no durable job: rejected because navigation or network loss would hide the real result.

## Decision 4 — Admin interaction architecture

**Decision**: Use the existing CoreX admin shell and WordPress packages for stateful builders, inboxes, drawers, modals, and tables. Server-render the initial shell and truthful fallback states; use REST for reads and mutations.

**Rationale**: This matches the existing build, WordPress accessibility primitives, Data screen, and CoreX visual system without introducing a second UI framework. Server fallbacks keep errors and access states visible when JavaScript fails.

**Alternatives considered**:

- Continue server-rendering every interaction: rejected for field reordering, rule builders, live preview, filters, and drawers.
- Add an external CSS/JS framework: rejected by the token and global-asset rules.
- Build a standalone SPA: rejected because it duplicates WordPress navigation, permissions, loading, and asset lifecycle.

## Decision 5 — Security boundary

**Decision**: REST/AJAX routes declare CoreX middleware; `admin_menu` and `admin_post` workflows use the shared `AdminGuard`; services apply domain authorization and lockout rules before repositories write.

**Rationale**: It follows Constitution VII and keeps request safety, product policy, and persistence responsibilities separate.

**Alternatives considered**:

- Hand-write checks in every screen/controller: rejected because checks drift and are easily omitted.
- Rely only on menu capabilities: rejected because direct route/action requests still require authorization and anti-forgery protection.

## Decision 6 — Access model

**Decision**: Introduce grouped `corex_*` abilities as the editable CoreX product model. Map existing administrator capabilities for backward compatibility. Preserve native capability visibility but do not edit native capabilities when an external role manager is active.

**Rationale**: This provides the designed AAM-lite behavior without competing with dedicated role plugins or risking WordPress-wide access changes.

**Alternatives considered**:

- Edit arbitrary WordPress capabilities: rejected for conflict and lockout risk.
- Keep Access read-only: rejected by the owner directive.
- Store an independent user list disconnected from roles: rejected because WordPress role/user permission semantics remain authoritative inputs.

## Decision 7 — Activity and audit

**Decision**: Use one append-only product activity stream with explicit event kind, actor, target, context, outcome, sensitivity, and retention. Domain-specific timelines reference or project those events.

**Rationale**: Overview, Access, Security, Submissions, Email, imports/exports, configuration, and mode changes need consistent evidence. A single contract prevents fake or disconnected recent activity.

**Alternatives considered**:

- One option per domain: rejected for retention, query, and consistency problems.
- Read server logs: rejected because server logs are not a stable product contract and may contain secrets.

## Decision 8 — Email result and capture

**Decision**: Extend the mail seam to return a typed outcome and assign an attempt ID. Development routes to a capture driver. Production refuses live delivery without an explicitly configured provider. Queue and resend produce linked attempts.

**Rationale**: UI must report captured, queued, sent, or failed truthfully; a void `send()` cannot support that contract.

**Alternatives considered**:

- Keep void dispatch and assume success: rejected as fake success.
- Always call `wp_mail`: rejected because Development must never accidentally reach real recipients.

## Decision 9 — Flow and template versioning

**Decision**: Draft records are editable; publishing creates immutable numbered snapshots. Submissions and email attempts reference the exact version used.

**Rationale**: Historical fields, consent, routing, and content must remain explainable after later edits.

**Alternatives considered**:

- Mutate one live JSON document: rejected because historical records would lose their contract.
- Copy every flow into each submission: rejected as excessive duplication; a version reference plus consent snapshot is sufficient.

## Decision 10 — Data management contract

**Decision**: Data sources declare granular capabilities and field metadata, including personal-data classification. Write/import/export/migration services consume adapters rather than branching on source type.

**Rationale**: The approved Data UI can only show actions that work, while custom sources can extend management safely.

**Alternatives considered**:

- Infer write support from method existence: rejected because permissions, preview, validation, and supported bulk actions are richer than a boolean.
- One generic SQL editor: rejected because it violates model permissions and turns CoreX into an unsafe database tool.

## Decision 11 — Blog analytics privacy

**Decision**: First-party analytics use short-lived pseudonymous uniqueness, consent-aware collection, aggregated reporting, and retention controls. Raw IP addresses are not stored. External providers remain adapters.

**Rationale**: The product needs real metrics without covert tracking or fake values.

**Alternatives considered**:

- Sample analytics: rejected by the owner directive.
- Store raw visitor identifiers indefinitely: rejected for privacy and retention risk.
- Require an external analytics provider: rejected by the optional-dependency rule.

## Decision 12 — Completion evidence

**Decision**: Maintain direct requirement traceability from FR/SC IDs to tasks, tests, runtime probes, and rendered evidence. Final completion is a positive proof audit.

**Rationale**: A broad green suite or absence of placeholder strings cannot prove all designed behavior. The goal explicitly requires requirement-by-requirement evidence.

**Alternatives considered**:

- Use only screenshot comparison: rejected because screenshots cannot prove persistence or security.
- Use only unit tests: rejected because layout, navigation, environment, and end-to-end behavior require live evidence.
