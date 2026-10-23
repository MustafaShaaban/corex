# Feature Specification: Mail stays where it should

**Feature Branch**: `spec/113-mail-capture`

**Created**: 2026-10-21

**Status**: Draft

**Input**: The owner approved, on 2026-10-20, the stage 0 stories of the CoreX revamp as written. This spec is the
second release of that stage, R0.2, and carries five of them as its acceptance criteria: US-SAFE-02, US-SAFE-01,
US-SAFE-03, US-SAFE-17 and US-EML-18. The owner answered the questions that shape them on 2026-10-19 (decisions
SAFE-D1, SAFE-D2, SAFE-D3 and EML-D6, quoted under Clarifications). The stories, their 38 test cases and the
verification behind them are kept in the owner's private planning workspace.

## Why this spec exists

A site that is not live yet should never send real mail: a developer testing a client site must not mail the client's
customers, subscribers or applicants. Today only Email Studio's own sends ask whether the site is a development site,
and the answer comes from a setting that no admin screen can change. The operations mode the owner picks in the admin
(Development, Staging, Production, Coming soon, Maintenance) changes no mail at all. A flow's "Run marked test" sends
real mail. Email Studio's overview does not say whether mail leaves the site, and on a site with more than 500 emails
its log stops showing new ones.

This release makes one setting, the operations mode, decide whether a site is a development site; makes every email on
a development or staging site stay on the site; holds every email a flow test would send; says plainly on every Email
Studio tab what happens to mail; and keeps the log and its clean-up working on a busy site. Production mail is not
changed in any way (SAFE-D2).

## Clarifications

### Session 2026-10-19 (the owner's decisions, recorded in the planning workspace)

- Q (SAFE-D1): Which setting decides whether a site captures mail? → A: **The operations mode, set from the admin
  (shared hosts have no shell). `APP_ENV`, when set, locks it and the screen says so. With no mode chosen, WordPress's
  `local`/`development`/`staging` map to Development/Staging. Coming soon and Maintenance deliver like Production.**
- Q (SAFE-D2): Production mail outside Email Studio (contact form, newsletter, bookings, careers, client code) ignores
  the live-delivery switch, and client sites rely on that. Keep it? → A: **Keep production exactly as it is in stage 0;
  this release changes development and staging only.** Unifying the production rule is a later story.
- Q (SAFE-D3): Should a flow's "Run marked test" hold its emails in every environment, including production? → A:
  **Yes: held, listed in the result and the log, never sent.**
- Q (EML-D6): On development and staging, capture all mail or only CoreX's? → A: **All mail by default, with a
  switch.**

## User Scenarios & Testing *(mandatory)*

### User Story 1 - One setting decides whether a site is a development site (Priority: P1)

A site owner on shared hosting, with no command line, sets "this is a development site" from the CoreX admin, and mail
capture follows that choice. Every screen gives the same answer.

**Why this priority**: Story 2 depends on it: capture can follow the owner's choice only once one setting decides.

**Independent Test**: on a site with no `APP_ENV`, set the operations mode to Development; Email Studio, Overview,
Operations & Security and Insights all say Development, and an Email Studio test send is captured. Set it back to
Production; all four say Production and the mode history records both changes.

**Acceptance Scenarios**:

1. **Given** a site with no `APP_ENV`, **When** the owner sets the mode to Development or Staging, **Then** the site
   captures mail; **When** they set it to Production, Coming soon or Maintenance, **Then** it delivers as production
   does today.
2. **Given** `APP_ENV` set in `.env`, or a value locked by the network, **When** the owner opens Operations & Security,
   **Then** the mode is shown as locked with where it is set, the selector cannot change it, and mail follows that
   value.
3. **Given** no mode has been chosen, **When** WordPress's environment type is `local` or `development`, **Then** the
   site is treated as Development; `staging` as Staging; `production` or anything else as Production.
4. **Given** any site, **When** Email Studio, the Overview, Operations & Security and Insights are opened, **Then** they
   all show the same answer to "is this a development site".
5. **Given** a mode change to or from Production, **When** it is saved, **Then** who changed it and when is recorded in
   the mode history.
6. **Given** a site upgraded from v0.47 that never chose a mode or set `APP_ENV`, and whose WordPress environment is
   `production` or unset, **When** it is upgraded, **Then** it stays Production and nothing delivers differently.

---

### User Story 2 - Mail stays on a development or staging site (Priority: P1)

A developer working on a client site before it launches can submit every form, publish posts and register accounts
without any customer, subscriber or applicant receiving mail. Every message is captured and can be read in Email
Studio's log, named by what sent it.

