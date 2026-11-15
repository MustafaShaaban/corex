# Feature Specification: Flows that stay live and route to people

**Feature Branch**: `spec/122-live-flows`

**Created**: 2026-10-23

**Status**: Draft

**Input**: Release R3.9 of the CoreX revamp, six stories: US-FLW-01, US-FLW-03, US-FLW-04, US-FLW-07, US-VIS-01 and
US-VIS-02, with the owner's decisions FLW-D1 (a closed flow shows a message and an alternative; flows get open and
close dates and a cap), FLW-D4 (editing a live flow publishes a new version while it stays live), FLW-D5 (routing
targets are user, team, role, flow owner, page owner and field value; teams get a screen) and FLW-D11 (flow actions,
email variables and routing extensions are finished as documented hooks). The stories, their 35 test cases and the
verification behind them are kept in the owner's private planning workspace. On 2026-10-21 the owner delegated the
revamp and asked not to be asked; where this spec settles a point the stories leave open it says "decided under the
owner's 2026-10-21 mandate".

## Why this spec exists

A visual flow (a form built in Forms & Flows) has four gaps that a site owner meets in the first week:

- **Publishing an edit takes the form off the site.** A saved draft of a live flow can only reach visitors by moving
  the flow back to draft and publishing again; in between, the form is gone and a visitor who submits is told the flow
  is "not available".
- **Routing assigns nobody.** Routing stores the target's type and a typed value. Only user, team and role targets can
  be seen by someone who does not manage every submission; flow owner, page owner, field value and the rest route to
  nobody, and the default fallback of every new flow is one of them. Teams exist only for developers.
- **The documented extension points do nothing.** A registered flow action never runs, a registered email variable is
  never filled in, and an "extension" routing target routes to nobody.
- **A closed form disappears.** A closed, expired, draft or missing flow renders nothing, for visitors and editors; a
  visitor whose page was loaded while the form was open gets a technical error. There is no open date, close date or
  response cap, although an "Expired" state and filter exist.

Muva and Perego use code-defined forms, which run none of this: their visitor forms keep working as they do today.

## Clarifications

### Session 2026-10-23 (decided under the owner's 2026-10-21 mandate)

