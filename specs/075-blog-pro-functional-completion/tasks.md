# Spec 075 — Tasks

> Tick a box only against a verified artifact. Spec 074's `tasks.md` sat stale through four commits of
> finished work and let a browser spec go on asserting an IA that had been removed; that cost real time.

## Phase 0 — Open

- [x] T001 Capture `evidence/before/` on the real install (`blog-pro-{dark,light}.png`).
- [x] T002 Write `spec.md` + `checklists/requirements.md`; point `.specify/feature.json` at 075.
- [x] T003 Write `plan.md`.
- [x] T004 `ROADMAP.md` §17: 074 merged, 075 active.

## Phase A — Server payload (FR-1, FR-2, FR-3, FR-4)

- [x] T010 [RED] `tests/Unit/Blog/BlogProLabelsTest.php`: every editorial state and comment state maps
      to a translated label; an unknown key falls back to the raw key rather than an empty string.
- [x] T011 `Corex\Config\Blog\BlogProLabels` — pure map, no WordPress beyond `__()`.
- [x] T012 [RED] `tests/Unit/Blog/EditorialTransitionsTest.php`: every state except the current one is
      offered; `scheduled` is flagged as requiring a timestamp; an unknown current state still offers
      all six rather than nothing.
- [x] T013 `Corex\Config\Blog\EditorialTransitions` — a pure derivation of the above.
      **`EditorialWorkflowService` is not changed:** it has no transition graph, and adding one would be
      new domain logic this spec forbids itself.
- [x] T014 [RED] `tests/Unit/Blog/BlogAnalyticsHasDataTest.php`: a post with no reading events reports
      `has_data: false`; a post with events summing to zero reports `has_data: true`.
- [x] T015 `BlogAnalyticsAggregate`/`BlogAnalyticsService` carry `has_data`, derived from the aggregate
      already computed — no extra query.
- [x] T016 Per-item permission flags (`can_moderate`, `can_transition`) on the comment and editorial
      payloads, derived from the same capability the route enforces (DECISIONS #159).
- [x] T017 `BlogProScreen::clientConfig()` stops hard-coding `$posts[0]`: it honours `?post=<id>`,
      validates it, falls back to the newest post, and states which post it selected.
- [x] T018 `BlogProController` returns labels and flags on every route the screen consumes.

## Phase B — Client foundation (FR-1, FR-6)

- [ ] T020 [RED] Jest: `BlogProApp` dispatches — the reducer's `error` case renders a notice.
- [ ] T021 `blogProClient.js` — one fetch helper over `blogEndpoint`, carrying the localized nonce,
      mapping a failure to the reducer's `error` action. No second state layer.
- [ ] T022 `BlogProApp` takes `dispatch`, renders `state.notice` in a live region, and shows loading and
      failure states honestly.
- [ ] T023 Post selector (`CorexSelect`, DECISIONS #141) with `?post=<id>` URL sync; changing it
      refetches through the routes rather than reloading.
- [ ] T024 Every panel names the post it describes. No panel presents one post's figures as site-wide.
- [ ] T025 `blog-pro.scss` + byte-identical `.css` twin; tokens and logical properties only.
- [ ] T026 Jest: selection drives refetch; the no-posts empty state; notices announced.

## Phase C — Editorial workflow (FR-2)

- [ ] T030 [RED] Jest: the six states render translated labels, never slugs; only permitted transitions
      are offered; a denied transition surfaces the server's message.
- [ ] T031 `EditorialPanel.js` — current state, native status alongside it, and the transition form
      (assignee, due date, scheduled date, note) built with `buildTransitionPayload`.
- [ ] T032 Submit to `POST /blog/editorial/{id}/transition`; update in place via `transitioned`.
- [ ] T033 [RED→GREEN] `tests/Integration/Blog/EditorialTransitionTest.php`: round-trip, denial for an
      actor without the capability, rejection of an invalid transition.

## Phase D — Moderation queue (FR-3)

- [ ] T040 [RED] Jest: a queued comment shows author, content, arrival time, and a translated state;
      the three actions are hidden from an actor without `moderate_comments`.
- [ ] T041 `ModerationPanel.js` — approve / spam / trash to `POST /blog/comments/{id}/moderate`,
      updating via `commentModerated`. Three actions, not four: the queue holds only comments awaiting
      review, so "unapprove" has nothing to act on.
- [ ] T042 A distinct, positive empty state. The queue is already bounded at 50 by
      `CommentModerationService::queue()`; confirm, do not re-bound.
- [ ] T043 [RED→GREEN] `tests/Integration/Blog/CommentModerationTest.php`: round-trip and denial.

## Phase E — Analytics and sharing (FR-4, FR-5)

- [ ] T050 [RED] Jest: "no data yet" and zero render differently; the engagement rate
      `normalizeAnalytics` derives is displayed; the period and the post are named.
- [ ] T051 `AnalyticsPanel.js` — the six metrics, honest about absence.
- [ ] T052 Top posts and the chart series `normalizeAnalytics` already shapes are rendered **or** the
      fields are deleted from the normalizer and its tests. Shaping data nobody displays is the same
      dead-code defect in a different place.
- [ ] T053 `SharingPanel.js` — translated target labels; `buildShareClickPayload` →
      `POST /blog/share-click` → `shareRecorded`. If recording a click from the admin screen means
      nothing, remove the control and say so in DECISIONS instead of shipping a button that lies.

## Phase F — Dead-export sweep (DoD item 6)

- [ ] T060 Grep every `blogProState.js` export for a caller in the running app. Delete what has none,
      with its tests. Record the deletions.

## Phase G — Close

- [ ] T070 Full gate: PHP lint, Pest unit, integration, Jest, builds, CSS lint, guards, token
      inventory, Playwright, CodeQL.
- [ ] T071 Playwright: select a post and watch every panel follow it; a transition; a moderation; the
      empty states; RTL and 200% zoom with no overflow.
- [ ] T072 Browser acceptance: dark/light, LTR/RTL, desktop/narrow, keyboard, 200% zoom; no console
      error, failed REST call, uncaught JS error, or blank mount.
- [ ] T073 `evidence/after/` captured and referenced from `spec.md`.
- [ ] T074 Guard Gate on the diff (`clean-code-guard`, `wp-guard`, `test-guard`, `docs-guard`).
- [ ] T075 Docs, `DECISIONS.md`, `CHANGELOG.md`, `PROGRESS.md`, `ROADMAP.md`.
- [ ] T076 PR, CI green, merge, delete branch.
