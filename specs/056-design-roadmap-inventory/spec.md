# Feature Specification: Design Roadmap and Inventory Integration

**Feature Branch**: `docs/056-roadmap-refresh`

**Created**: 2026-06-19

**Status**: Draft

**Input**: User description: "Separate external Claude Design exploration from the Corex engineering roadmap, maintain a design inventory and focused handoffs, and convert approved design areas into engineering specs one at a time."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Understand product direction at a glance (Priority: P1)

As the Corex owner, I need one milestone-based product and engineering roadmap that distinguishes completed foundations, active work, blockers, design dependencies, Free/Core scope, and Pro/future scope, so I can choose the next investment without reconstructing project history from old specs.

**Why this priority**: The roadmap is the decision surface for all later work. If it is ambiguous or stale, design and engineering will advance in the wrong order.

**Independent Test**: A reviewer unfamiliar with recent chat history can read the roadmap and correctly identify what exists, what is active, what is blocked, what depends on design, what is required before real company websites, and what is deferred.

**Acceptance Scenarios**:

1. **Given** the current Corex repository, **When** the owner reads the roadmap, **Then** every planned area appears in a named milestone with status, priority, dependencies, and bounded scope.
2. **Given** an item that is already represented by a repository foundation, **When** it appears in current status, **Then** it is described without claiming that incomplete page, state, or release coverage is finished.
3. **Given** an advanced commercial idea, **When** it appears in the roadmap, **Then** it is classified as Pro/future or deferred and does not become immediate Core scope.

---

### User Story 2 - Track design independently from implementation (Priority: P1)

As the design owner, I need a separate design roadmap and inventory, so external Claude Design exploration can be reviewed and organized without being mistaken for approved engineering work.

**Why this priority**: Design is occurring outside the repository and can produce more concepts than engineering should implement. A visible approval boundary prevents scope leakage.

**Independent Test**: A reviewer can record a design area as approved, needs revision, missing, or future, with its priority and notes, without changing an engineering spec or product code.

**Acceptance Scenarios**:

1. **Given** a new external design concept, **When** it is added to the inventory, **Then** it receives an allowed status and priority without being treated as implementation authorization.
2. **Given** a design that still lacks responsive, RTL, accessibility, state, or performance coverage, **When** it is reviewed, **Then** it remains needs revision or missing.
3. **Given** an explicitly future commercial design, **When** it is inventoried, **Then** it remains future and does not enter the immediate engineering queue.

---

### User Story 3 - Hand approved design to engineering safely (Priority: P2)

As an engineer, I need a focused handoff for each approved design area before an engineering spec is created, so scope, variants, behavior, responsive rules, RTL, accessibility, performance, and open questions are explicit.

**Why this priority**: A handoff is the translation boundary between visual intent and testable engineering requirements. It reduces ambiguity without bypassing Spec Kit.

**Independent Test**: An approved design area can be represented by one handoff whose required sections are complete, and a reviewer can determine whether it is ready to become an engineering spec.

**Acceptance Scenarios**:

1. **Given** an approved design area, **When** a handoff is prepared, **Then** it defines scope, exclusions, components, states, responsive behavior, RTL, accessibility, performance, tokens, and unresolved questions.
2. **Given** a handoff with unresolved scope or missing critical behavior, **When** engineering reviews it, **Then** no implementation spec is created until the gap is resolved or explicitly bounded.
3. **Given** a complete approved handoff, **When** the next engineering spec is created, **Then** that spec remains focused on one implementation area rather than the entire design inventory.

---

### User Story 4 - Preserve durable planning boundaries (Priority: P3)

As a maintainer, I need roadmap, progress, changelog, decisions, design, and specs to retain distinct responsibilities, so future agents can resume from repository evidence without duplicating or contradicting status.

**Why this priority**: Durable planning depends on each document answering one class of question. Mixing release history, session status, architecture rationale, and future scope creates drift.

**Independent Test**: A reviewer can classify a proposed update and identify exactly one primary planning surface for it, with cross-updates only when the documented contract requires them.

**Acceptance Scenarios**:

1. **Given** a session resume update, **When** it is recorded, **Then** it belongs in the progress file rather than the roadmap.
2. **Given** an actual released or unreleased product change, **When** it is recorded, **Then** it belongs in the changelog rather than the design inventory.
3. **Given** a non-trivial architectural or product decision, **When** it is recorded, **Then** its rationale belongs in the decisions log rather than milestone status.

### Edge Cases

