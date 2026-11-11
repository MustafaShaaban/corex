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
  changed or closed; the conflict message; `FlowService::copyIntoDraft()`; `versionForVisitor()` rules; publishing
  changes on a flow with no recorded publications keeps its old live version (`tests/Unit/Forms/FlowPublishChangesTest.php`).
- [x] T011 Integration test: publish, change, save, publish changes; the page renders the form before and after; a
  submission carrying the old version is accepted and records it; a stale editor is refused
  (`tests/Integration/Forms/LiveFlowsTest.php`).
- [x] T012 `Flow::publish()`, `FlowService::publish()/copyIntoDraft()/liveVersion()/versionForVisitor()`, the copy
  route, the presenter's publications with names.
- [x] T013 The block prints `corex_flow_version`; the visitor service runs the submission against the version it
  names under DECISIONS #470.
- [x] T014 [P] Jest: `publishState()` (publish, publish changes, up to date), the state chip helper, `liveLine()` for a
  published flow only, and the Versions tab (`__tests__/teamsAndVersions.test.js`).
- [x] T015 Editor header (eyebrow, name, chip, live line, Publish changes, Unpublish with confirmation), Versions tab.

## Phase 4: US-FLW-03 Routing assigns a person or team (P1)

- [x] T020 [P] Unit tests: `AssigneeResolver` for each target and each failure; the fallback chain in `RoutingStage`;
  `PageReference` signs and refuses an altered value; `RoutingPreview` by name and address, following the fallback
  chain (`tests/Unit/Forms/AssigneeResolverTest.php`).
- [x] T021 [P] Unit tests: `TeamService` create, rename, members, delete, key collisions, a removed team's key never
  given out again (`tests/Unit/Forms/TeamServiceTest.php`).
- [x] T022 Integration tests: teams REST; a team member without "Manage submissions" sees only assigned flow
  submissions, by user, team and role, never a code form's, and only those flows in the form filter; the upgrade
  reassigns and counts in batches, from an admin request or cron only, done only after the last batch
  (`tests/Integration/Forms/FlowRoutingAssignsTest.php`).
- [x] T023 `Assignee`, `RoutingDirectory`/`WpRoutingDirectory`, `AssigneeResolver`, `PageReference`; `RoutingStage`,
  `InboxStage` and `TimelineStage` record the assignee and the failures.
- [x] T024 Teams: `Team`, `TeamStore`/`WpTeamStore`, `TeamService`, `TeamController`; `AssignedAccess` answers the
  inbox filters; `WpSubmissionAccessPolicy` and `SubmissionsInboxScreen` give the assigned-only inbox.
- [x] T025 `FlowOwnerUpgrade` (batches of 200 from `admin_init` and WP-Cron, under a named lock) and its notice on
  the Forms & Flows screen.
- [x] T026 [P] Jest: target picker values for each type; field value table; `routingOwner()` words; the Teams view
  (`__tests__/teamsAndVersions.test.js`); the preview's name and address (`routedPerson()`).
- [x] T027 Routing tab pickers, Teams view, flow list owner column.

## Phase 5: US-FLW-04 Routed owner (P2)

- [x] T030 [P] Unit tests: `RoutedRecipients` for user, team, role under and over the limit, no address; the resolver's
  `routed_owner` token (`tests/Unit/Forms/AssigneeResolverTest.php`).
- [x] T031 Integration test: captured email to each team member (`tests/Integration/Forms/FlowExtensionHooksTest.php`),
  to the fallback with the note on the delivery and the history, "Send again" to the same routed recipients, and a
  failed email that stops neither the assignment, the routing history nor the actions
  (`tests/Integration/Forms/FlowRoutedEmailTest.php`).
- [x] T032 `RoutedRecipients`, routing metadata, `FlowEmailAddressResolver`, `EmailStage` note, `RoutingPreview` and
  its route, which takes the fallback order from `RoutingChain`, as `RoutingStage` does.
- [x] T033 Emails tab recipient picker, fallback recipient, limit and preview.

## Phase 6: US-FLW-07 Extensions run (P2)

- [x] T040 [P] Unit tests: `FlowExtensionHooks` reads the three filters and `RoutingTargetRegistry`
  (`tests/Unit/Forms/TeamServiceTest.php`); a stage whose failure is recorded, not fatal, and a wrapper around one
  stage (`tests/Unit/Forms/FormSubmissionPipelineTest.php`). `ActionsStage` runs once, records done and failed and
  passes the test flag, proved end to end (`tests/Integration/Forms/FlowExtensionHooksTest.php`,
  `tests/Integration/Forms/FlowRoutedEmailTest.php`).
- [x] T041 Integration test: the README's examples, registered through the hooks, run on a submission
  (`tests/Integration/Forms/FlowExtensionHooksTest.php`).
- [x] T042 `FlowExtensionHooks`, `RoutingTargetRegistry`, `ActionsStage`, `EmailVariableValues`, the corex-email
  schema filter, the catalog.
- [x] T043 Success tab "After submission" actions; README.

## Phase 7: US-VIS-01 and US-VIS-02 Closed and scheduled flows (P1, P2)

- [x] T050 [P] Unit tests: `FlowStatusResolver` (draft, scheduled, open, expired by date and cap, closed);
  `FlowNotice` text and alternative; a cap below 1 refused; a closed flow keeps its closing date
  (`tests/Unit/Forms/FlowAvailabilityTest.php`).
- [x] T051 Integration tests: the block's closed, scheduled, draft (visitor and editor) and missing output; the submit
  route's 409; availability saved without publishing reopens; the lock held for the count and the store only, and a
  503 `flow_busy` after a long wait; the owner's words with and without a translation plugin; the page reference
  only on a singular main query (`tests/Integration/Forms/LiveFlowsTest.php`); the cap race in two PHP processes
  (`tests/Integration/Forms/FlowCapRaceTest.php`).
- [x] T052 `FlowStatusResolver`, `FlowResponseCounter`, `FlowNotice`, `FlowNoticeText`, `FlowClosedException`,
  `SubmissionBusyException`; the renderer; the submit route; the lock around the store only; the availability route;
  the list's status and filter.
- [x] T053 Runtime `flow_closed` branch (Jest in `plugins/corex-core`), the notice styles, the block editor picker.
- [x] T054 Availability tab.

## Phase 8: Release

- [x] T060 Playwright: `flow-live-publish.spec.js`, `flow-closed.spec.js`; run the touched `forms-flow.spec.js`.
- [x] T061 Measure the editor, the Teams view and the visitor notice at 375px, dark and right to left.
- [x] T062 DECISIONS #470 to #479, CHANGELOG under [Unreleased] with Client impact, PROGRESS "In flight", README.
- [x] T063 Guards: clean-code-guard, wp-guard, test-guard, docs-guard.
- [x] T064 Merge origin/main, push, open the pull request, get CI green.
