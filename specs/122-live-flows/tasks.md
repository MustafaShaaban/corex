# Tasks: Flows that stay live and route to people

**Input**: [spec.md](spec.md), [plan.md](plan.md). Tests come first in each story. `[P]` means it touches files no other
open task touches.

## Phase 1: Setup

- [x] T001 Set up a scratch WordPress for this worktree (port 8922, database `corex_w122`) and confirm WordPress loads
  (Environment Gate).

## Phase 2: Foundations shared by every story

- [x] T002 [P] Unit tests: `FlowAvailability` normalizes, validates (order of dates, link, email, cap) and round-trips;
  `FlowPublication` round-trips (`tests/Unit/Forms/FlowAvailabilityTest.php`).
- [x] T003 `FlowPublication`, `FlowAlternative`, `FlowAvailability`; `Flow` gains publications and availability;
  `FlowRepository` reads and writes them; `FlowConfiguration` gains `actions` (checksum only when not empty).

## Phase 3: US-FLW-01 Publish changes without going offline (P1)

- [x] T010 [P] Unit tests: `Flow::publish()` from draft and from published with a newer draft, refused when nothing
  changed or closed; the conflict message; `FlowService::copyIntoDraft()`; `versionForVisitor()` rules
  (`tests/Unit/Forms/FlowPublishChangesTest.php`).
- [x] T011 Integration test: publish, change, save, publish changes; the page renders the form before and after; a
  submission carrying the old version is accepted and records it; a stale editor is refused
  (`tests/Integration/Forms/FlowPublishChangesTest.php`).
- [x] T012 `Flow::publish()`, `FlowService::publish()/copyIntoDraft()/liveVersion()/versionForVisitor()`, the copy
  route, the presenter's publications with names.
- [x] T013 The block prints `corex_flow_version`; the visitor service runs the submission against the version it
  names under DECISIONS #470.
- [x] T014 [P] Jest: `publishState()` (publish, publish changes, up to date) and the state chip helper.
- [x] T015 Editor header (eyebrow, name, chip, live line, Publish changes, Unpublish with confirmation), Versions tab.

## Phase 4: US-FLW-03 Routing assigns a person or team (P1)

- [x] T020 [P] Unit tests: `AssigneeResolver` for each target and each failure; the fallback chain in `RoutingStage`;
  `PageReference` signs and refuses an altered value (`tests/Unit/Forms/AssigneeResolverTest.php`,
  `tests/Unit/Forms/RoutingStageAssigneeTest.php`).
- [x] T021 [P] Unit tests: `TeamService` create, rename, members, delete, key collisions (`tests/Unit/Forms/TeamServiceTest.php`).
- [x] T022 Integration tests: teams REST; a team member without "Manage submissions" sees only assigned submissions;
  the upgrade reassigns and counts (`tests/Integration/Forms/FlowRoutingAssignsTest.php`).
- [x] T023 `Assignee`, `RoutingDirectory`/`WpRoutingDirectory`, `AssigneeResolver`, `PageReference`; `RoutingStage`,
  `InboxStage` and `TimelineStage` record the assignee and the failures.
- [x] T024 Teams: `Team`, `TeamStore`/`WpTeamStore`, `TeamService`, `TeamController`; `AssignedAccess` answers the
  inbox filters; `WpSubmissionAccessPolicy` and `SubmissionsInboxScreen` give the assigned-only inbox.
- [x] T025 `FlowOwnerUpgrade` and its notice on the Forms & Flows screen.
- [x] T026 [P] Jest: target picker values for each type; field value table; `routingOwner()` words.
- [x] T027 Routing tab pickers, Teams view, flow list owner column.

## Phase 5: US-FLW-04 Routed owner (P2)

- [x] T030 [P] Unit tests: `RoutedRecipients` for user, team, role under and over the limit, no address; the resolver's
  `routed_owner` token (`tests/Unit/Forms/RoutedRecipientsTest.php`).
- [x] T031 Integration test: captured email to the routed user, to each team member, to the fallback with the note
  (`tests/Integration/Forms/FlowRoutedOwnerEmailTest.php`).
- [x] T032 `RoutedRecipients`, routing metadata, `FlowEmailAddressResolver`, `EmailStage` note, `RoutingPreview` and
  its route.
- [x] T033 Emails tab recipient picker, fallback recipient, limit and preview.

## Phase 6: US-FLW-07 Extensions run (P2)

- [x] T040 [P] Unit tests: `FlowExtensionHooks` reads the three filters; `ActionsStage` runs once, records done and
  failed, passes the test flag; `RoutingTargetRegistry` (`tests/Unit/Forms/FlowExtensionsRunTest.php`).
- [x] T041 Integration test: the README's examples, registered through the hooks, run on a submission
  (`tests/Integration/Forms/FlowExtensionHooksTest.php`).
- [x] T042 `FlowExtensionHooks`, `RoutingTargetRegistry`, `ActionsStage`, `EmailVariableValues`, the corex-email
  schema filter, the catalog.
- [x] T043 Success tab "After submission" actions; README.

## Phase 7: US-VIS-01 and US-VIS-02 Closed and scheduled flows (P1, P2)

- [x] T050 [P] Unit tests: `FlowStatusResolver` (draft, scheduled, open, expired by date and cap, closed);
  `FlowNotice` text and alternative (`tests/Unit/Forms/FlowStatusResolverTest.php`).
- [x] T051 Integration tests: the block's closed, scheduled, draft (visitor and editor) and missing output; the submit
  route's 409; availability saved without publishing reopens; the cap race in two PHP processes
  (`tests/Integration/Forms/FlowAvailabilityTest.php`).
- [x] T052 `FlowStatusResolver`, `FlowResponseCounter`, `FlowNotice`, `FlowNoticeText`, `FlowClosedException`; the
  renderer; the submit route; the lock; the availability route; the list's status and filter.
- [x] T053 Runtime `flow_closed` branch (Jest in `plugins/corex-core`), the notice styles, the block editor picker.
- [x] T054 Availability tab.

## Phase 8: Release

- [x] T060 Playwright: `flow-live-publish.spec.js`, `flow-closed.spec.js`; run the touched `forms-flow.spec.js`.
- [x] T061 Measure the editor, the Teams view and the visitor notice at 375px, dark and right to left.
- [x] T062 DECISIONS #470 to #479, CHANGELOG under [Unreleased] with Client impact, PROGRESS "In flight", README.
- [x] T063 Guards: clean-code-guard, wp-guard, test-guard, docs-guard.
- [x] T064 Merge origin/main, push, open the pull request, get CI green.
