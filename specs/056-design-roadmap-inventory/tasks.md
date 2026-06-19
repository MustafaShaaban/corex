# Tasks: Design Roadmap and Inventory Integration

**Input**: Design documents from `specs/056-design-roadmap-inventory/`

**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`, `contracts/design-handoff-contract.md`, `quickstart.md`

**Tests**: This is a documentation-only feature. Validation tasks check requirements, vocabulary, paths, changed-file scope, formatting, and the documentation guard; no product test suite is required.

## Phase 1: Setup

**Purpose**: Establish the active planning feature and protect the documentation-only scope.

- [X] T001 Confirm branch, clean starting ownership, and active feature pointer in `.specify/feature.json`
- [X] T002 Review constitution, current roadmap/progress/changelog/decisions, recent specs, and repository package structure against `specs/056-design-roadmap-inventory/spec.md`

---

## Phase 2: Foundational planning contracts

**Purpose**: Define shared planning roles and design-to-engineering boundaries before story-specific documents are finalized.

**Critical**: Complete before all user-story phases.

- [X] T003 Define planning-surface responsibilities and milestone semantics in `specs/056-design-roadmap-inventory/data-model.md`
- [X] T004 Define approval, readiness, and change-control requirements in `specs/056-design-roadmap-inventory/contracts/design-handoff-contract.md`
- [X] T005 Define repeatable end-to-end validation in `specs/056-design-roadmap-inventory/quickstart.md`

**Checkpoint**: Planning entities, handoff contract, and validation procedure are explicit.

---

## Phase 3: User Story 1 - Understand product direction at a glance (Priority: P1)

**Goal**: Give the owner one milestone-based product/engineering roadmap that answers current state, next work, blockers, design dependencies, company-site prerequisites, Free/Core, Pro/future, and deferrals.

**Independent Test**: A new reviewer can answer every roadmap question in US1 using `ROADMAP.md` alone and can verify all 12 milestones are bounded and sequenced.

- [X] T006 [US1] Replace spec-history organization with roadmap-purpose and at-a-glance milestone sections in `ROADMAP.md`
- [X] T007 [US1] Add evidence-calibrated current foundation status in `ROADMAP.md`
- [X] T008 [US1] Add complete milestone scope for M0 through M11 in `ROADMAP.md`
- [X] T009 [US1] Add Free/Core, Pro/future, deferred scope, and spec-creation policy in `ROADMAP.md`
- [X] T010 [US1] Limit the immediate detailed-spec queue to Specs 056, 057, and 058 in `ROADMAP.md`

**Checkpoint**: US1 is independently readable and testable from the root roadmap.

---

## Phase 4: User Story 2 - Track design independently from implementation (Priority: P1)

**Goal**: Provide a separate design roadmap and controlled inventory without implying implementation authorization.

**Independent Test**: A design area can be recorded with an allowed status/priority and reviewed without changing product code or an engineering spec.

- [X] T011 [P] [US2] Create the external-design-to-engineering sequence in `design/ROADMAP.md`
- [X] T012 [P] [US2] Create the initial area inventory with controlled status and priority vocabulary in `design/INVENTORY.md`
- [X] T013 [US2] Verify every inventory row uses an allowed status and priority in `design/INVENTORY.md`

**Checkpoint**: Design exploration has a distinct planning surface and no row claims implementation.

---

## Phase 5: User Story 3 - Hand approved design to engineering safely (Priority: P2)

**Goal**: Establish the focused handoff gate before any approved external design becomes an engineering spec.

**Independent Test**: A reviewer can use the handoff guidance and contract to determine whether one approved area is ready for specification.

- [X] T014 [US3] Create lightweight handoff authoring guidance in `design/handoffs/README.md`
- [X] T015 [US3] Cross-check handoff guidance against all required contract sections in `specs/056-design-roadmap-inventory/contracts/design-handoff-contract.md`
- [X] T016 [US3] Verify the roadmap and design guidance both prohibit direct design-to-code implementation in `ROADMAP.md` and `design/ROADMAP.md`

**Checkpoint**: The approval-to-handoff-to-spec gate is complete and consistent.

---

## Phase 6: User Story 4 - Preserve durable planning boundaries (Priority: P3)

**Goal**: Keep each continuity document responsible for one class of information and leave release/decision history untouched.

**Independent Test**: A reviewer can classify roadmap, session, release, decision, design, and implementation updates to the correct primary surface.

- [X] T017 [US4] Add a short resume entry with the exact next action in `PROGRESS.md`
- [X] T018 [US4] Update the managed Spec Kit plan reference in `CLAUDE.md`
- [X] T019 [US4] Confirm `CHANGELOG.md` and `DECISIONS.md` have no feature diff and document the scope check in `specs/056-design-roadmap-inventory/quickstart.md`

**Checkpoint**: Continuity roles remain distinct and the next session can resume from Spec 056 evidence.

---

## Phase 7: Polish and cross-cutting validation

**Purpose**: Prove the documentation feature satisfies its complete contract without overclaiming runtime verification.

- [X] T020 Validate all FR/SC coverage and checklist state in `specs/056-design-roadmap-inventory/checklists/requirements.md`
- [X] T021 Run placeholder, terminology, internal-path, and milestone-count checks across `ROADMAP.md`, `design/`, and `specs/056-design-roadmap-inventory/`
- [X] T022 Run `git diff --check` and confirm the changed-file set is limited to authorized planning/specification surfaces
- [X] T023 Run `docs-guard` on the complete documentation diff and resolve all blocking findings
- [X] T024 Update `PROGRESS.md` with final Spec 056 verification evidence and the next recommended action

---

## Dependencies and execution order

### Phase dependencies

- Phase 1 has no dependencies.
- Phase 2 depends on Phase 1 and blocks all user stories.
- US1 and US2 can proceed after Phase 2; they edit separate primary surfaces.
- US3 depends on the US2 design structure and the Phase 2 handoff contract.
- US4 depends on the completed roadmap/design boundaries so its resume state is accurate.
- Phase 7 depends on all user stories.

### User-story dependency graph

```text
Setup -> Foundation -> US1
                    -> US2 -> US3
US1 + US2 + US3 -> US4 -> Validation
```

### Parallel opportunities

- T011 and T012 can run in parallel because they create separate design files.
- After the foundational contracts, US1 roadmap drafting and US2 design-structure drafting can proceed independently.
- Requirement-presence and inventory-vocabulary checks may be prepared independently before the final scope/guard pass.

### Parallel example: User Story 2

```text
Task A: Create design workflow in design/ROADMAP.md
Task B: Create controlled inventory in design/INVENTORY.md
Then: Validate vocabulary and cross-document consistency
```

## Implementation strategy

### MVP first

1. Complete Setup and Foundational contracts.
2. Complete US1 to establish the owner-facing roadmap.
3. Validate that the roadmap independently answers the seven required owner questions.

### Incremental delivery

1. Add US2 design tracking without changing implementation scope.
2. Add US3 handoff readiness contract.
3. Add US4 continuity updates.
4. Run the complete documentation and scope validation gate.

## Task format validation

All 24 tasks use the required checkbox, sequential task ID, optional parallel marker, user-story label where applicable, concrete action, and explicit file path.
