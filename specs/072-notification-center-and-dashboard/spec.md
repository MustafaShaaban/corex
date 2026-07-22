# Feature Specification: CoreX Notification Center & Dashboard Command Center

**Feature Branch**: `spec/072-notification-center-and-dashboard`
**Created**: 2026-07-21
**Status**: Draft
**Input**: Owner request — "Create a shared CoreX administrative Notification Center that surfaces meaningful, actionable, recipient-aware events across the framework… and add a CoreX presence to the default WordPress Dashboard."

## Context

CoreX already records **what happened** — an append-only Activity stream audits mode changes, role
grants, email attempts, exports, and more. What it lacks is a way to tell an administrator **what
needs their attention**. Today a failed email, a submission assigned to you, a background job that
died, or a readiness blocker leaves no signal you can act on: you find out by stumbling onto the
owning screen. There is no unread count, no bell, no per-person inbox of things to do.

This feature adds that layer — a **Notification Center**: recipient-aware items, each with a state
(unread, read, snoozed, dismissed, resolved) and, where useful, a direct action. It is deliberately
**not** a second Activity log. Activity is the durable history of everything; a notification is a
targeted, resolvable nudge for one or more specific people.

Two supporting surfaces make notifications reach administrators where they already are: a **bell** in
the CoreX admin shell (and a matching entry in the WordPress toolbar for non-CoreX screens), and a
**Command Center widget** on the default WordPress Dashboard that greets an administrator with site
state, what needs attention, and safe shortcuts into CoreX.

This is Phase B (Notification Center) + Phase C (Dashboard) of the form-delivery goal, sequenced
after Phase A (spec 071), whose truthful submission-delivery outcomes are one of the first things the
Notification Center surfaces.

**Explicitly excluded:** MFA, 2FA, TOTP, authenticator apps, passkeys, WebAuthn, security keys,
trusted-device authentication, and MFA recovery codes — from this and every phase of this work.

## Distinct concepts (must not be conflated)

- **Activity** — an immutable record of *what happened*. The durable audit history. Unchanged here.
- **Notification** — a recipient-aware item that *something needs attention*, with per-user state.
- **Toast** — transient feedback for an action *the current user just performed*. Not stored as a
  notification.
- **WordPress admin notice** — reserved for exceptional system-level warnings (a failed migration),
  not for routine notifications.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — An administrator sees what needs their attention (Priority: P1) 🎯 MVP

**Why this priority**: The whole feature exists to answer "what needs me?". Without a recipient-aware
store and a way to read it, nothing else matters.

**Independent Test**: Cause an event targeted at a specific administrator (e.g. a submission assigned
to them, or a failed email they can manage), then confirm that administrator — and only permitted
administrators — sees it, with an unread count, and can open it.

**Acceptance Scenarios**:

1. **Given** an event relevant to a specific administrator, **When** it occurs, **Then** that
   administrator sees a notification for it with an unread indicator.
2. **Given** a notification targeted at someone else, **When** an unrelated administrator looks,
   **Then** they neither see it nor see it counted.
3. **Given** an administrator viewing their notifications, **When** they mark one read, **Then** its
   unread state clears for them without affecting anyone else.
4. **Given** a notification with a direct action, **When** the administrator activates it, **Then**
   they are taken to the owning screen to act.
5. **Given** the same condition recurs many times, **When** the administrator looks, **Then** they see
   one grouped item with an occurrence count, not a flood.

---

### User Story 2 — The bell and drawer make notifications reachable (Priority: P1)

**Why this priority**: A store nobody can glance at is not a notification system. The bell is the
always-visible affordance.

**Independent Test**: With unread notifications present, confirm the bell shows the correct count, the
drawer opens to the latest items, and both are fully keyboard-operable.

**Acceptance Scenarios**:

1. **Given** unread notifications, **When** an administrator is on any CoreX screen, **Then** a bell in
   the shell header shows their unread count (capped visually at `99+`, the true count in its label).