**Why this priority**: a test email to a real customer cannot be called back.

**Independent Test**: on a development site with CoreX Mail active, submit the contact form, subscribe to the
newsletter and publish a post, send a booking request, apply for a job, register an account and ask for a password
reset. A mail spy records nothing; the Email Studio log lists each message as Captured with its path.

**Acceptance Scenarios**:

1. **Given** a development or staging site, **When** any CoreX path sends mail, **Then** the message is captured and
   nothing reaches the mail transport.
2. **Given** a development or staging site, **When** WordPress core or another plugin sends mail, **Then** it is
   captured the same way, by default.
3. **Given** the "Capture only CoreX mail" switch on the Email Studio overview (off by default), **When** an owner
   turns it on, **Then** only CoreX's own mail is captured, other mail is delivered, and the overview says in one
   sentence which mail is captured.
4. **Given** a captured message, **When** the owner opens the Email Studio log, **Then** it shows the state Captured,
   its recipient, its subject and the path that sent it (for example "Newsletter: new post", "WordPress: password
   reset", "Other: wp_mail").
5. **Given** a development or staging site where CoreX Mail is not active, **When** any mail is sent, **Then** it is
   not delivered: it is refused, and each refusal is recorded in the activity log with its recipient, subject and
   path.
6. **Given** a site in Production, Coming soon or Maintenance, **When** any path sends mail, **Then** it is delivered
   exactly as today, including client code on the `Mailer` seam with no provider or live delivery set.
7. **Given** a staging site with a provider set and live delivery on, **When** Email Studio and any other path send
   mail, **Then** both follow the same rule; they never disagree.
8. **Given** a captured message, **When** its capture retention window passes, **Then** it is deleted (Story 4).

---

### User Story 3 - A test run of a flow never delivers mail (Priority: P1)

Someone building a form presses "Run marked test" and sees the emails the run would send, without any of them being
sent, on any site, including production.

**Why this priority**: testing a form on a live site must never mail the client's team or a stranger.

**Independent Test**: on a production site with a provider and live delivery on, run a marked test of a flow with an
admin email and a submitter email. A mail spy records nothing; the result lists both emails as Held for test, each
with a link to the Email Studio log. A real submission made right after delivers both.

**Acceptance Scenarios**:

1. **Given** any environment, including production, **When** "Run marked test" runs, **Then** no email from that run
   reaches the mail transport.
2. **Given** a finished test run, **When** its result is shown, **Then** it lists each email the run would have sent,
   with its recipient, subject and a link to it in the Email Studio log as Held for test.
3. **Given** the flow's Test tab, **When** it is opened, **Then** it says, before the button is pressed, that emails
   are held and not sent.
4. **Given** a test run just finished, **When** a visitor submits the same flow, **Then** its emails deliver as before.
5. **Given** held messages, **When** "clear test entries" runs, **Then** they are deleted with the test submission;
   otherwise the capture retention window removes them.

---

### User Story 4 - Email Studio says what happens to mail on this site (Priority: P1)

A site owner reads, on every Email Studio tab, whether mail from this site is delivered, captured or blocked, which
setting decided it, and any path that does not follow it. Captured and held messages are removed when their retention
ends.

**Why this priority**: an owner must never be surprised that mail went out, or that it did not.

**Independent Test**: open Email Studio on a production site with a provider and live delivery on, on a development
site, and on a production site with no provider and live delivery off (a client site's setting); the statement matches
what each site does, on every tab.

**Acceptance Scenarios**:

1. **Given** any site, **When** Email Studio is opened, **Then** it states the outcome in words for the site's current
   mode: "Delivered for real", "Captured: nothing leaves this site" or "Blocked: nothing is sent".
2. **Given** any site, **When** the statement is read, **Then** it says whether live delivery is on, which provider is
   set, and which setting decided the outcome (the operations mode, or a locked `APP_ENV`).
3. **Given** a development or staging site, **When** the statement is read, **Then** it says whether capture covers all
   mail or only CoreX mail.
4. **Given** a staging site, **When** the statement is read, **Then** it matches what staging does for each
   combination of provider and live delivery.
5. **Given** a site where some paths do not follow the outcome (for example production with no provider: Email Studio
   sends are blocked while form, newsletter, booking and careers mail and site code still deliver), **When** the
   statement is read, **Then** it names those paths; it is never more reassuring than the truth.
6. **Given** any Email Studio tab, **When** it is opened, **Then** the same statement appears on it as a banner.
7. **Given** captured messages and messages held for a test past their retention date, **When** the daily clean-up
   runs, **Then** they are deleted, every one of them however many are stored, and newer ones are kept.

---

### User Story 5 - Email Studio shows the newest mail on a busy site (Priority: P1)

A site owner checking whether mail went out today sees today's emails first in Email Studio's log, and overview counts
that describe recent mail, however many emails the site has stored.

**Why this priority**: past 500 stored emails, the log and the overview stop changing and say nothing about today.

**Independent Test**: seed 600 attempts, the newest dated today; the log's first row is today's newest. Send one more
test email and reload; it is the first row and the overview counts changed.

**Acceptance Scenarios**:

1. **Given** more than 500 attempts or captures stored, **When** the log is opened, **Then** its first row is the most
   recent email.
2. **Given** the overview, **When** it is opened, **Then** its counts are taken from the most recent emails and say
   what window they count.
3. **Given** a newly sent or captured email, **When** the log is next loaded, **Then** it appears.
4. **Given** captured and held mail past its retention date, **When** the clean-up runs, **Then** it reaches every such
   record, not only the oldest 500.
5. **Given** a site with fewer than 500 records, **When** the log is opened, **Then** it shows exactly what it shows
   today.
6. **Given** 5,000 records, **When** the log is loaded, **Then** it loads in under a second and makes no database query
   per row.

---

### Edge Cases

- A WordPress environment of `local` with no mode chosen is Development, not Production (Story 1).
- A mode locked by `APP_ENV` to `production` on a site whose stored mode is Development delivers, and the screen says
  why (Story 1).
- Mail queued while the site was in Development and sent after it moved to Production is sent under the rule in force
  when it is sent (Story 2).
- A message with several recipients is captured once, with all its recipients (Story 2).
- A captured message is never resent by an automatic retry when the site later moves to Production (Story 2).
- A flow test whose flow has no email binding lists no emails and says so (Story 3).
- A flow test run while CoreX Mail is inactive holds nothing in Email Studio but still sends nothing; the result says
  the emails were not sent (Story 3).
- A capture whose payload cannot be read is skipped in the log and still removed by the clean-up (Stories 4 and 5).
- The clean-up removes thousands of expired records in batches, without running out of time or memory on a shared
  host (Stories 4 and 5).

## Requirements *(mandatory)*

### Functional Requirements

**One setting (Story 1)**

- **FR-001**: The site's environment for mail and for every screen MUST be decided, in this order: a value set in
  `.env` (`APP_ENV`) or locked by the network; otherwise the operations mode the owner chose; otherwise WordPress's
  environment type (`local` and `development` → Development, `staging` → Staging, anything else → Production).
- **FR-002**: Development and Staging MUST capture mail (FR-005); Production, Coming soon and Maintenance MUST deliver
  as production does today.
- **FR-003**: When `APP_ENV` or the network decides, the Operations & Security screen MUST show the mode as locked and
  where it is set, and MUST NOT let the selector change it.
- **FR-004**: Email Studio, the Overview, Operations & Security and Insights MUST read that one answer; no screen MUST
  read a different source for "is this a development site".
- **FR-004a**: A change of mode to or from Production MUST be recorded in the mode history with who and when.
- **FR-004b**: A site that never chose a mode or set `APP_ENV`, with a WordPress environment of `production` or none,
  MUST stay Production after the upgrade.

**Capture (Story 2)**

- **FR-005**: On a development or staging site, every email from every CoreX path MUST be captured instead of reaching
  the mail transport, including queued mail when the queue runs and mail sent by the account add-on through WordPress.
- **FR-006**: By default, WordPress core mail and other plugins' mail on a development or staging site MUST be captured
  the same way.
- **FR-007**: The Email Studio overview MUST offer a switch, "Capture only CoreX mail", off by default; when on, only
  CoreX's own mail is captured and other mail is delivered.
- **FR-008**: Each captured message MUST appear in the Email Studio log as Captured with its recipients, subject and
  the name of the path that sent it.
- **FR-009**: On a development or staging site where CoreX Mail is not active, mail MUST NOT be delivered; each refused
  message MUST be recorded in the activity log with its recipients, subject and path.
- **FR-010**: On a site in Production, Coming soon or Maintenance, every path MUST deliver exactly as it does today,
  including client code on the `Mailer` seam with no provider or live delivery set.
- **FR-011**: On staging with a provider set and live delivery on, Email Studio's paths and every other path MUST
  follow the same rule.
- **FR-012**: Captured messages MUST follow the capture retention window (FR-020).

**Flow tests (Story 3)**

- **FR-013**: "Run marked test" MUST NOT let any email from its run reach the mail transport, in any environment.
- **FR-014**: The test result MUST list each email the run would have sent, with its recipient, subject and a link to
  it in the Email Studio log, shown as Held for test.
- **FR-015**: The Test tab MUST say, before the button is pressed, that emails are held and not sent.
- **FR-016**: A real submission to the same flow MUST deliver as before.
- **FR-017**: Held messages MUST be deleted with their test submission when test entries are cleared, and otherwise by
  the capture retention window.

**What Email Studio says (Story 4)**

- **FR-018**: Every Email Studio tab MUST carry a statement of the outcome for the site's current mode: "Delivered for
  real", "Captured: nothing leaves this site" or "Blocked: nothing is sent", with whether live delivery is on, which
  provider is set, which setting decided, and, on a development or staging site, whether capture covers all mail or
  only CoreX mail. Staging MUST be covered.
- **FR-019**: When any path does not follow the outcome, the statement MUST name those paths; it MUST NOT be more
  reassuring than what the site does.
- **FR-020**: Captured messages and messages held for a test MUST be deleted once their retention date has passed, by
  the daily clean-up, reaching every such record however many are stored.

**The log on a busy site (Story 5)**

- **FR-021**: The Email Studio log MUST list the most recent emails first, whatever number is stored.
- **FR-022**: The overview counts MUST be taken from the most recent emails and say the window they count.
- **FR-023**: A newly sent or captured email MUST appear at the next load.
- **FR-024**: A site with fewer than 500 records MUST show exactly what it shows today.
- **FR-025**: The log MUST make no database query per row and load in under a second with 5,000 records.

**All stories**

- **FR-026**: Every new message MUST be translatable and read correctly right to left; path names, states and dates in
  the log MUST read correctly in Arabic.
- **FR-027**: Every new screen element MUST meet WCAG 2.2 AA in dark and light.
- **FR-028**: The release notes MUST say that production mail is unchanged, that local and staging copies of a client
  site start capturing mail, and that the log shows the newest mail.

### Key Entities

- **Site environment**: Development, Staging or Production for mail, decided by FR-001; carries the setting that
  decided it (`APP_ENV`, the network, the operations mode, or WordPress's environment type) and whether it is locked.
- **Mail outcome**: what happens to mail on this site (delivered, captured or blocked), the paths that do not follow
  it, and whether capture covers all mail or only CoreX mail.
- **Mail path**: the name of what sent a message ("Form notification", "Newsletter: new post", "WordPress: password
  reset", "Other: wp_mail"), shown in the log.
- **Captured message**: a message kept instead of sent, with its recipients, subject, body, path, state (Captured or
  Held for test) and retention date.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: On a development or staging site, 0 messages from the 14 CoreX mail paths reach the mail transport, and
  100% of them are in the log with their path (test cases TC-SAFE-01-01 to 05).
- **SC-002**: On a production site with no provider and live delivery off, 100% of the mail that delivered before the
  release still delivers (TC-SAFE-01-07).
- **SC-003**: Email Studio, the Overview, Operations & Security and Insights give the same environment on 100% of the
  combinations of mode, `APP_ENV` and WordPress environment in the test matrix.
- **SC-004**: A marked flow test sends 0 emails in every environment.
- **SC-005**: The Email Studio statement matches what the site does in 100% of the tested combinations, including
  staging and a client site's production setting.
- **SC-006**: With 600 stored attempts, the log's first row is the newest; with 5,000, the log loads in under a second.
- **SC-007**: After the clean-up, 0 captured or held messages past their retention date remain.

## Client impact

- **Muva and Perego**: production mail is unchanged (SAFE-D2). Their local and staging copies, and any copy whose
  operations mode is Development or Staging or whose WordPress environment is `local`, `development` or `staging`,
  start capturing mail instead of sending it. Email Studio's log starts showing the newest mail. The owner tells both
  client sessions before they take the release; each checks its own mail on a local copy.
- A client site whose code sends through the `Mailer` seam keeps working unchanged in production; on a development or
  staging copy its mail is captured.
- **Mizzey**: pinned to v0.42.0; not affected until it moves.

## Assumptions

- The capture retention window is the existing 30 days; this release does not make it configurable.
- "CoreX mail" for the "Capture only CoreX mail" switch means mail sent by a CoreX plugin or add-on, and mail a site's
  own code sends through CoreX's `Mailer` seam.
- The live-delivery switch and the provider setting keep their current meaning; this release changes neither for
  production (SAFE-D2).
- Coming soon and Maintenance are production for mail.
- The 14 CoreX mail paths are the ones listed in the private verification; any path added later inherits capture
  because capture is decided where mail meets the transport, not in each path.
- Every story, its test cases and its verification are in the owner's private planning workspace; this spec repeats
  the criteria and nothing else.