- A design is visually approved but lacks RTL, accessibility, responsive, or interaction-state coverage.
- One design concept affects multiple milestones and could create overlapping handoffs.
- The inventory and engineering roadmap show different status for the same area.
- A repository foundation exists, but its completeness cannot be verified from current evidence.
- A future Pro concept depends on a Free/Core accessibility or security capability.
- An urgent client request arrives before the corresponding design handoff is approved.
- External design work changes after an engineering spec has already been approved.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The roadmap MUST state the separate purposes of the roadmap, progress file, changelog, decisions log, implementation specs, and design planning area.
- **FR-002**: The roadmap MUST provide a high-level foundation status that distinguishes existing foundations from verified completion and marks uncertain claims for verification.
- **FR-003**: The roadmap MUST organize future direction into milestones M0 through M11 with status, priority, dependencies, scope, and explicit deferrals.
- **FR-004**: The roadmap MUST identify stabilization and a clean post-readiness release as required before real company website work.
- **FR-005**: The roadmap MUST identify design-dependent milestones and MUST NOT treat external design exploration as implementation authorization.
- **FR-006**: The roadmap MUST preserve adoption, accessibility, internationalization, RTL, and security basics in Free/Core.
- **FR-007**: The roadmap MUST keep advanced vertical workflows, commercial entitlement, white-labeling, portals, and multi-company operations in Pro/future scope until Core/Core kits are stable.
- **FR-008**: The design roadmap MUST describe the sequence from exploration to inventory, approval, handoff, engineering spec, and implementation.
- **FR-009**: The design inventory MUST record area, screen or component, status, priority, handoff reference, and notes.
- **FR-010**: Design inventory status MUST be limited to `approved`, `needs revision`, `missing`, and `future`.
- **FR-011**: Design inventory priority MUST be limited to `high`, `medium`, and `low`.
- **FR-012**: A design handoff MUST cover approved scope, explicit exclusions, screens/components/variants, content constraints, interactions, states, responsive behavior, RTL and mixed-script behavior, accessibility, performance, tokens, open questions, and approval evidence.
- **FR-013**: A design handoff MUST be treated as input to an engineering spec, not as permission to change product code directly.
- **FR-014**: Detailed engineering specs MUST be created only for the next two or three implementation items and MUST be implemented one at a time.
- **FR-015**: The immediate recommended spec sequence MUST list only Spec 056, Spec 057, and Spec 058 until roadmap status is intentionally updated.
- **FR-016**: This feature MUST NOT change product code, runtime behavior, release metadata, architecture decisions, or unapproved design tokens.
- **FR-017**: Planning changes MUST preserve existing released history and completed implementation specs as historical evidence rather than rewriting them to match future intent.
- **FR-018**: If an external design changes after an engineering spec is approved, the handoff and spec MUST be reviewed and updated before implementation continues.

### Key Entities

- **Engineering Milestone**: A durable product or engineering outcome with status, priority, dependencies, bounded scope, and deferrals.
- **Design Inventory Item**: A design area or component with review status, priority, handoff reference, and notes about missing coverage.
- **Design Handoff**: An approved, focused translation of design intent into behavior and quality constraints suitable for specification.
- **Engineering Spec Candidate**: The next bounded implementation area selected from an approved handoff and roadmap dependency order.
- **Planning Surface**: A durable document with one responsibility: roadmap, progress, changelog, decisions, design planning, or implementation specification.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A new maintainer can identify completed foundations, active work, blockers, design dependencies, first-company-site requirements, Free/Core scope, Pro/future scope, and deferred scope from the roadmap with no chat context.
- **SC-002**: All 12 milestones from M0 through M11 have an explicit status, priority, dependency or sequencing statement, and bounded outcome.
- **SC-003**: Every design inventory row uses one of four allowed statuses and one of three allowed priorities.
- **SC-004**: 100% of approved design areas selected for engineering have a focused handoff before a detailed engineering spec is created.
- **SC-005**: No product-code file, release-history entry, or architecture-decision entry changes as part of this planning-only feature unless a separately reviewed requirement explicitly authorizes it.
- **SC-006**: The next-spec list contains exactly three items—Specs 056, 057, and 058—and none is represented as implemented merely because it is listed.
- **SC-007**: A reviewer can assign each new roadmap, session, release, decision, design, or implementation update to the correct primary planning surface on the first attempt.

## Assumptions

- Claude Design remains external to the repository and supplies design exploration artifacts for human review.
- Existing specs and package structure are evidence of foundations, but release completeness requires current verification.
- The root roadmap remains the single engineering roadmap; the design roadmap is subordinate for design sequencing only.
- The first real consumers are company websites, so stabilization, brand foundations, navigation, the company kit, and selected component gaps lead the implementation sequence.
- Design inventory rows may begin as missing or needs revision; placeholders do not imply approval.
- No product implementation is needed to complete this feature.

## Scope Boundaries

### In scope

- Root roadmap information architecture and milestone content.
- Lightweight design roadmap, inventory, and handoff guidance.
- Immediate progress/resume update for the planning change.
- Validation of planning responsibilities, status vocabulary, scope boundaries, and next-spec sequence.

### Out of scope

- Brand tokens, logos, typography, or visual assets.
- Headers, navigation, mega menus, footers, blocks, templates, kits, admin UI, forms, email templates, docs UI, or marketing assets.
- Product code, runtime behavior, migrations, package metadata, release publishing, or dependency remediation.
- Detailed specifications for Specs 057 or 058.

## Likely Affected Planning Surfaces

- The durable product and engineering roadmap.
- The immediate progress/resume file.
- The design roadmap and inventory.
- Design handoff guidance.
- Spec Kit's active-feature pointer.

## Validation and Rollback

- Validate that every required milestone, planning responsibility, inventory field, status value, and next-spec item is present.
- Validate that changed files are limited to planning surfaces authorized by this feature.
- Validate documentation formatting and internal path references.
- Roll back by reverting this feature's planning-document changes as one unit; no runtime or data rollback is required.
