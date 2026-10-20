# Feature Specification: Access and logs

**Feature Branch**: `spec/112-access-and-logs`

**Created**: 2026-10-20

**Status**: Draft

**Input**: The owner approved, on 2026-10-20, the stage 0 stories of the CoreX revamp as written. This spec is the
first release of that stage, R0.1, and carries seven of them verbatim as its acceptance criteria: US-SAFE-04,
US-SAFE-05, US-SAFE-06, US-SEC-04, US-BLG-08, US-DAT-14 and US-INB-12. The stories, their test cases and the
verification behind them are kept in the owner's private planning workspace.

## Why this spec exists

Seven behaviours on screens that client sites run today need tightening: who can obtain a CoreX ability, how refused
attempts are kept, what Maintenance mode closes, how an owner without a command line gets back into the admin, what
Blog Pro lets each role do, who sees security records on the Data screen, and where a resent email goes. Each is a
small, contained change. They ship together as one patch release, before any other revamp work, because the later
stages build on them: every CoreX screen will start checking its own ability (stage 3), and that is safe only once
abilities can be granted, listed and revoked deliberately.

This spec describes what each change does. It does not describe how the current behaviour could be misused.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - High-risk abilities are granted only through the role matrix (Priority: P1)

A site owner who receives access requests from colleagues approves the ones that make sense and refuses the rest.
Abilities that control the whole site (the locked and critical CoreX abilities) are never handed out through a
request: an administrator grants them deliberately, through the role matrix, or not at all. Every grant made by
approving a request is listed, with who approved it, why, and when it ends, and can be revoked.

**Why this priority**: it decides who can change the site. Stage 3 makes every CoreX screen check its ability, so the
set of people holding abilities must be deliberate before then.

**Independent Test**: as a subscriber, ask for a critical ability through the access-denied page and through the REST
route; both are refused and nothing is stored. As an administrator, approve a request for a sensitive ability with a
note and a 7-day expiry; the grant appears in the grants list and disappears after 7 days.

**Acceptance Scenarios**:

1. **Given** a signed-in user, **When** they request a locked or critical ability, or an area that contains one,
   through the access-denied page or the REST route, **Then** the request is refused with a message saying an
   administrator must grant it through the role matrix, and nothing is stored.
2. **Given** a pending request for a sensitive ability, **When** an administrator approves it, **Then** they see the
   ability's name, risk level and the requester's reason, must write a note, choose an expiry (7, 30 or 90 days, 30
   preselected) and confirm; for a "dangerous" ability they also type the role matrix's confirmation.
3. **Given** a request that a role change would refuse, **When** an administrator approves it, **Then** approval is
   refused for the same reason.
4. **Given** an area request, **When** an administrator opens the requests panel, **Then** the area's name and the
   abilities it would grant are shown.
5. **Given** a grant made by approval, **When** an administrator opens the Access screen, **Then** it is listed with
   who granted it, when, its note and its expiry, and Revoke removes it and is recorded.
6. **Given** a grant whose expiry has passed, **When** the daily check runs, **Then** the ability is removed, the
   removal is recorded, and the person and the approver each receive a notification.
7. **Given** a site upgraded with grants approved before this release, **When** an administrator opens the Access
   screen, **Then** those grants are listed as "no expiry (granted before expiries existed)", stay in effect, and can be
   revoked.
8. **Given** a pending request for an ability that is no longer requestable, **When** the site is upgraded, **Then**
   the request is closed and the requester sees why.

---

### User Story 2 - The access-denied log keeps its history (Priority: P1)

An administrator reviewing refused attempts sees each person's refusals counted, not stored one by one, so the log
keeps every person's entries and the site does not write to the database on every refused page view.

**Why this priority**: the audit log is how an owner sees who tried to reach what; it must keep its history.

**Independent Test**: refuse one user once, then have a second user load a refused page 1,000 times in an hour; the
audit tab shows the first user's entry and one entry for the second with a count of 1,000.

