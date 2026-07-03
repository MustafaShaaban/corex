# Evidence Ledger: CoreX Product Functional Completion

This ledger maps direct completion evidence to Spec 068. An empty row is incomplete. Indirect evidence, intent, or a green unrelated suite does not count.

## Planning and Governance

| Task | Evidence | Result |
|---|---|---|
| T001 | `spec.md`: 167 unique FR IDs and 20 unique SC IDs | Pass |
| T002 | `plan.md`, `research.md`, `data-model.md`, `contracts/`, `quickstart.md` | Pass |
| T003 | `CLAUDE.md` managed block points to Spec 068 plan | Pass (manual fallback after extension script Python/YAML failure) |
| T004 | `DECISIONS.md` #115 | Pass |
| T005 | Top `PROGRESS.md` resume block | Pass |
| T006 | `ROADMAP.md` §17 and `design/INVENTORY.md` | Pass |
| T007 | `scripts/audit-product-completion.mjs` and pure `scripts/product-completion-audit.mjs` | Pass |
| T008 | `tests/product-completion-audit.test.js`: 9/9 Jest tests | Pass |
| T009 | This ledger | Pass |
| T010 | Clean-code, test, and docs guard reviews; focused JS lint; `git diff --check`; baseline audit | Pass (audit intentionally exits 1 with the 31 known completion findings below) |

## Baseline Audit

- Repository root: `C:\wamp64\www\corex`
- Active branch: `fix/067-admin-shell-and-completion`
- Baseline commit: `3ce717b`
- Remote parity at audit: `HEAD...origin/fix/067-admin-shell-and-completion = 0/0`
- Worktrees: one, normal root
- Adopted prior work: `plugins/corex-config/src/Insights/InsightWidgets.php`
- Design authority: `F:\Work\CoreX.zip`
- Primary `.dc.html` files read/inventoried: 44
- WordPress: 7.0
- Theme: `corex` active, version 0.33.0
- Required plugins: `corex-core`, `corex-blocks`, `corex-config`, `corex-forms` active
- Boot probe: `BOOT_OK`
- `wp corex doctor`: pass with one non-blocking neutral-brand recommendation

### Baseline contradictions to completion

- Forms & Flows explicitly describes code-first, planned, read-only behavior.
- Access & Abilities explicitly disables the request workflow and role editing.
- Blog Pro identifies itself as a future/reference surface and renders sample analytics.
- Email Studio disables editing/test/routing/partials and describes planned capabilities.
- Data Models stops at read-only/validation preview without write commit.
- Operations & Security defers login protection.
- Overview includes planned/read-only summaries and no unified event store.
- Setup remains a three-step chooser rather than the approved nine-step launch workflow.
- Adopted Insights work contains a `Planned` state.
- Roadmap, progress, decisions, and design inventory previously authorized conflicting deferrals.

Executable baseline scan: 31 findings in 12 files. Rule totals: code-defined-editor 1; disabled-required-action 1;
future-add-on 6; planned-capability 6; planned-state 5; read-only-surface 7; reference-layout 2; sample-data 3.
This is expected failure evidence and remains a completion blocker until T223 reaches zero.

## Spec Kit Quality

- Specification checklist: all items passing
- Clarification questions: zero; owner requirements and standing recommended-choice instruction resolved critical scope decisions
- Tasks: 235, unique IDs 235, format failures 0
- Story tasks: US1 12; US2 28; US3 20; US4 21; US5 23; US6 17; US7 22; US8 22; US9 15; US10 14
- Requirement ranges: FR-001–FR-167 and SC-001–SC-020 mapped in `tasks.md`
- Constitution issues: none found
- Consistency remediation applied: Profile add-on scaffolding, Woo email layout, missing-package guidance, named settings domains, and concrete performance contract

## Phase Evidence

| Phase | Focused tests | Runtime/E2E | Visual matrix | Guards | Docs | Status |
|---|---|---|---|---|---|---|
| 1 | 9/9 Jest tests pass; focused JS lint passes | N/A | N/A | Clean-code, test, and docs guards pass | Complete | Complete |
| 2 | Pending | Pending | N/A | Pending | Pending | Pending |
| 3 | Pending | Pending | Pending | Pending | Pending | Pending |
| 4 | Pending | Pending | Pending | Pending | Pending | Pending |
| 5 | Pending | Pending | Pending | Pending | Pending | Pending |
| 6 | Pending | Pending | Pending | Pending | Pending | Pending |
| 7 | Pending | Pending | Pending | Pending | Pending | Pending |
| 8 | Pending | Pending | Pending | Pending | Pending | Pending |
| 9 | Pending | Pending | Pending | Pending | Pending | Pending |
| 10 | Pending | Pending | Pending | Pending | Pending | Pending |
| 11 | Pending | Pending | Pending | Pending | Pending | Pending |
| 12 | Pending | Pending | Pending | Pending | Pending | Pending |

## Requirement Evidence

Every item remains incomplete until populated with direct source, test, runtime, and rendered evidence as applicable.

| Requirement range | Task range | Direct evidence | Status |
|---|---|---|---|
| FR-001–FR-011 | T007–T041, T222–T235 | Pending | Incomplete |
| FR-012–FR-019 | T173–T184 | Pending | Incomplete |
| FR-020–FR-026 | T176–T184 | Pending | Incomplete |
| FR-027–FR-045 | T064–T091 | Pending | Incomplete |
| FR-046–FR-058 | T092–T111 | Pending | Incomplete |
| FR-059–FR-070 | T112–T132 | Pending | Incomplete |
| FR-071–FR-083 | T133–T155 | Pending | Incomplete |
| FR-084–FR-095 | T020–T027, T143–T155 | Pending | Incomplete |
| FR-096–FR-110 | T156–T172 | Pending | Incomplete |
| FR-111–FR-125 | T037–T039, T042–T063 | Pending | Incomplete |
| FR-126–FR-133 | T185–T191 | Pending | Incomplete |
| FR-134–FR-143 | T192–T196 | Pending | Incomplete |
| FR-144–FR-153 | T197–T206 | Pending | Incomplete |
| FR-154–FR-167 | T207–T221 | Pending | Incomplete |
| SC-001–SC-020 | T222–T235 and all checkpoints | Pending | Incomplete |

## Final Verification

Pending. Do not use this section to claim completion until every command, guard, screen/state matrix, and requirement row has direct current evidence.