2. **Given** the bell, **When** it is activated by keyboard or pointer, **Then** a drawer opens listing
   the latest attention items and recent notifications, and Escape closes it and returns focus to the
   bell.
3. **Given** an administrator on a non-CoreX WordPress screen, **When** they look at the toolbar,
   **Then** a CoreX notification entry is available there instead — never two bells at once.
4. **Given** any severity, **When** it is shown, **Then** it is conveyed by text and shape as well as
   colour, and works in dark mode, light mode, RTL, and at 375px width.

---

### User Story 3 — Notifications are truthful, targeted, and private (Priority: P1)

**Why this priority**: A notification system that leaks a title, a count, or a source id to someone
who shouldn't see it is worse than none.

**Independent Test**: Target a notification at holders of a specific ability; confirm a user without
that ability sees nothing — not the item, not its count, not a badge.

**Acceptance Scenarios**:

1. **Given** a notification targeted by ability, **When** a user without that ability is checked,
   **Then** no title, snippet, count, badge, or source id reaches them.
2. **Given** a shared, condition-based notification, **When** one administrator dismisses it, **Then**
   it is not falsely marked resolved for everyone.
3. **Given** an underlying condition is no longer active, **When** the system re-evaluates, **Then**
   the notification is resolved — distinct from a user having merely dismissed it.
4. **Given** any notification, **When** it is stored, **Then** it holds no secret, credential, token,
   provider payload, or raw network identifier.

---

### User Story 4 — Real events produce real notifications (Priority: P1)

**Why this priority**: The Center is only as useful as what feeds it. Producers turn framework events
into attention items.

**Independent Test**: Trigger each initial producer (new/assigned submission, failed email, failed
job, access request, security lockout, readiness blocker) and confirm a correctly targeted, correctly
categorised notification appears.

**Acceptance Scenarios**:

1. **Given** a new or assigned submission, **When** it is stored, **Then** the assigned user and
   permitted submission managers get a notification.
