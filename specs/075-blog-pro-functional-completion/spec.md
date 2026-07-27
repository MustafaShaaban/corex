# Spec 075 — Blog Pro Functional Completion

**Status:** draft · **Branch:** `spec/075-blog-pro-functional-completion` · **Follows:** Spec 074 (merged, `d243b7f`)

Blog Pro has a complete, tested back end and a front end that uses almost none of it. This spec closes
that gap. It adds no new service, no new table, and no new REST route — it makes the screen do what the
code behind it already supports.

## 1. Problem

Blog Pro ships as a **read-only reference dashboard**. The services (`BlogAnalyticsService`,
`EditorialWorkflowService`, `CommentModerationService`, `SocialSharingService`,
`AuthorAnalyticsService`), the seven REST routes, and the client reducer all exist and are covered by
tests. The screen renders none of it as something you can act on.

This is precisely what ROADMAP §17's standing rule forbids: *the approved current design is the
functional contract, and a required control may not remain a placeholder.* Evidence lives in
`evidence/before/`.

### D1 — The screen hard-codes one arbitrary post and never says so

`BlogProScreen.php:102`:

```php
$selectedPostId = (int) ($posts[0]['id'] ?? 0);
```

Analytics, editorial state, the moderation queue, and the share controls are **all** computed for
whichever post happens to sort first. Nothing on the screen names that post. The analytics panel is
titled *"First-party reading signals"* over four large numbers, which reads as site-wide truth — it is
one post's numbers. There is no way to choose a different post.

This is the same class of defect Spec 074 existed to remove: a surface stating something more confident
than what it actually knows.

### D2 — The reducer is wired to nothing

`BlogProApp.js:6`:

```js
const [ state ] = useReducer( blogReducer, { ... } );
```

The dispatch is discarded. `blogProState.js` exports `blogReducer` with `transitioned`,
`commentModerated`, `shareRecorded`, and `error` cases, plus `buildTransitionPayload`,
`buildShareClickPayload`, and `blogEndpoint`. **All of it is unreachable from the running app**, and all
of it is covered by `__tests__/blogPro.test.js` — so the suite is green over code no user can run. The
`notice` field the reducer maintains is never rendered, so even the success and failure messages it
builds go nowhere.

### D3 — Seven REST routes have no caller

`BlogProScreen` localizes `restUrl` and `nonce`. The app never uses them; it reads only from the
initial localized blob. So `/blog/analytics`, `/blog/share-controls`, `/blog/share-click`,
`/blog/editorial/{id}/transition`, `/blog/comments`, `/blog/comments/{id}/moderate`, and
`/blog/authors` are reachable by an HTTP client and by nothing in the product. Two of them —
`transition` and `moderate` — are the entire point of an editorial workspace.

### D4 — What is rendered is raw and untranslatable

- `state.editorial.editorial_state` prints a raw slug (`ready_for_review`), never a label.
- `comment.state` likewise.
- Authors and comments are assembled by string concatenation —
  `` `${ author.name } · ${ author.post_count || 0 }` `` — which is sentence assembly outside the i18n
  system and cannot be translated or pluralized.
- Nothing distinguishes the six editorial states from one another beyond that raw slug.

## 2. User stories

**US1 — An editor moves a post through review.** I open Blog Pro, pick the post I am working on, see
that it is *Ready for review*, and move it to *Approved* with a note — without leaving for the post
editor and without guessing which post the screen is describing.

**US2 — A moderator clears the comment queue.** I see the comments waiting on the selected post, read
each one, and approve, unapprove, spam, or trash it from here, with the count updating as I go.

**US3 — An author checks whether anything is being read.** I see views, reads, share clicks, and
average read time for a post I choose, with the engagement rate the reducer already computes, and I can
tell a post nobody has opened from a post with no data yet.

