# Feature Specification: Modes and login protection that do what they say

**Feature Branch**: `spec/124-modes-and-login`

**Created**: 2026-10-26

**Status**: Draft

**Input**: Release R3.8 of the CoreX revamp, eleven stories approved under the owner's 2026-10-21 mandate:
US-OPS-01, US-OPS-02, US-OPS-05, US-OPS-06, US-OPS-07, US-SEC-01, US-SEC-02, US-SEC-03, US-SEC-07, US-SEC-10 and
US-VIS-04. The stories, their test cases and the reading of the code behind them are kept in the owner's private
planning workspace. It builds on spec 112 (Maintenance closes public submissions), spec 113 (one answer to "what kind
of site is this", and the mail gate) and spec 118 (one launch checklist).

## Why this spec exists

The operations mode is the one switch that says what kind of site this is, and today most of what it says is advice.
Development and Staging say search engines "should" be kept out and change nothing. There is no way to stop every
form while the site stays open. Going live past open readiness blockers takes the same phrase as a clean launch and
leaves the same history row. The maintenance page cannot carry the owner's message or a time to come back. The mode
is chosen from a dropdown that shows one mode at a time, and Apply can be pressed before the confirmation it needs.

Login protection has the same problem. One switch turns everything on, the "hide" box does nothing alone, and it
hides the login and the admin area together. Five saved settings have no field. A lockout cannot be released from
the screen and never names the account it locked. Hardening calls a development copy "this production site" and
counts live-only checks as warnings on every local copy. And a record of a sign-in attempt does not always keep the
typed username and the visitor's address unreadable.

This release makes each mode and each login setting do exactly what its words say.

## Clarifications

### Session 2026-10-26 (decided under the owner's 2026-10-21 mandate)

The owner delegated the revamp and asked not to be consulted. Where a story leaves a choice open, the planning
workspace's recommended answer is taken and recorded as decided under that mandate (DECISIONS #520 to #529).

- Q: Does the mode decide indexing? → A: **Yes** (OPS-D2). Development and Staging answer every public page with
  `noindex`, in the page and in an `X-Robots-Tag` header; the Reading setting is never changed.
- Q: Which new modes ship? → A: **Forms paused first** (OPS-D1). Private and Read-only are later releases.
- Q: May Production be switched on with blockers? → A: **Yes, with a different phrase, "GO LIVE ANYWAY", recorded as
  an override** (OPS-D3).
- Q: Maintenance page message, back-by time? → A: **Yes** (OPS-D11). The page keeps today's renderer until spec 110's
  branded renderer lands; the fields and timing are this release's.
- Q: Separate switches for hiding the login, hiding the admin and limiting attempts? → A: **Yes** (SEC-D6).
- Q: What may a sign-in record show? → A: **Hashes made with a key specific to the site; the account name only when
  the username belongs to an account, "unknown username" otherwise, and a short network fingerprint, never a full
  address** (SEC-D7).
- Q: Where does the own login address work when only some switches are on? → A: **Whenever any switch is on**, as it
  did while protection was on. A site that had protection on without hiding keeps its own address beside
  `wp-login.php`; the address field is required only to hide `wp-login.php`.
- Q: Where are the maintenance message and the Forms paused message written? → A: **One "Messages for visitors" card
  on the Environment tab** with a Maintenance part and a Forms paused part, saved on its own, so a message can be
  written before the mode is chosen and changed while it is in force. Each mode's card points to it.
- Q: What are "the site languages"? → A: **The site's language, or every language a multilingual plugin reports**
  (Polylang when present; a site can declare its own through a filter). One message field per language, and the page
  shows each language's message in its own direction.
- Q: Which hardening checks are "live only"? → A: **HTTPS, debug output and search engines** (the story's three).
- Q: Who may release a lockout? → A: **Whoever may manage Operations & Security** (the screen's own gate).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Each mode says and does what it promises (US-OPS-01, Priority: P1)

A site owner reads, for the current mode and for any mode being considered, what happens to email, form submissions,
search engines and analytics, and every row is true on the running site.

**Why this priority**: a development or review copy must never mail customers or appear in search results.

**Independent Test**: switch to Development, sign out, request a page: `noindex` in the page and the header; switch to
Production: gone, and Settings > Reading unchanged.

**Acceptance Scenarios**:

1. **Given** Production, **When** the owner opens Environment & Maintenance, **Then** "What this mode does" lists
   Email Delivered, Form submissions Accepted, Search engines Allowed (unless Settings > Reading discourages them),
   Analytics "None collected by CoreX".
2. **Given** Production, **When** Staging is selected and not applied, **Then** Staging's four rows are shown and
   nothing is applied.
3. **Given** Development, **When** a signed-out visitor opens any public page, **Then** it carries a robots `noindex`
   and an `X-Robots-Tag: noindex` header; a form submission is stored and its mail captured.
4. **Given** Development, **When** the owner switches to Production, **Then** no page carries `noindex` and the Reading
   setting is what it was.
5. **Given** no mode declared and WordPress's environment `staging`, **Then** the panel says Staging, inherited from
   WordPress, and pages carry `noindex`.
6. **Given** each mode in turn, **Then** each row matches the measured mail, form and header behaviour.
7. **Given** a lock (`APP_ENV`) decides the environment, **Then** the Email row says so; **given** a row's answer cannot
   be read, **Then** the row says "Could not check".

---

### User Story 2 - Forms can be paused while the site stays open (US-OPS-02, P2)

**Acceptance Scenarios**:

1. **Given** Production, **When** the owner chooses Forms paused, ticks the acknowledgement and applies, **Then** the
   mode is Forms paused and the history names who and when.
2. **Given** Forms paused, **When** an anonymous visitor posts to a code-defined form, a flow, the newsletter, a booking
   or a job application, **Then** each is refused with the paused message and HTTP 503; nothing is stored or mailed.
3. **Given** Forms paused, **Then** pages, sign-in, password reset and comments keep working.
4. **Given** Forms paused and a signed-in administrator, **When** they submit a form, **Then** it is accepted.
5. **Given** one flow closed and one open before pausing, **When** the site returns to Production, **Then** the open
   flow accepts and the closed one still refuses.
6. Maintenance and Forms paused refuse exactly the same endpoints (one gate).
7. `wp corex mode set forms-paused --acknowledge` does the same and is in the history.

---

### User Story 3 - Going live past open blockers needs its own phrase and is recorded (US-OPS-05, P1)

**Acceptance Scenarios**:

1. **Given** no blockers, **When** the owner types PRODUCTION and applies, **Then** the site is in Production and the
   history says "Went live" with who and when.
2. **Given** two blockers, **When** Production is chosen, **Then** the panel lists both by name and asks for
   "GO LIVE ANYWAY".
3. **Given** the same, **When** PRODUCTION is typed, **Then** it is refused with a message naming the blockers and the
   override phrase; the mode is unchanged.
4. **Given** the same, **When** GO LIVE ANYWAY is typed, **Then** the site is in Production, the history says "Went live
   with 2 blockers overridden" and names both, and a security event is in the activity log.
5. **Given** no blockers when the page loaded and one appears before submitting, **When** PRODUCTION is typed,
   **Then** it is refused and the override is asked for, naming the new blocker.
6. **Given** blockers, `wp corex mode set production --phrase=PRODUCTION` fails naming
   `--phrase="GO LIVE ANYWAY"`; with that phrase it succeeds and writes the same history row.
7. The Setup Wizard's launch step asks for the phrase that applies.

---

### User Story 4 - The maintenance page carries the owner's message and a back-by time (US-OPS-06, P2)

**Acceptance Scenarios**:

1. **Given** Maintenance, a message and a back-by time today, **When** a signed-out visitor opens the site, **Then**
   503, the message, "Back by" the time in the site's format, the Brand contact email when one is set, and
   `Retry-After` the seconds until that time (at least 60).
2. **Given** the time has passed, **Then** the page says the work is taking longer than planned; `Retry-After: 600`.
3. **Given** no message and no time, **Then** the default text and `Retry-After: 3600`, as today.
4. Saving a back-by time in the past, or a message over 300 characters, is refused with the reason; nothing is saved.
5. **Given** a `wp-content/maintenance.php` CoreX did not write, **Then** the card says the site's own file is in use
   and visitors get that file's page.
6. **Given** two site languages, **When** the owner previews the page, **Then** both messages show, each in its own
   direction, and the mode is unchanged.

---

### User Story 5 - Every mode side by side, and Apply waits for its confirmation (US-OPS-07, P2)

**Acceptance Scenarios**:

1. Every mode is a card with its name, its tone in words, one sentence of what it does, and "Current mode" on the one
   in force; one column on a phone; mirrored right to left.
2. The cards are one radio group: arrow keys move between them, the selected card is announced, focus is visible.
3. **Given** Production selected and the phrase empty, **Then** Apply is disabled with "Type PRODUCTION to continue"
   (or the override phrase), and enabled once typed.
4. **Given** Maintenance selected and the box unticked, **Then** Apply is disabled with "Tick the box to continue".
5. **Given** scripts off, **When** Production is submitted without the phrase, **Then** the server refuses and the form
   returns with the phrase field shown.
6. **Given** an Editor, **When** they open the screen or post the mode form, **Then** the standard access-denied state;
   the mode is unchanged.
7. The order is Development, Staging, Production, Coming soon, Forms paused, Maintenance.

---

### User Story 6 - Hiding the login, hiding the admin and limiting attempts are three switches (US-SEC-01, P1)

**Acceptance Scenarios**:

1. **Given** only "Hide wp-login.php" on with the address `team-entry`, **Then** signed out `/wp-login.php` is not found,
   `/team-entry/` shows the sign-in form, and `/wp-admin/` redirects to sign-in.
2. **Given** only "Hide the admin from visitors who are not signed in", **Then** `/wp-admin/` is not found and
   `/wp-login.php` signs in.
3. **Given** only "Limit failed sign-ins", **Then** the sixth failed attempt at `/wp-login.php` is refused as locked.
4. **Given** "Hide wp-login.php" on and the address cleared, **When** saved, **Then** it is refused with the reason and
   nothing changes.
5. `admin-ajax.php`, `admin-post.php` and `options.php` stay reachable in every combination.
6. **Given** a site saved by the previous release with protection on and hiding on, **Then** all three switches read on
   with the same address and every address answers as before; protection on without hiding reads as limits on with its
   own address beside `wp-login.php`; protection off (whatever "hide" was stored as) reads as all three off.
7. The tab's status line, the Overview and the Operations Overview tab show each switch's real state. The recovery
   command turns all three off and releases lockouts; the recovery constant reopens `wp-login.php` whatever the first
   switch says.

---

### User Story 7 - Every login-protection setting can be seen and changed (US-SEC-02, P2)

**Acceptance Scenarios**:

1. **Given** defaults, **Then** the tab shows maximum failed attempts 5, counting window 5 minutes, lockout 15 minutes,
   no trusted proxies, keep sign-in records 30 days and record successful sign-ins on, with units, and a sentence
   saying what the limits mean together.
2. **Given** a 30-second window set from the command line, **When** only Max attempts is changed and saved, **Then** the
   window is still 30 seconds.
3. A lockout of 0 minutes, or `10.0.0.0/33` as a proxy, is refused with the range or the bad line named; nothing saved.
4. `203.0.113.0/24` as a trusted proxy is saved and shown, with the warning beside the field.
5. An Editor sending the settings request is refused.

---

### User Story 8 - A lockout can be released from the screen (US-SEC-03, P2)

**Acceptance Scenarios**:

1. **Given** limits on and five failures as the account `sara`, **Then** Lockouts names "sara", says when it ends and
   has Release.
2. **When** released, **Then** `sara` signs in at once from the same network; the activity log records who released
   whose lockout.
3. **Given** five failures as `nobody-here`, **Then** the lockout reads "unknown username" and a fingerprint; the typed
   name is not shown.
4. "Release all active lockouts" ends every active one and changes no other setting.
5. Releasing a lockout that has already ended says so and changes nothing.
6. An Editor calling the release route gets the standard access-denied answer.
7. The panel says a lockout blocks one username from one network, not the whole site.

---

### User Story 9 - Hardening speaks for the mode the site is in (US-SEC-07, P2)

**Acceptance Scenarios**:

1. **Given** Development, no HTTPS and debug display on, **When** Hardening opens, **Then** HTTPS, debug output and search
   engines are under "Before going live" with their state, and no text says "production site".
2. **Given** the same, **Then** the Overview and the Operations Overview tab leave those checks out of their counts.
3. **Given** Staging, **Then** search engines read "kept out by the mode for now" and are no warning.
4. **Given** Development without HTTPS, **When** Production is chosen, **Then** HTTPS is a blocker in the launch gate.
5. **Given** Production without HTTPS, **Then** the Overview counts it.

---

### User Story 10 - Visitors on a test site are told so, and a paused site says forms are paused (US-VIS-04, P2)

**Acceptance Scenarios**:

1. **Given** Staging and a flow on a page, **Then** a visitor sees above the form: "Test site: what you send here is kept
   for testing and no email is delivered."
2. **Given** Production, Coming soon or Maintenance (as an administrator), **Then** no notice.
3. **Given** Forms paused with a back-by time, **Then** the flow block shows the paused message with the time instead of
   the form; a crafted request gets 503, the paused message and no `errors`; nothing is stored or mailed.
4. **Given** Forms paused and an administrator, **Then** the flow is accepted; switching to Production reopens at once.
5. The notice and the paused panel read in light and dark and right to left and are announced once.

---

### User Story 11 - Sign-in identities stay unreadable wherever CoreX records them (US-SEC-10, P1)

**Acceptance Scenarios**:

1. **Given** a lockout with a username that belongs to no account, **Then** the notification says "unknown username"
   and a short fingerprint.
2. **Given** a lockout of an existing account, **Then** the notification names the account and shows no address.
3. **Given** the above, **When** the notifications, the attempt records, the activity log and the captured mail are
   searched for the typed username and the full address, **Then** neither is found.
4. **Given** two sites, **When** the same username fails once on each, **Then** the stored values differ.
5. **Given** a site with an active lockout and stored lockout notices from the previous release, **When** it updates,
   **Then** the lockout still holds until its end and the old notices follow the new rule.

### Edge Cases

- A lock (`APP_ENV`, the network, the `corex_app_env` option) fixes the environment: Forms paused, like Coming soon and
  Maintenance, still applies and survives the lock; mail follows the lock.
- A site in Forms paused whose visitor is an administrator gets the real form and no paused panel.
- A blocker that cannot be checked ("Could not check") never blocks and never asks for the override.
- A login settings save from an older screen that still sends `enabled` and `block_default_endpoints` is read in the
  old meaning, so nothing is hidden that the owner did not ask to hide.
- A saved site that later runs an older CoreX reads the same switches it wrote (the old keys are written too).
- A lockout recorded before the update has no account: it reads "unknown username" until it ends.

## Requirements *(mandatory)*

### Functional Requirements

**Modes (US-OPS-01, 02, 05, 06, 07)**

- **FR-001**: The mode model MUST describe, for every mode, four rows: Email, Form submissions, Search engines,
  Analytics, with the values of the story's table; the Email row MUST be the mail gate's own answer, "Locked by
  APP_ENV" (or the lock's name) when a lock decides; a row whose answer cannot be read MUST say "Could not check".
- **FR-002**: In Development and Staging every front-end response MUST carry `noindex` in the page (`wp_robots`) and an
  `X-Robots-Tag: noindex` header; no other mode adds either, and the Reading setting MUST NOT be changed.
- **FR-003**: "Forms paused" (`forms-paused`) MUST be a mode, ordered after Coming soon, needing an acknowledgement,
  delivering mail and allowing indexing like Production, and surviving a lock like Coming soon and Maintenance.
- **FR-004**: One gate MUST refuse every REST route that declares a public submission while the mode is Maintenance or
  Forms paused, before its handler runs, for everyone but a signed-in administrator, with HTTP 503, `Retry-After`, the
  mode's message and no `errors` object. No flow's own state changes.
- **FR-005**: Going live MUST need PRODUCTION when nothing blocks and "GO LIVE ANYWAY" when something does, evaluated
  at submit; the wrong phrase MUST be refused naming the blockers and the phrase; the phrases are never translated.
- **FR-006**: A launch MUST write a history row that says "Went live" or "Went live with N blockers overridden" and names
  each blocker; an override MUST also be written to the activity log as a security event.
- **FR-007**: The Environment tab MUST have a "Messages for visitors" card: per site language a message of up to 300
  characters and one "Back by" date and time (site time zone) for Maintenance, and the same for Forms paused; a time in
  the past or a message over the limit MUST be refused with the reason and nothing saved.
- **FR-008**: The maintenance page MUST show the message (or the default), the back-by time in the site's format, the
  Brand contact email when set; `Retry-After` MUST be the seconds until the back-by time (at least 60), 600 once it has
  passed (with "taking longer than planned"), and 3600 with no time.
- **FR-009**: A `wp-content/maintenance.php` that CoreX did not write MUST be what visitors get in Maintenance, and the
  card MUST say the site's own file is in use and the fields do not apply.
- **FR-010**: "Preview the maintenance page" MUST open the page as a visitor gets it without changing the mode, for
  people who may manage Operations & Security only.
- **FR-011**: The mode chooser MUST be one radio group of cards (name, tone in words, one sentence, "Current mode"),
  and Apply MUST stay disabled, with the reason beside it, until the selected mode's confirmation is given; with
  scripts off the server MUST refuse a missing confirmation as before.
- **FR-012**: The command line MUST follow the same rules and write the same history rows.

**Login protection (US-SEC-01, 02, 03, 07, 10)**

- **FR-013**: Login protection MUST be three independent switches: hide `wp-login.php`, hide the admin from signed-out
  visitors, limit failed sign-ins; the own login address MUST work whenever any switch is on; hiding `wp-login.php`
  MUST need a valid address.
- **FR-014**: Settings stored by the previous release MUST be read with their old meaning without being rewritten;
  a save MUST write the new keys and the old ones.
- **FR-015**: The tab MUST show and save maximum attempts, counting window (minutes), lockout length (minutes), trusted
  proxies (one per line, each a valid IPv4/IPv6 address or range), retention (days) and recording of successful
  sign-ins; a save MUST change only the fields that were changed, MUST refuse an out-of-range value with the range and
  MUST never rewrite a value it was not given.
- **FR-016**: A lockout of a username that belongs to an account MUST record and show that account; others MUST show
  "unknown username" and a short fingerprint.
- **FR-017**: A lockout, or every active lockout, MUST be releasable from the screen, at once, recorded in the
  activity log, for people who may manage Operations & Security only; a lockout that has already ended MUST be
  reported as such and change nothing.
- **FR-018**: In Development and Staging, HTTPS, debug output and search engines MUST be shown under "Before going
  live" with their state and left out of the counts on the Overview, the Operations Overview tab and Insights; the
  launch gate MUST still evaluate every check; no check text may call the site "production" outside Production.
- **FR-019**: The values stored for a sign-in attempt MUST be keyed with a key specific to the site; attempts stored
  before the update MUST keep counting until they age out.
- **FR-020**: Nothing CoreX shows, sends or stores about a sign-in attempt (notifications, attempt records, activity log,
  mail) MAY contain the typed username or a full IP address; lockout notices stored before the update MUST be rewritten
  to the same rule once, on update.

**Visitor forms (US-VIS-04)**

- **FR-021**: In Development and Staging every flow block MUST show the test-site notice above the form; in Forms paused
  it MUST show the paused message (and back-by time) instead of the form; neither in any other mode, and the paused
  panel never to an administrator.

### Key Entities

- **Mode guardrails**: four rows per mode, each a label, a value in words and a tone.
- **Visitor messages**: per mode (Maintenance, Forms paused) a message per language and an optional back-by time.
- **Login protection settings**: three switches, the address, the limits, trusted proxies, retention, success logging.
- **Lockout**: a sign-in attempt record with an end time, its account when known, a fingerprint.
- **Launch history row**: from, to, who, when, and the blockers overridden.

## Success Criteria *(mandatory)*

- **SC-001**: In each of the six modes every guardrail row matches measured behaviour (mail, a form post, the robots
  header) in an automated test.
- **SC-002**: A site that saved login protection on the previous release answers at `/wp-login.php`, `/wp-admin/` and its
  own address exactly as before after the update, proven with the stored option shape of both client sites.
- **SC-003**: No typed username and no full IP address of a failed sign-in can be found in any CoreX store or captured
  mail after a lockout.
- **SC-004**: A locked-out colleague can sign in again within one click of an administrator, with no command line.
- **SC-005**: An operator cannot press Apply before giving the confirmation the selected mode needs.

## Assumptions

- Spec 110's branded maintenance renderer is not on `main`; the page keeps today's renderer and gains the message,
  time, contact line and timing. When spec 110 lands it renders the same content.
- CoreX collects no analytics, so the Analytics row says so in every mode.
- The Forms paused notice uses the same panel markup as the closed-flow notice of spec 122 when that lands.
- Release from the screen is gated by the screen's own capability on `main` (`manage_options`); spec 117 moves it to
  its ability when it lands.
- Muva, Perego and Mizzey are not touched; their sessions are told what changes (Client impact in the changelog).