**Acceptance Scenarios**:

1. **Given** one person refused repeatedly for the same section within an hour, **When** the audit tab is opened,
   **Then** one entry shows the count and the first and last time.
2. **Given** a repeated refusal within that hour, **When** it happens, **Then** no database write follows the first.
3. **Given** entries from other people, **When** one person is refused 1,000 times, **Then** every other entry is still
   listed.
4. **Given** any refused page, **When** it is shown, **Then** the refusal page and its request form look and behave as
   before (spec 079).

---

### User Story 3 - Maintenance mode closes public submissions (Priority: P1)

A site owner who puts the site into Maintenance expects nothing to be stored or mailed from the public site until it
is back. Forms, sign-ups, booking and job applications and account registration stop accepting submissions; signed-in
administrators can still test. The Maintenance panel says so in plain words. Coming soon is unchanged: its forms stay
open, as spec 101 requires.

**Why this priority**: Maintenance is chosen exactly when the owner does not want visitors' data to arrive.

**Independent Test**: in Maintenance, an anonymous POST to each public submission route answers 503 and stores and
mails nothing; in Coming soon, the same POSTs succeed.

**Acceptance Scenarios**:

1. **Given** Maintenance, **When** an anonymous visitor submits to any CoreX form, flow, newsletter, booking, careers or
   account endpoint, **Then** the answer is HTTP 503 with a message that the site is in maintenance, and nothing is
   stored or mailed.
2. **Given** Maintenance, **When** a signed-in administrator submits a form, **Then** it is accepted.
3. **Given** Maintenance, **When** the admin, the login, cron or a read-only REST request is used, **Then** it works.
4. **Given** Coming soon, **When** the same submissions are made, **Then** each succeeds as spec 101 requires.
5. **Given** Maintenance is selected, **When** the owner reads the Maintenance panel, **Then** it says in plain words,
   without opening anything, that pages and form submissions are closed and that the admin and login stay open.
6. **Given** Maintenance, **When** the owner switches back to Production, **Then** submissions are accepted at once.

---

### User Story 4 - An owner without a command line can recover the sign-in (Priority: P1)

A site owner on shared hosting with no command line who has lost the login address follows the Recovery panel's
steps with the host's file manager, gets back in, and is told while recovery is switched on.

**Why this priority**: both client sites run on hosts without a shell; the recovery route must be usable there.

**Independent Test**: read the Recovery panel; define the recovery constant in `wp-config.php`; the Login protection
tab and the Operations & Security overview both show that recovery is on.

**Acceptance Scenarios**:

1. **Given** the Recovery panel, **When** an owner reads it, **Then** it gives two ways back with their steps: without a
   command line, add `define( 'COREX_LOGIN_UNGUARD', true );` to `wp-config.php` above the line that says "That's all,
   stop editing", using the host's file manager, sign in at `/wp-login.php`, then remove the line; with a command line,
   `wp corex security reset-login`.
2. **Given** the panel, **When** it is read, **Then** it says what each does: the constant reopens `wp-login.php` while
   it is set, leaves the site's own login address working and does not end a lockout (lockouts end after the lockout
   length); the command turns protection off, puts the default address back and ends every lockout.
3. **Given** the bookmark warning under the protection switches, **When** it is read, **Then** it names both ways.
4. **Given** the constant is set, **When** the Login protection tab opens, **Then** it shows: "Recovery is on:
   wp-login.php is open to everyone. Remove COREX_LOGIN_UNGUARD from wp-config.php to hide it again."
5. **Given** the panel, **When** it is read in any language, **Then** the constant and the command are shown as code,
   left to right, and can be copied, and the surrounding words are translated.
6. **Given** the constant is set, **When** the "Default login endpoints" row on the Operations & Security overview is
   read, **Then** it says the same.

---

### User Story 5 - Blog Pro follows each person's WordPress permissions (Priority: P1)

A site owner with editors and writers expects Blog Pro's screen and its actions to allow each person exactly what
WordPress allows them for the post or comment in question.