**US4 — Nobody is offered a control they cannot use.** `edit_posts` opens the screen; a contributor who
cannot moderate comments or publish does not see the controls for those, rather than being refused
after clicking (Spec 074, DECISIONS #159).

## 3. Functional requirements

### FR-1 · A named, chosen subject

- The screen presents a **post selector**; every panel states which post it is describing.
- The selection is reflected in the URL (`?post=<id>`) so a view can be linked and reloaded.
- Changing the selection refetches analytics, editorial state, comments, and share controls through the
  existing routes — not by reloading the page.
- With no posts at all, the screen says so once, plainly, and offers the action that fixes it.
- **No panel may present a single post's figures as a site-wide total.** Either the panel names its
  post, or it aggregates honestly.

### FR-2 · A live editorial workflow

- The six states (`draft`, `ready_for_review`, `needs_changes`, `approved`, `scheduled`, `published`)
  render as **translated labels**, never raw slugs.
- Every state **except the one the post is already in** is offered.

  This is what the service actually does, and the spec follows it rather than inventing a workflow.
  `EditorialWorkflowService::transition()` has **no transition graph**: it accepts any of the six states
  from any other and maps it to a native status. Its one genuine constraint is that `scheduled` requires
  a schedule timestamp, or it throws. Building a graph here would be new domain logic, which §10 and the
  plan's *Out of plan* both forbid — so the UI offers what the service accepts, and the schedule field
  becomes required when *Scheduled* is chosen.
- A transition may carry an assignee, a due date, a scheduled date, and a note — the fields
  `buildTransitionPayload` already builds — and is submitted to
  `POST /blog/editorial/{id}/transition`.
- The result updates the panel in place through the reducer's `transitioned` case, and the outcome is
  announced.
- The native WordPress status is shown alongside the CoreX state, since the service maps between them
  and a divergence is worth seeing.

### FR-3 · A working moderation queue

- Each queued comment shows its author, its content, when it arrived, and its state as a translated
  label.
- **Approve / spam / trash** post to `/blog/comments/{id}/moderate` and update in place through the
  reducer's `commentModerated` case. Those three, and no others: `CommentModerationService::queue()`
  returns only comments held for review, so "unapprove" has nothing to act on, and the service's `edit`
  and `reply` actions are excluded by §10. The queue is already bounded at 50 by the service.
- A moderator who lacks `moderate_comments` sees the queue without the controls.
- The empty queue is a distinct, positive state, not an absence.

### FR-4 · Analytics that admit what they do not know

- Views, reads, share clicks, unique visitors, average read seconds, and the engagement rate
  `normalizeAnalytics` already derives — each labelled with the post it belongs to and the period it
  covers.
- **"No data yet" and "zero" are rendered differently.** A post published an hour ago with no reads is
  not the same as a post nobody has ever opened, and the current four-big-numbers panel cannot tell them
  apart.
- Top posts and the chart series that `normalizeAnalytics` already shapes are rendered, or the fields
  are removed. Shaping data nobody displays is the same dead-code problem in a different place.

### FR-5 · Sharing, and the reducer's last dead branch

- Share controls list their targets with translated labels.
- Recording a share click uses `buildShareClickPayload` against `/blog/share-click`, reaching the
  reducer's `shareRecorded` case.
- If a share control cannot be exercised from the admin screen in a way that means anything, **say so
  and remove the control** rather than leaving a button that reports a click nobody made.

### FR-6 · Outcomes are announced

- The `notice` the reducer maintains is rendered, with its tone, and is announced to assistive
  technology.
- Every failure path — a rejected transition, a moderation denial, a network error — produces a notice
  a person can act on, not a silent no-op. The reducer's `error` case gets its first real caller.

## 4. Non-functional requirements

- No new REST route, service, or table. If something appears genuinely missing, record it and stop.
- All styling through `theme.json` / `--corex-admin-*` tokens and logical properties; no hardcoded
  colours or sizes; RTL by construction (constitution Principle VI).
- Every user-facing string translated, with a literal `corex` text domain, translator comments on
  placeholders, and `_n()` for plurals. **No sentence assembly by concatenation** — D4 is a hard fail.
- No unbounded query. The moderation queue and post selector are paginated or bounded.
- The `.scss`/`.css` twins stay byte-identical, and `stylelint` gains no new error.

## 5. Security and permission boundaries

- Every mutation carries the REST nonce the screen already localizes; the routes' existing
  `permission_callback` is authoritative and is not weakened.
- The client hides what the actor may not do; the server still enforces it. The two derive from the same
  capability, per DECISIONS #159.
- `edit_posts` opens the screen. `moderate_comments` gates the moderation controls. Publishing
  transitions are gated on the capability the workflow service already requires.
- No analytics figure that is not already exposed by `BlogAnalyticsService` is introduced, and no
  visitor-identifying value is rendered.

## 6. Accessibility and RTL

- Keyboard-operable throughout; visible focus; the post selector is the approved `CorexSelect`
  (DECISIONS #141).
- Notices announced via a live region.
- State is never carried by colour alone — every editorial and comment state is a word first.
- Verified dark and light, LTR and RTL, desktop and 360 px, at 200 % zoom, with no horizontal overflow.

## 7. Tests

**Jest** — the reducer's four previously unreachable cases driven through the component; post selection
refetching; permission-gated controls hidden; "no data" distinguished from zero; notices rendered and
announced; translated labels for all six editorial states and all comment states.

**Pest (unit)** — any label/permission derivation added on the server.

**Pest (integration)** — transition and moderation round-trips through the real routes, including
denial for an actor without the capability and rejection of an invalid transition.

**Playwright** — select a post and see every panel follow it; move a post through a transition; moderate
a comment; the empty states; RTL and 200 % zoom with no overflow.

Every test must catch a defect this spec is fixing. A test asserting that a service already covered by
its own suite still works is not in scope.

## 8. Definition of Done

1. Every FR implemented and covered by a test that fails without it.
2. Full gate green: PHP lint · Pest unit · integration · Jest · builds · CSS lint · guards · token
   inventory reproduces · Playwright · CodeQL.
3. Guard Gate clean on the diff: `clean-code-guard`, `wp-guard`, `test-guard`, `docs-guard`.
4. Browser acceptance on the real install: dark and light, LTR and RTL, desktop and narrow, keyboard
   only, 200 % zoom — with no horizontal overflow, console error, failed REST request, uncaught
   JavaScript error, or blank React mount.
5. `evidence/before/` and `evidence/after/` captured and referenced from this file.
6. **No dead export remains in `blogProState.js`** — every function it exports has a caller in the
   running app, or it is deleted along with its tests.
7. `PROGRESS.md`, `ROADMAP.md`, `DECISIONS.md`, `CHANGELOG.md`, and the affected docs updated.

## 9. Assumptions

- The existing services are correct. This spec trusts their behaviour and tests only the wiring and the
  presentation on top of them.
- `edit_posts` remains the right gate for opening the screen; it is what the routes already enforce.
- Reading events are already collected by the front end; this spec does not add collection.
- A day is the analytics period granularity, matching what `BlogAnalyticsService::aggregate()` accepts.

## 10. Explicit exclusions

- **No new analytics capability** — no funnels, cohorts, referrers, or retention. Present what is
  collected.
- **No editorial state machine changes** — the six states and their transitions are the service's, not
  this spec's.
- **No comment threading, editing, or replies.** Moderation only.
- **No front-end (visitor-facing) work.** Blog Pro's admin screen is the whole scope.
- **No new managed table or migration.**
- **Notification integration** — an editorial transition producing a notification is a reasonable future
  idea and is *not* in scope; Spec 074 closed the notification work.
- **The `lint:css` / `lint:js` CI decision** — carried in `PROGRESS.md` as an open owner decision, not
  resolved here.