- Q: When a visitor loaded the page before "Publish changes" and submits after it, which version judges the answers?
  → A: **The version the visitor's page carried, when that version was live at some moment in the last 24 hours (the
  life of the page's security token); the submission records that version. The spam protection is always the live
  version's, so an old page cannot bring back a weaker check. A version that is unknown, or replaced more than a day
  ago, is not trusted: the live version judges the answers.** (DECISIONS #470)
- Q: How does "Page owner" know the page the form was sent from? → A: **The form carries the page it was printed on,
  signed by the site so it cannot be changed in the browser. A missing or altered value is "the page is unknown" and
  the fallback applies.** Stored flows that still use the retired "Post author" target resolve it the same way.
  (DECISIONS #471)
- Q: Who sees a submission assigned to them without managing all submissions? → A: **Someone who does not hold
  "Manage submissions" opens the inbox when they belong to a team or have a submission assigned to them or to one of
  their roles, and sees only those submissions. They cannot export personal data, delete for good or read restricted
  notes. Holders of "Manage submissions" keep seeing everything, as today.** (DECISIONS #473)
- Q: Where are the flow's fallback recipient and the role limit for "Routed owner" set? → A: **In the Emails tab,
  once per flow, under the recipient pickers: "If Routed owner has no address" (an address; empty means the site
  administrator) and "Most people a role can reach" (default 20).** (DECISIONS #475)
- Q: Closed message, alternative, open date, close date and cap: part of a version, or of the flow? → A: **Of the
  flow. They are set in an "Availability" tab with a Save of its own and take effect at once, without publishing, so
  clearing a date reopens an expired flow and a closed message can be corrected while the flow is closed.**
  (DECISIONS #477)
- Q: What does "translatable with the site" mean for the owner's own closed message and alternative label, when CoreX
  has no translation driver? → A: **They are registered through the string-translation hooks WPML and Polylang both
  answer (`wpml_register_single_string`, `wpml_translate_single_string`); with neither installed they are shown as
  written. CoreX's own default wording is a normal translatable string.** (DECISIONS #478)
- Q: Does a cap count trashed responses? → A: **No. A response counts while it is stored and not in the trash;
  marked tests never count. Restoring one from the trash counts it again; it does not refuse anything already
  stored.** (DECISIONS #477)

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Publish changes to a live flow without taking it offline (Priority: P1), US-FLW-01

A site owner edits a form that is already on the site, saves, and presses "Publish changes". Visitors keep seeing the
form the whole time; afterwards they see the new version.

**Why this priority**: today every edit to a live form means a gap in which the form is gone.

**Independent Test**: publish a flow on a page, change a label, save, press "Publish changes" while reloading the page:
the form is on every load and shows the new label after.

**Acceptance Scenarios**:

1. **Given** a published flow whose saved draft differs from the live version, **When** the editor opens, **Then** it
   offers "Publish changes"; **Given** no difference, **Then** it says "Live version is up to date" and offers nothing
   to publish.
2. **Given** "Publish changes", **When** it runs, **Then** the saved draft becomes the live version in one step, the
   page never loses the form and no submission is refused.
3. **Given** a page loaded before "Publish changes", **When** the visitor submits after it, **Then** the submission
   is accepted and records the version the visitor saw.
4. **Given** the editor, **Then** its header shows the flow's state as a labelled chip (Draft, Published, Closed,
   Expired or Scheduled) and, when published, "Live: version N, published {date} by {name}".
5. **Given** a "Versions" tab, **Then** it lists each published version with its date and author; an owner can open an
   older version and "Copy into draft", which makes it the saved draft without publishing it; with unsaved edits the
   copy first asks the unsaved-changes question of spec 115.
6. **Given** someone else saved the draft after the editor was opened, **When** "Publish changes" is pressed, **Then**
   it is refused with "This flow changed since you opened it. Reload to see the latest draft." and nothing is
   published.
7. "Move to draft" is renamed "Unpublish" and asks for confirmation, saying the form will disappear from every page
   that shows it; Cancel leaves it published.

---

### User Story 2 - Routing assigns each submission to a real person or team (Priority: P1), US-FLW-03

A site owner routes submissions to a person, a team or a role, or to the flow's owner, the author of the page, or the
person whose value the visitor picked. The submission is assigned to that person or team, and they see it.

**Why this priority**: routing is the reason a flow exists; today most targets assign nobody.

**Independent Test**: a flow owned by Sara with the default fallback; submit as a visitor; Sara, who does not manage
all submissions, sees it in her inbox.

**Acceptance Scenarios**:

1. **Given** the routing editor, **Then** it offers User, Team, Role, Flow owner, Page owner, Field value and every
   registered routing extension, by name; Department, Post author and Email are no longer offered.
2. **Given** each target, **Then** User assigns that user; Team that team; Role that role; Flow owner the flow's
   owner; Page owner the author of the page the form was sent from; Field value the user, team or role the owner set
   for the value the visitor picked, in a value-to-owner table.
3. **Given** a Teams view in Forms & Flows, **Then** teams can be created, renamed, given members and removed, and a
   team target assigns the submission to that team.
4. **Given** a person who does not manage all submissions, **Then** they see in their inbox the submissions assigned
   to them, their team or their role, and no other.
5. **Given** a target that cannot be resolved (the page has no author, the value has no owner, the user was deleted,
   the team was removed), **Then** the flow's fallback applies; when that fails too, the flow owner; the submission's
   timeline says which target failed and why.
6. **Given** existing flow submissions stored with an owner type that names nobody, **When** the site updates,
   **Then** a step that runs once gives each to its flow's owner, deletes nothing, and the Forms & Flows screen says
   how many it moved.
7. **Given** the flow list, **Then** the owner column names the fallback in words ("Sara Ahmed", "Team: Sales",
   "Form owner (Sara Ahmed)", "Page author"), never a stored key.

---

### User Story 3 - Email the person a submission was routed to (Priority: P2), US-FLW-04

A site owner chooses "Routed owner" as the recipient of a flow email, and the person or team the routing chose gets it.

**Why this priority**: it completes routing; without it the owner types addresses into every flow.

**Independent Test**: team notification to "Routed owner"; routed to team Sales with three members; one captured
email to each of the three.

**Acceptance Scenarios**:

1. **Given** each email event, **Then** its recipient picker offers "An address", "The submitter" (an email field, by
   label) and "Routed owner".
2. **Given** routed to a user, **Then** the email goes to that user's address; to a team, to every member; to a role,
   to every user with that role up to the limit shown in the Emails tab (default 20), beyond which it goes to the
   flow's fallback recipient.
3. **Given** no address can be resolved, **Then** the email goes to the flow's fallback recipient, or the site
   administrator when none is set, and the submission's delivery record says "Routed owner had no address; sent to
   {address}".
4. **Given** the Emails tab, **Then** it shows who "Routed owner" would be for a submission no rule matches, and for
   each rule, by name.
5. **Given** a development or staging site, **Then** the email is captured, not delivered (spec 113).

---

### User Story 4 - Flow actions, email variables and routing extensions run (Priority: P2), US-FLW-07

A developer registers a flow action, an email variable or a routing target through a documented WordPress hook or the
container registries; the owner can use it and it runs.

**Why this priority**: the hooks are documented today and do nothing.

**Independent Test**: a test plugin registers an action through the hook; the action is added to a flow; a visitor
submits; the action ran once and the timeline says so.

**Acceptance Scenarios**:

1. **Given** the hooks `corex_flow_actions`, `corex_flow_email_variables` and `corex_flow_routing_targets`, and the
   container registries, **Then** an extension registered through either is offered.
2. **Given** a registered action added to a flow in the Success tab's "After submission" section, **When** a
   submission is stored, **Then** the action runs once, after storage, and its result (done, or failed with its
   message) is on the timeline; a failing action stops neither the submission nor its emails.
3. **Given** a registered email variable, **Then** it is in Email Studio's variable list and filled in when a flow
   email is sent; one that fails is left empty and the failure is logged.
4. **Given** a registered routing target, **Then** it is in the routing editor under its own label and assigns the
   owner it returns; when it returns none, the fallback applies.
5. **Given** a marked test, **Then** actions receive "this is a test" and no mail is sent (spec 113).
6. **Given** the corex-forms README, **Then** each hook is documented with an example that a test runs.

---

### User Story 5 - A closed or not-yet-open flow tells visitors so and offers another way (Priority: P1), US-VIS-01

A visitor who reaches a form that is not taking answers is told so and given another way to get in touch.

**Why this priority**: a vanished form reads as a broken page.

**Independent Test**: close a flow with the alternative "Email us" to jobs@example.com; open its page as a visitor:
the heading, "This form is closed." and the "Email us" link.

**Acceptance Scenarios**:

1. **Given** a closed or expired flow, **Then** its block shows the flow's heading and a titled notice with the
   message ("This form is closed." or the owner's own) and the alternative the owner set: a link with a label, an
   email address, or nothing.
2. **Given** a flow scheduled to open, **Then** it shows "This form opens on {date}." in the site's date and time
   format and language.
3. **Given** a draft or missing flow, **Then** visitors see nothing; a signed-in editor sees "Only editors see this:
   the form in this block is not published" with a link to it in Forms & Flows.
4. **Given** a page loaded while the flow was open, **When** it is submitted after the flow closed, **Then** the
   answers stay on screen and the closed message and its alternative are shown, not a technical error.
5. **Given** the block editor, **Then** a block whose flow is closed, expired or scheduled shows that state and names
   the flow, and the flow picker lists such flows too.
6. **Given** the flow editor's Availability tab, **Then** the owner sets the message and the alternative; both are
   translatable with the site.
7. The notice is readable in light and dark and right to left, and is read once by a screen reader.

---

### User Story 6 - A flow opens and closes on its dates or after a number of responses (Priority: P2), US-VIS-02

A site owner running a registration sets when the form opens, when it closes and how many responses it takes.

**Why this priority**: it removes the need to be online to close a form.

**Independent Test**: cap 3 with two responses stored and one marked test; submit twice: the first is accepted, the
second gets the closed message.

**Acceptance Scenarios**:

1. **Given** the Availability tab, **Then** it offers "Opens on", "Closes on" (date and time, in the site's time
   zone) and "Stop after [N] responses", each optional.
2. **Given** a time before the open date, **Then** the block shows the not-yet-open message and a submission is
   refused with it.
3. **Given** the close date has passed, or N responses are stored (marked tests not counted), **Then** the block shows
   the closed message, submissions are refused, and the list shows "Expired" with the reason ("Closed on {date}",
   "Reached N responses").
4. **Given** two visitors submitting the last allowed response at the same moment, **Then** one is accepted and the
   other gets the closed message; the count never exceeds N.
5. **Given** an expired flow, **When** the dates or the cap are changed or cleared, **Then** it is open again without
   publishing.
6. **Given** a site whose WordPress cron does not run, **Then** dates still apply: they are checked when the page is
   served and when a submission arrives.

---

### Edge Cases

- A flow published before this release has no publication history: its live version is listed with its date and
  "unknown" as the author.
- "Publish changes" on a draft that fails validation is refused with the reason, and the live version stays.
- A visitor's page carries a version number that was never published: the live version judges the answers.
- A team is removed while submissions are assigned to it: those submissions stay assigned to the team key, readable
  by managers; new submissions routed to it use the fallback.
- A role target names a role that no longer exists: the fallback applies.
- A field value routing whose field is a multi-choice question: the first picked value that has an owner wins.
- A registered action or routing target removed from the code: an action is skipped with "not available" on the
  timeline; a target falls back.
- A flow with a close date before its open date cannot be saved: the Availability tab says why.
- A closed flow with an alternative link that is not a web address, or an email that is not an address, cannot be
  saved.
- A submission arrives while the inbox stage cannot write: unchanged from today (the stage fails and says so).

## Requirements *(mandatory)*

### Functional Requirements

**Publishing (US-FLW-01)**

- **FR-001**: A published flow MUST accept "publish" when its saved draft version is not its live version; it makes
  the saved draft the live version without leaving the published state.
- **FR-002**: Every publication MUST be recorded on the flow: version number, time and the person who published.
- **FR-003**: Publishing MUST refuse, with "This flow changed since you opened it. Reload to see the latest draft.",
  when the draft version the editor holds is not the saved draft; nothing is published.
- **FR-004**: The editor MUST show the state chip, the live version line and "Publish changes" or "Live version is up
  to date" as in User Story 1.
- **FR-005**: A "Versions" tab MUST list published versions newest first with date and author, open any version read
  only, and copy it into the draft as a new saved draft version.
- **FR-006**: "Unpublish" MUST ask for confirmation in a dialog that names the consequence; Cancel changes nothing.
- **FR-007**: The visitor form MUST carry the version it was printed from, and a submission MUST be judged by and
  record that version under the rule in Clarifications; spam protection is the live version's.

**Routing (US-FLW-03)**

- **FR-008**: Routing MUST resolve every target to an assignee: a user, a team or a role, or "unassigned" with a
  reason.
- **FR-009**: A matched target that does not resolve MUST fall back to the flow's fallback, then to the flow owner;
  each failure MUST be written on the submission's timeline with its reason.
- **FR-010**: The form MUST carry a signed reference to the page it was printed on; page owner resolves to that page's
  author.
- **FR-011**: Field value routing MUST use a value-to-owner table on the target (value → user, team or role).
- **FR-012**: Teams MUST be stored by CoreX with a key, a name and members, and managed from a Teams view in Forms &
  Flows by people who may manage forms.
- **FR-013**: The inbox scope MUST include the actor's teams, and a person without "Manage submissions" MUST get an
  assigned-only inbox under the rule in Clarifications.
- **FR-014**: A one-time upgrade MUST reassign flow submissions whose owner type is not a user, team or role to their
  flow's owner, record the count, and the Forms & Flows screen MUST state it.
- **FR-015**: The flow list MUST name the fallback in words.
- **FR-016**: The routing editor MUST offer only User, Team, Role, Flow owner, Page owner, Field value and registered
  targets, with a picker for each value (people, teams, roles, fields).

**Routed owner (US-FLW-04)**

- **FR-017**: A recipient MAY be "Routed owner"; it resolves to the assignee's addresses (user, team members, role
  members up to the limit).
- **FR-018**: With no address, or a role over the limit, it MUST go to the flow's fallback recipient, else the site
  administrator, and record "Routed owner had no address; sent to {address}" (or the role-limit reason) with the
  delivery and on the timeline.
- **FR-019**: The Emails tab MUST preview the routed owner of each rule and of the fallback by name and address.
- **FR-020**: The inbox's "Send again" MUST reach the same routed recipients.

**Extensions (US-FLW-07)**

- **FR-021**: Flow actions, email variables and routing targets MUST be registrable through the filters
  `corex_flow_actions`, `corex_flow_email_variables` and `corex_flow_routing_targets` and through their registries.
- **FR-022**: A flow's chosen actions MUST run once per stored submission after storage, receive the submission, the
  flow and whether it is a test, and record "done" or "failed: {message}" on the timeline; a failure MUST NOT change
  the submission's outcome.
- **FR-023**: Registered email variables MUST be declared to Email Studio's variable list and resolved into the mail
  context of flow emails; a failure leaves the value empty and is logged.
- **FR-024**: A registered routing target MUST resolve to an assignee or none; none falls back (FR-009).
- **FR-025**: The README MUST document each hook with an example that a test runs.

**Visitors (US-VIS-01, US-VIS-02)**

- **FR-026**: A flow's availability (closed message, alternative, open date, close date, cap) MUST be stored on the
  flow, saved without publishing, and validated (dates in order, a link that is a web address, an email that is an
  address, a cap of at least 1).
- **FR-027**: The effective state MUST be computed when the page is served and when a submission arrives: draft;
  scheduled before the open date; expired from the close date or at the cap; closed when closed by hand; otherwise
  open.
- **FR-028**: The block MUST render the closed or scheduled notice, the editor-only notice, or nothing, as in User
  Story 5.
- **FR-029**: A submission to a flow that is not open MUST be refused with HTTP 409, code `flow_closed`, and the
  notice (title, message, alternative); the visitor runtime MUST show it and keep the answers.
- **FR-030**: Count-and-store for a capped flow MUST be serialized so the count never exceeds the cap.
- **FR-031**: The flow list MUST show Scheduled and Expired with the reason, and its "Expired" filter MUST match
  flows expired by date or cap.
- **FR-032**: The owner's message and alternative label MUST be registered and translated through the
  string-translation hooks; CoreX's default wording MUST be translatable.

### Key Entities

- **Publication**: version number, published at, published by. A list on the flow.
- **Availability**: opens at, closes at (UTC instants), response cap, closed message, alternative (none, link with
  label and address, or email address with label). On the flow, outside its versions.
- **Team**: key, name, member user IDs.
- **Assignee**: a type (user, team, role, or none) and a key, with a display name and the reasons any target failed.
- **Routed recipients**: the addresses the assignee resolves to and, when a fallback was used, the note that says so.
- **Flow action**: key, label, callback; a flow's configuration lists the keys it runs.

## Success Criteria *(mandatory)*

- **SC-001**: An owner publishes a change to a live flow in one action, and a page reloaded every second throughout
  shows the form every time.
- **SC-002**: Every new flow submission is assigned to a user, team or role; none is stored with an owner that names
  nobody, and the upgrade leaves none from before.
- **SC-003**: A person who does not manage all submissions sees exactly the submissions assigned to them, their team
  or their role.
- **SC-004**: A registered action, variable and routing target each take effect on the first submission after being
  registered, in a test that registers them through the documented hooks.
- **SC-005**: A closed, expired or scheduled flow's page always tells the visitor why and offers the alternative set;
  a submission refused for that reason keeps every answer on screen.
- **SC-006**: With a cap of N, two simultaneous last submissions store exactly N responses.
- **SC-007**: Muva's and Perego's code-defined forms store and send exactly as before (their suites and the
  code-form tests pass unchanged).

## Assumptions

- Submissions stay `corex_submission` posts; owner type and key stay where the inbox reads them.
- The inbox's owner names (US-INB-01) belong to R3.10; this release stores real owners so they can be named.
- Plain-word editors for conditions and rules (US-FLW-05) belong to R3.10; this release replaces only the target
  pickers and the recipient picker.
- Abilities (spec 117) may rename the capability the flow routes ask for; this release asks through the same gateway.
- Email Studio's variable list is the one place a template declares variables (spec 120 may change how it is shown).

## Client impact

- **Muva** and **Perego**: their forms are code-defined. Nothing they store, send or show to visitors changes. The
  upgrade step finds no flow submissions on either site. The visitor runtime gains a branch for a code (`flow_closed`)
  their routes never answer. Their inbox gains nobody: no one on either site is in a team, and no flow submission is
  assigned to a role.
- **Mizzey**: not on this release (it pins v0.42.0).