**Why this priority**: Blog Pro is in a core plugin that every client site runs.

**Independent Test**: as Contributor, Author, Editor and Administrator, try each Blog Pro action on posts and comments
the role may and may not change; the server allows exactly what WordPress allows.

**Acceptance Scenarios**:

1. **Given** any person, **When** they open Blog Pro or use one of its actions, **Then** both require the same base
   permission, and a person refused by one is refused by the other with the standard access-denied state.
2. **Given** a person WordPress lets edit posts, **When** they open Blog Pro, **Then** they see only what they may act
   on; administrators see what they see today.
3. **Given** a post, **When** someone changes its editorial state, **Then** they need permission to edit that post, and
   moving it to Published or Scheduled needs permission to publish it.
4. **Given** a comment, **When** someone approves, marks as spam or trashes it, **Then** they need permission to
   moderate comments; editing it needs permission to edit that comment; replying needs permission to moderate.
5. **Given** a post's figures and held comments, **When** they are shown, **Then** only to people who may edit that post.
6. **Given** a refused action, **When** it is attempted directly, **Then** the server refuses it whatever the screen
   shows, with a message saying what is not allowed.
7. **Given** the public share-click address, **When** it is called, **Then** it counts nothing until reading data is
   collected (a later release).

---

### User Story 6 - Security records on the Data screen go only to the people who manage them (Priority: P1)

A site owner who gives a colleague "Manage data" expects that colleague to work with the site's data without seeing
access requests, role grants or sign-in history, which belong to the Access and Operations screens.

**Why this priority**: delegating data work must not delegate security records.

**Independent Test**: as a user with "Manage data" only, the three security models are absent from the Data screen and
refused through every route; an administrator sees them as today.

**Acceptance Scenarios**:

1. **Given** a person with "Manage data" but without the ability of the screen that owns a security record (Access for
   role grants and access requests; Operations and Security for sign-in attempts), **When** they open the Data screen,
   **Then** those models are not listed.
2. **Given** the same person, **When** they ask for those records directly (list, one record, a query or an export),
   **Then** they are refused with the standard access-denied answer and nothing is returned or exported.
3. **Given** an administrator, or a person holding both abilities, **When** they use those models, **Then** they work as
   today.
4. **Given** every other model on the Data screen, **When** a "Manage data" holder uses it, **Then** it works as today.
5. **Given** an export set that includes one of these models, **When** a person without the owning ability runs it,
   **Then** that model is skipped and the export says which model was left out and why.
6. **Given** any such refusal, **When** it happens, **Then** it is recorded in the access audit.

---

### User Story 7 - Resending an email from the inbox reaches the people it was for (Priority: P1)

The person handling a submission resends one of its emails and expects it to go to the people that email was
addressed to.

**Why this priority**: a team notification must reach the team.

**Independent Test**: on a capture site with delivery failing, resend a failed team notification from a submission's
drawer; the captured resend is addressed to the team address only.

**Acceptance Scenarios**:

1. **Given** a submission's email, **When** it is resent from the drawer, **Then** it goes to the recipients that email
   was first addressed to, as recorded when it was sent.
2. **Given** a team notification or a failure notice, **When** it is resent, **Then** it never goes to the submitter.
3. **Given** an email whose recipients were not recorded, **When** the drawer is opened, **Then** resend is not offered
   for it and the drawer says why.
4. **Given** a resend, **When** it is sent, **Then** the drawer says who it went to and the submission's history
   records it.
5. **Given** a confirmation that was addressed to the submitter, **When** it is resent, **Then** it goes to the
   submitter.
6. **Given** a person not allowed to handle that submission, **When** they try to resend, **Then** they are refused, as
   today.

### Edge Cases

- A request that names an ability no longer requestable after the upgrade is closed, not left pending (Story 1).
- An expiry that falls while the approver's account has been deleted still removes the ability and notifies the person
  (Story 1).