2. **Given** a notification email fails (Phase A's typed delivery result), **When** the failure is
   recorded, **Then** users who can manage Email Studio get a notification, deduplicated per route.
3. **Given** a background job fails, an access request is submitted, a login-lockout threshold is
   reached, or a readiness blocker appears, **When** it happens, **Then** the permitted users get a
   correctly categorised notification.
4. **Given** a module is not installed, **When** producers register, **Then** the absent module
   produces nothing — no fabricated data.

---

### User Story 5 — Preferences and retention keep it sane (Priority: P2)

**Independent Test**: Set a category preference and a severity threshold; confirm low-priority items
respect it while mandatory system notifications remain. Confirm old notifications are pruned.

**Acceptance Scenarios**:

1. **Given** a user's preferences, **When** a low-priority notification would be created, **Then** it
   respects their in-app/email/digest and category/severity choices within policy.
2. **Given** a mandatory system notification, **When** a user tries to disable it, **Then** the UI
   explains why it cannot be disabled.
3. **Given** an email notification is itself about a mail failure, **When** delivery is considered,
   **Then** no email is sent — no failure→email→failure loop.
4. **Given** notifications older than the retention window, **When** the cleanup runs, **Then** they
   are pruned, while the permanent audit record stays in Activity.

---

### User Story 6 — The WordPress Dashboard greets an administrator with CoreX (Priority: P2)

**Independent Test**: Load `/wp-admin/` as an administrator and confirm the CoreX Command Center
widget shows real site state, real attention items, a security snapshot, and safe shortcuts — with no
remote calls during render.

**Acceptance Scenarios**:

1. **Given** an administrator with CoreX visibility, **When** they load the Dashboard, **Then** the
   Command Center widget is present by default and shows the site's mode, version, readiness, and
   active-blocker count.
2. **Given** attention items exist, **When** the widget renders, **Then** it lists the highest-priority
   ones from the same canonical notification query, with a link to the full Center.
3. **Given** the widget renders, **When** it does so, **Then** it makes no remote API calls and issues
   only bounded queries; it is useful even without JavaScript.
4. **Given** a user without permission for a section, **When** the widget renders, **Then** that
   section's sensitive detail is withheld.
5. **Given** Screen Options, **When** a user hides or moves the widget, **Then** their choice is
   respected.

---

### User Story 7 — Optional Dashboard widgets, opt-in only (Priority: P3)

**Independent Test**: Enable an optional widget in settings; confirm it registers, reuses the canonical
services, and never appears for users who can see no underlying data.

**Acceptance Scenarios**:

1. **Given** the optional widgets, **When** an administrator enables one, **Then** it registers and
   draws from the same services as its full CoreX screen.
2. **Given** a Development-only widget, **When** the site is in Production, **Then** it does not appear.
3. **Given** any widget, **When** it offers actions, **Then** they are navigational only — no mode
   switch, deletion, approval, or migration is performed from a widget.

---

### Edge Cases

- 10,000+ stored notifications: unread counts and drawer queries stay fast and bounded.
- The same failure recurs 500 times: one grouped item, not 500 rows.
- A user loses an ability after a notification was targeted at that ability: they stop seeing it.
- A notification's source object is deleted: the notification degrades gracefully, no fatal.
- Multisite: counts and visibility respect the site context.
- A notification about a mail failure must never itself trigger an email that could fail and recurse.

## Requirements *(mandatory)*

### Store & privacy

- **FR-001**: Notifications MUST be stored in a managed, migration-backed store, not solely in user
  meta.
- **FR-002**: Every read and mutation MUST re-check the current actor's recipient visibility and
  required ability — including counts, badges, grouped totals, and API aggregates.
- **FR-003**: No title, snippet, count, badge, or source id may reach a user not permitted to see it.
- **FR-004**: The store MUST hold no secret, credential, token, provider payload, or raw network
  identifier.
- **FR-005**: Per-user state (unread/read, dismissed, snoozed, acknowledged) MUST be separate from the
  shared notification record.

### Recipients, severity, categories

- **FR-006**: Recipients MUST be targetable by specific user, multiple users, holders of a CoreX
  ability, users assigned to a source object, or administrators responsible for a category — not by
  hard-coded WordPress roles alone.
- **FR-007**: Severity MUST include at least critical, error, warning, action, information, and
  success; routine successes that belong as toasts or Activity MUST NOT become permanent notifications.
- **FR-008**: Categories MUST be extensible typed identifiers covering at least submissions, email,
  jobs, security, access, operations, readiness, imports/exports, editorial, setup, and system.

### States, grouping, actions

- **FR-009**: The system MUST support mark read/unread, mark-all-visible read, dismiss, snooze,
  resolve, reopen, and expire.
- **FR-010**: Dismissed (a user hid it) MUST be distinct from resolved (the condition ended); for a
  shared condition-based notification, one user reading or dismissing MUST NOT resolve it globally.
- **FR-011**: Repeated occurrences MUST update one active notification via a stable deduplication key,
  incrementing an occurrence count and preserving first/latest occurrence.
- **FR-012**: A direct action MUST navigate to the owning screen; the drawer MUST NOT perform dangerous
  mutations.

### Producers

- **FR-013**: Real producers MUST exist for at least: new/assigned submission and notification-email
  failure (fed by Phase A's typed delivery result); Email Studio delivery failure; background-job
  failure and completed export; access request; security lockout / critical hardening failure;
  readiness blocker appearing and clearing.
- **FR-014**: Producer registration MUST be dependency-aware — an absent module produces nothing.
- **FR-015**: Producers MUST NOT run remote checks (PageSpeed, Cloudflare) during notification
  rendering; they consume stored/latest results.

### Surfaces

- **FR-016**: A bell in the CoreX admin shell header MUST show the actor's real unread count (visual
  cap `99+`, true count in the accessible label), be keyboard operable, and open a drawer that closes
  on Escape and returns focus to the bell.
- **FR-017**: A WordPress admin-toolbar entry MUST make notifications reachable on non-CoreX screens;
  the shell bell and the toolbar entry MUST NOT both show at once, and the toolbar MUST NOT load the
  full CoreX admin bundle on the front end.
- **FR-018**: A full `CoreX → Notifications` screen MUST offer filtered views (inbox, requires
  attention, assigned to me, system, updates, history, preferences) with bounded server-side filtering
  and pagination, honest empty/loading/error/unavailable states, and bulk mark-read.
- **FR-019**: The Overview MUST gain a compact *Attention Required* summary that links to filtered
  Center views, without replacing or relabelling Activity's *Recent Activity*.

### Preferences, retention, loop prevention

- **FR-020**: Users MUST be able to set category and delivery preferences within policy; mandatory
  system notifications MAY remain non-disableable, with the UI explaining why.
- **FR-021**: Email delivery MUST use CoreX Mail when active and the supported fallback otherwise, with
  explicit loop prevention so a mail-failure notification never triggers another email.
- **FR-022**: Retention MUST be bounded and pruned by a scheduled job; permanent audit evidence stays
  in Activity, not in unbounded notification history.

### Dashboard

- **FR-023**: A `corex_command_center` Dashboard widget MUST be registered by default for users with
  CoreX visibility, showing site state, attention items (from the canonical notification query), a
  permitted security snapshot, and navigation-only quick actions.
- **FR-024**: The widget MUST make no remote calls during render, issue only bounded queries, be
  useful without JavaScript, and respect Screen Options.
- **FR-025**: Optional widgets MUST be opt-in, reuse canonical services, respect abilities and Screen
  Options, and never register for users who can see no underlying data; Development-only widgets MUST
  NOT appear in Production.

### Performance

- **FR-026**: Notification queries MUST be indexed and bounded; no remote calls during header,
  toolbar, Overview, or Dashboard rendering; short-lived aggregate projections MAY be cached and MUST
  be invalidated after mutations. The system MUST stay fast at 10,000 stored notifications.

## Success Criteria *(mandatory)*

- **SC-001**: An administrator can identify what needs their attention from the bell/drawer without
  opening any owning screen.
- **SC-002**: A user without the required ability sees zero notifications, zero count, and zero badge
  for items not targeted at them.
- **SC-003**: A recurring condition produces exactly one grouped notification with an accurate
  occurrence count, not one row per occurrence.
- **SC-004**: With 10,000 stored notifications, the unread-count and drawer queries stay within the
  project's admin-read budget.
- **SC-005**: No email notification is ever produced by an email-failure notification (no loop).
- **SC-006**: The Dashboard Command Center renders with real data and issues zero remote requests.
- **SC-007**: Every new surface is keyboard-operable and meets WCAG 2.2 AA in dark, light, RTL, and at
  375px.
- **SC-008**: The bell and toolbar entry never both appear on the same screen.

## Assumptions

- Contracts live in `Corex\Notifications\*` (corex-core); WP adapters/screens in
  `Corex\Config\Notifications\*` (corex-config), mirroring the Activity split. The existing
  `Corex\Profile\Notification\NotificationService` (front-office) is a different namespace and is not
  merged.
- Notifications reuse the framework's ability system for targeting; there is no new role model.
- The first release ships in-app delivery, optional critical email, and an optional daily digest;
  Slack/Teams/SMS/push/webhooks are out of scope and get no fake disabled controls.
- The scheduled retention job is the framework's first recurring job; it uses Action Scheduler when
  available, else WP-Cron, and reuses the existing bounded retention sweep.

## Out of scope

- MFA and its whole family (excluded by the owner).
- New remote integrations or live checks during rendering.
- Replacing Activity, toasts, or WordPress admin notices.
- Future notification channels beyond in-app + critical email + daily digest.