- A refusal window spanning midnight keeps one entry with its first and last time (Story 2).
- A page cached by a proxy before Maintenance began still posts into a closed route and gets the 503 answer (Story 3).
- The recovery constant set on a site where login protection is off changes nothing visible except the notice
  (Story 4).
- An author's own held comment on their own post follows the moderation rule, not the author rule (Story 5).
- An export set made entirely of security models leaves an empty export with the explanation, not an error (Story 6).
- A resend of an email whose recorded recipient no longer exists is still sent to that address; the drawer shows the
  provider's answer (Story 7).

## Requirements *(mandatory)*

### Functional Requirements

**Access requests and grants (Story 1)**

- **FR-001**: The system MUST refuse, when it is made, any access request for a locked or critical ability or for an
  area containing one, through every entry point, storing nothing and naming the role matrix as the way to grant it.
- **FR-002**: Approving a request MUST show the ability's name, risk level and the requester's reason, and MUST require
  a written note, an expiry of 7, 30 or 90 days (30 preselected) and a confirmation; a "dangerous" ability MUST also
  require the role matrix's typed confirmation.
- **FR-003**: Approval MUST run the same policy checks as a role change and refuse whatever a role change refuses.
- **FR-004**: The requests panel MUST name an area request's area and list the abilities it would grant.
- **FR-005**: The Access screen MUST list every grant made by approval, with its approver, date, note and expiry, and
  MUST offer Revoke, which removes the ability and is recorded.
- **FR-006**: A grant MUST be removed when its expiry passes, the removal recorded, and the person and the approver
  notified in the CoreX notification center.
- **FR-007**: Grants approved before this release MUST be listed as "no expiry (granted before expiries existed)",
  stay in effect, and be revocable; they MUST NOT be removed automatically.
- **FR-008**: Pending requests for abilities that are no longer requestable MUST be closed on upgrade, with the reason
  shown to the requester.

**Access-denied log (Story 2)**

- **FR-009**: Repeated refusals of the same person for the same section within one hour MUST be stored as one entry
  with a count and the first and last time.
- **FR-010**: Within that hour, a repeated refusal MUST NOT cause a database write after the first.
- **FR-011**: One person's repeats MUST NOT remove other people's entries.
- **FR-012**: The audit tab MUST show the count and the time range.
- **FR-013**: The refusal page and its request form MUST be unchanged (spec 079).

**Maintenance (Story 3)**

- **FR-014**: In Maintenance, public submissions to every CoreX form, flow, newsletter, booking, careers and account
  endpoint MUST be refused with HTTP 503 and a maintenance message, storing and mailing nothing.
- **FR-015**: Signed-in administrators MUST still be able to submit in Maintenance.
- **FR-016**: The admin, the login, cron and read-only REST requests MUST keep working in Maintenance.
- **FR-017**: Coming soon MUST keep accepting every submission (spec 101 FR-006, US3.3).
- **FR-018**: The Maintenance panel MUST say in plain words, without expanding anything, what is closed and what stays
  open.
- **FR-019**: Leaving Maintenance MUST reopen submissions immediately.

**Recovery (Story 4)**

- **FR-020**: The Recovery panel MUST give the no-command-line route (the constant, where to add it, how to sign in,
  when to remove it) and the command-line route, and say what each does and does not do.
- **FR-021**: The bookmark warning MUST name both routes.
- **FR-022**: While the recovery constant is set, the Login protection tab and the "Default login endpoints" row on the
  Operations & Security overview MUST say that recovery is on and how to end it.
- **FR-023**: The constant and the command MUST be shown as copyable code in left-to-right direction; the surrounding
  text MUST be translatable.

**Blog Pro (Story 5)**

- **FR-024**: Opening Blog Pro and using its actions MUST require the same base permission.
- **FR-025**: Each Blog Pro action MUST be allowed by the server only when WordPress allows that person that action on
  that post or comment (edit, publish, moderate, edit comment), whatever the screen shows.
- **FR-026**: A post's figures and held comments MUST be shown only to people who may edit that post.
- **FR-027**: The public share-click address MUST count nothing until reading data is collected.
- **FR-028**: Contributor, Author, Editor and Administrator MUST each be tested against every action.

**Data screen (Story 6)**

- **FR-029**: The role grants, access requests and sign-in attempts models MUST require, in addition to "Manage data",
  the ability of the screen that owns them, for listing, reading, querying and exporting.
- **FR-030**: An export set MUST skip a model its runner may not read and say which and why.
- **FR-031**: Every such refusal MUST be recorded in the access audit.

**Inbox resend (Story 7)**

- **FR-032**: A resend from the inbox MUST go to the recipients the email was first addressed to, as recorded when it
  was sent.
- **FR-033**: An email without recorded recipients MUST NOT be offered for resend, and the drawer MUST say why.
- **FR-034**: The drawer MUST show who a resend went to, and the submission's history MUST record it.

**All stories**

- **FR-035**: Every new message MUST be translatable and read correctly right to left.
- **FR-036**: Every new screen element MUST meet WCAG 2.2 AA in dark and light.
- **FR-037**: The release notes MUST state what changes for site owners, including that past grants can now be
  reviewed and revoked and that Maintenance now closes public submissions.

### Key Entities

- **Ability grant**: an ability given to one person by approving a request; holds the ability, the person, the
  approver, the date, the note and the expiry (or "no expiry" for grants made before this release).
- **Access request**: a person's request for an ability or an area; holds its state (pending, approved, refused,
  closed) and the reason it was closed.
- **Access-denied entry**: one person's refusals for one section within a window; holds the count and the first and
  last time.
- **Email attempt**: one sending of an email; holds the recipients it was addressed to, which a resend reuses.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 0 locked or critical abilities can be obtained by any non-administrator through an access request, on
  every entry point (test cases TC-SAFE-04-01 to 03).
- **SC-002**: 100% of grants made by approval are visible on the Access screen and revocable in one action.
- **SC-003**: After 1,000 refusals of one person in an hour, 100% of other people's entries are still listed, and the
  database is written once for that hour.
- **SC-004**: In Maintenance, 0 public submissions are stored or mailed; in Coming soon, 100% are accepted.
- **SC-005**: An owner with no command line can follow the Recovery panel's steps to the admin without outside help
  (checked by the owner on a scratch site).
- **SC-006**: For each of four roles, Blog Pro's server allows exactly the actions WordPress allows, with 0 exceptions
  across the test matrix.
- **SC-007**: A "Manage data" holder without the owning abilities reads 0 security records through any route.
- **SC-008**: 100% of resent team notifications reach the team address and 0 reach the submitter.

## Client impact

- **Muva and Perego**: administrators notice no change. Both owners can now review and revoke grants made by approval.
  Maintenance (if they use it) closes public submissions; Coming soon, which Muva uses, is unchanged. The Recovery
  panel gives them a route that works without a shell. Blog Pro actions follow WordPress permissions for accounts
  below Editor. A "Manage data" holder who is not an administrator stops seeing security records. Their code-defined
  forms have no flow emails, so the resend change is not visible to them.
- **Mizzey**: pinned to v0.42.0; not affected until it moves.
- Both client sessions are sent these lines before the tag and check the release on their local copies.

## Assumptions

- "Locked" and "critical" are the risk levels already declared in CoreX's ability catalogue; the catalogue is not
  changed by this spec.
- The expiry check runs once a day, as the submission retention clean-up already does; a grant may outlive its expiry
  by up to a day.
- The one-hour window for counting refusals is fixed in this release.
- Recorded recipients exist for emails sent by the current Email Studio paths; older attempts without them fall under
  FR-033.
- Read-only REST requests remain open in Maintenance; only submission endpoints close.
- Every story, its test cases and its verification are in the owner's private planning workspace; this spec repeats
  the criteria and nothing else.
