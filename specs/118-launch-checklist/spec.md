# Feature Specification: One launch checklist and an honest Overview

**Feature Branch**: `claude/spec-118-launch-checklist`

**Created**: 2026-10-23

**Status**: Draft

**Input**: Release R3.2 of the CoreX revamp, thirteen stories approved under the owner's 2026-10-21 mandate:
US-OVR-01 to US-OVR-09, US-WIZ-06, US-SEC-09, US-INS-02 and US-SHL-09. The stories, their test cases and the
reading of the code behind them are kept in the owner's private planning workspace. It builds on spec 114 (R0.3,
"Screens that tell the truth").

## Why this spec exists

CoreX tells a site owner how ready the site is in four places, and they disagree. The Overview counts "3 of 6" from
its own list, Operations & Security counts "4 blockers" from another, the readiness notifications come from a third
evaluation that publishes the same notice again each time a page is opened, and the Setup Wizard's launch step points
at the other screens while an unused fourth checklist sits in the kit add-on. The Overview itself opens with post and
page counts instead of the site's state, a card whose data cannot be read shows 0, recent activity is written in
internal keys, the data card lists the activity log next to the owner's leads, and a kit applied from the wizard is
never recorded as applied. This release makes **one readiness model** that every screen reads, and an Overview whose
every card says what is so.

## Clarifications

### Session 2026-10-23 (decided under the owner's 2026-10-21 mandate)

The owner delegated the revamp and asked not to be consulted. Where a story leaves a choice open, the planning
workspace's recommended answer is taken and recorded as decided under that mandate.

- Q: Which readiness list is the real one? → A: **One shared model that all screens read** (OVR-D1).
- Q: Hardening checks, site checks, or both? → A: **Both, in two groups**: "Security and hosting" and "Site and
  content" (OPS-D4).
- Q: Do the new site and content checks block Production? → A: **Advise only.** Only "Security and hosting" checks can
  block, as they do today; "Could not check" never blocks (OVR-D4).
- Q: Overview tiles: counts or state? → A: **State tiles**: Site health, Environment, Login protection, Add-ons, plus
  the Attention card (OVR-D2).
- Q: Should CoreX record PHP fatal errors? → A: **Yes, in corex-core**: time, type and the plugin or theme only, kept
  30 days, nothing written on a request without one, the source shown only to administrators (OVR-D3).
- Q: The Overview says Done, Pending and Blocked (US-OVR-02); Operations says Ready and "Blocks going live"
  (US-SEC-09). Are they one vocabulary? → A: **One set of states, two voices.** Every screen shows the same state for
  the same check. The Overview, a to-do list, words them Done, Pending, Blocked, Could not check; the screens that
  decide going live (Operations, the wizard's launch step, the Dashboard widget) word them Ready, Pending, Blocks
  going live, Could not check. Both voices come from one place.
- Q: Which "Unread" does the forms card count? → A: **Unread among the open submissions** (new or in progress), so the
  three numbers all describe open work, and the inbox filter it links to is the same.
- Q: Which notifications are grouped? → A: **A fixed list of informational kinds**: finished exports and failed jobs,
  grouped by what was exported or run. Anything that asks for a decision (access requests, assignments, failed email
  to a person, readiness blockers) is never grouped.
- Q: Where is the terms page chosen? → A: **A "Terms page" choice on Settings > Brand**, listing the site's pages. The
  Company kit creates no terms page today, so no kit apply sets it.
- Q: Which test email counts? → A: **A test sent from Email Studio whose attempt was accepted by the mail transport
  ("sent" or "opened") in the last 90 days.** A captured test never counts.
- Q: What does the "Go live" of the wizard's launch step submit? → A: **The Operations screen's own mode form**, to the
  same handler, so it asks the same confirmation and writes the same history row; it returns to the wizard.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - One launch checklist that every screen reads (US-OVR-01, Priority: P1)

A site owner preparing to launch reads the same checks, with the same states, on the Overview, Operations & Security,
the readiness notifications, the WordPress Dashboard's CoreX widget and the Setup Wizard's launch step.

**Why this priority**: an owner told "3 of 6" on one screen and "4 blockers" on the next trusts neither.

**Independent Test**: turn debug display on and file editing on; open the five places; turn debug display off and open
them again.

**Acceptance Scenarios**:

1. **Given** debug display on and file editing allowed, **When** the owner opens the Overview, Operations & Security,
   Notifications and the WordPress Dashboard, **Then** each shows the same checks with the same states and the
   Overview's count equals the Operations count.
2. **Given** the same, **When** Operations & Security is opened ten times, **Then** there is one notification per
   failing check and its repeat count did not grow.
3. **Given** the same, **When** debug display is turned off, **Then** the debug check passes on every screen, its
   notification is resolved, and both counts move together.
4. **Given** a failing check, **When** its notification is opened, **Then** "Fix this check" opens where it is fixed.
5. **Given** `show_on_front` = posts and a theme with its own front-page template, **Then** "Front page" is Done.
6. **Given** no kit add-on is active, **Then** there is no kit row; **given** the Company kit is active, there is one.
7. **Given** the hardening facts cannot be read, **Then** those checks read "Could not check" on every screen, are not
   counted as done, and do not block.

---

### User Story 2 - Each readiness row says Done, Pending or Blocked and links to its fix (US-OVR-02, P2)

**Acceptance Scenarios**:

1. **Given** HTTPS off, no terms page and a brand set, **When** the Overview opens, **Then** HTTPS reads "Blocked",
   the terms row "Pending" and the brand row "Done", each with an icon and a word.
2. An open row's second line says what is missing ("No static front page or front-page template").
3. Each open row is a link to its fix; a done row is not a link.
4. The header says "N of M" with plural forms and a progress bar whose value is announced.
5. With the Setup Wizard available the header links to it; without it there is no link.
6. At 1280px the card is as tall as its rows; there is no empty band below the last row.
7. Chips meet 4.5:1 text contrast in both themes and read correctly right to left.

---

### User Story 3 - The launch checklist checks the site, not only the server (US-OVR-03, P1)

**Acceptance Scenarios**:

1. The checklist has two groups: "Security and hosting" (the six hardening checks) and "Site and content".
2. "Site and content" holds Brand set, Kit applied (only with a kit add-on), Front page, Privacy page published, Terms
   page published, Test email sent and Login protection on.
3. **Given** the privacy page is chosen but a draft, **Then** "Privacy page published" is Pending and links to the
   page. **Given** no terms page is chosen, **Then** the row says "Choose a terms page" and links to the setting.
4. **Given** a test from Email Studio accepted by the transport in production, **Then** "Test email sent" is Done;
   **given** a captured test on a development site, it stays Pending; **given** Corex Mail is off, the row reads
   "Needs Corex Mail" and is left out of the count.
5. **Given** the hardening checks pass and every site check is Pending, **When** the owner switches to Production with
   the normal phrase, **Then** the switch succeeds and no override is recorded.
6. **Given** login protection off, then on, **Then** the row is Pending, then Done.
7. Both groups appear on the Overview and on Operations (Operations leads with "Security and hosting").

---

### User Story 4 - The Overview opens with the site's state (US-OVR-04, P2)

**Acceptance Scenarios**:

1. Four tiles replace the counts: Site health, Environment, Login protection, Add-ons, each with a state word, an icon
   and one line of detail; no post or page counts.
2. **Given** the uploads folder is not writable, **Then** Site health names it and links to WordPress's Site Health.
3. **Given** a theme without `brand.json`, **Then** Site health reads "Healthy".
4. **Given** a plugin that caused a PHP fatal error, **Then** an administrator reads its date and the plugin's name,
   and someone else with the Overview's ability reads the date only.
5. The stored record holds time, type and plugin or theme only; a request without a fatal error writes nothing.
6. **Given** the mode Development and `WP_ENVIRONMENT_TYPE` production, **Then** the Environment tile shows both, in
   Operations' words.
7. **Given** the health report cannot be read, **Then** Site health reads "Could not check" and the other tiles render.

---

### User Story 5 - The forms card counts open, assigned and unread submissions (US-OVR-05, P2)

**Acceptance Scenarios**:

1. **Given** 10 submissions (4 new, 2 in progress, 1 closed, 1 spam, 1 test, 1 trashed; 2 new assigned to the viewer;
   3 unread among the open), **Then** the card reads Open 6, Assigned to you 2, Unread 3.
2. Each number opens the inbox with the matching filter, and the inbox shows the same count.
3. A person who may not open the inbox sees no numbers and a sentence saying why.
4. With the forms add-on off: "Forms are off" with a link to Add-ons; with forms on and none received: "No
   submissions yet".
5. Labels have plural forms; numbers use the site's locale.

---

### User Story 6 - A card that cannot load says so (US-OVR-06, P1)

**Acceptance Scenarios**:

1. **Given** any one card's source throws, **Then** that card says "Could not load" with "Try again", never 0 or an
   empty state, and every other card renders.
2. **Given** the submissions count throws with forms on, **Then** "Could not load", not "Forms add-on inactive".
3. **Given** one data source throws, **Then** it is listed with "Could not load".
4. **Given** the activity service or the notification service throws, **Then** only its card fails.
5. An administrator reads a short reference on the failed card, and the same reference is in the PHP error log.
6. The same rule holds for Insights' forms counts.

---

### User Story 7 - Recent activity reads as sentences (US-OVR-07, P2)

**Acceptance Scenarios**:

1. **Given** the administrator "Mustafa" trashes 25 submissions, **Then** the card reads "Mustafa moved 25
   submissions to the trash", linking to the trash.
2. An event of a kind with no sentence reads "Something changed in <area>"; no dotted key appears.
3. A deleted acting user reads "A deleted user".
4. The Insights security card and the Access audit log read the same sentences from the same source.
5. No "Audit log" link points at a list of refusals only; the card has no link until a list of all activity exists.
6. Sentences are translated with plural forms; times use the admin date format.

---

### User Story 8 - The data sources card lists the owner's data (US-OVR-08, P3)

**Acceptance Scenarios**:

1. The card lists only sources marked as the site's records (form submissions, newsletter subscribers, job
   applications, and any an add-on or the site's code marks so); logs and system tables are not listed.
2. Each row shows the name, the count and "Can edit" or "Read only", and opens that source on the Data screen.
3. A footnote says system records are on the Data screen; the card's link opens it with no redirect.
4. A source whose add-on is off is not listed; with no owner data the card says what will create some.

---

### User Story 9 - Applying a kit from the wizard shows on the Overview (US-OVR-09, P1)

**Acceptance Scenarios**:

1. **Given** the Company kit applied from the Setup Wizard, **Then** the kit row is Done and Add-ons shows the kit
   applied.
2. **Given** a site where the wizard applied the kit before this release, **Then** the kit row is Done from the pages
   that carry the kit's record, and nothing is applied again.
3. **Given** an apply that fails part-way, **Then** the kit is not marked applied.
4. **Given** a kit reset, **Then** the kit row is Pending again.
5. The wizard and the Add-ons prompt give the same result.

---

### User Story 10 - The wizard ends on the same launch checklist (US-WIZ-06, P2)

**Acceptance Scenarios**:

1. The launch step shows the one checklist with its two groups, each item with its state in words and a link to fix
   it, the same as Operations and the Overview.
2. "Go live" is offered when the site is not in Production and asks the same confirmation as the Operations screen,
   through the same handler, writing the same history row.
3. The Done step says what is left ("2 checks still block going live") or that the site is live; never "ready" while
   something blocks.
4. If the checks cannot be read, the step says so with Retry and offers no "Go live".

---

### User Story 11 - The launch checklist says each state in words (US-SEC-09, P1)

**Acceptance Scenarios**:

1. **Given** two of six hardening checks failing, **Then** Environment & Maintenance shows rows saying "Ready" or
   "Blocks going live" beside an icon, and the header "4 of 6 ready".
2. In greyscale each state can still be told apart; a screen reader reads each row's state with its name.
3. Plural forms are correct (Arabic included); a failing row links to its fix.
4. The same check has the same state and words on the Environment and Hardening tabs and in the wizard's launch step.

---

### User Story 12 - Each Insights widget's state looks different (US-INS-02, P2)

**Acceptance Scenarios**:

1. Live, Connected, No data yet, Not connected, Needs attention and Could not load each have their own chip: an icon,
   a word and a tone.
2. The chip is a shared admin style; Add-ons, Overview integrations and Settings look as they did.
3. Chip text meets 4.5:1 and its edge 3:1 in dark and light; greyscale still tells states apart.
4. Each widget header has an icon for its subject, hidden from screen readers.
5. The screen is titled "Insights"; the agent card "AI and agent readiness".
6. A failed widgets request shows a "Could not load" panel with "Try again", announced.

---

### User Story 13 - Notifications group repeated items of the same kind (US-SHL-09, P2)

**Acceptance Scenarios**:

1. **Given** three finished exports of submissions and one of a data model, **When** the bell opens, **Then** it shows
   "3 exports finished" and one single item, with the latest time.
2. The bell count and the Overview's Attention card count each group once.
3. A group lists its members, each with its own link.
4. Marking a group read marks all its members; a new export afterwards starts a new group.
5. Two pending access requests are two items.
6. Grouping changes no stored row; retention treats each as before.
7. Counts use plural forms, Arabic included, and read right to left.

---

### Edge Cases

- A check whose facts cannot be read is "Could not check": shown, never counted done, never blocking.
- "Needs Corex Mail" is neither done nor open: it is left out of "N of M".
- A site in Production still sees the checklist; the wizard offers no "Go live".
- A theme without a front-page template and `show_on_front` = posts: Front page is Pending.
- A readiness notification resolved by hand while the check still fails is not raised again until the check has passed
  and failed again.
- A fatal error recorded at shutdown that WordPress also handled: one record, written after WordPress's handler.
- A notification group whose members the viewer may only partly see counts and lists only what the viewer may see.

## Requirements *(mandatory)*

### Functional Requirements

**The one model (US-OVR-01, US-OVR-03, US-SEC-09)**

- **FR-001**: There MUST be one readiness evaluation. The Overview, every tab of Operations & Security, the readiness
  notifications, the WordPress Dashboard widget and the Setup Wizard's launch step MUST read it and show the same
  state for each check.
- **FR-002**: Each check MUST have a group ("Security and hosting" or "Site and content"), a state (done, pending,
  blocked, could not check, or not applicable), the text of what is missing when open, and the place it is fixed.
- **FR-003**: "Security and hosting" MUST hold the six hardening checks. A failing hardening check MUST be blocked and
  block Production, as today. A check that cannot be read MUST be "Could not check" and MUST NOT block.
- **FR-004**: "Site and content" MUST hold: Brand set (company name and admin logo), Kit applied (only while a kit
  add-on is active), Front page (a static front page is chosen, or the active theme has a front-page template),
  Privacy page published, Terms page published, Test email sent, Login protection on. These MUST be pending when not
  met and MUST NOT block Production.
- **FR-005**: Terms page published MUST read the "Terms page" setting; with none chosen the row MUST say "Choose a
  terms page" and link to the setting.
- **FR-006**: Test email sent MUST pass on an Email Studio test whose attempt was sent or opened in the last 90 days; a
  captured test MUST NOT count; with Corex Mail inactive the row MUST read "Needs Corex Mail" and be left out of the
  count.
- **FR-007**: The count MUST be "N of M" over every check that is not "not applicable", N being the done ones, with
  plural forms, in the same numbers on the Overview, Operations and the wizard.
- **FR-008**: The words MUST come from one place: Done / Pending / Blocked / Could not check on the Overview; Ready /
  Pending / Blocks going live / Could not check on Operations, the wizard's launch step and the Dashboard widget.
  Each row MUST carry an icon and its state word inside its accessible text; nothing MUST depend on colour.
- **FR-009**: A blocked check MUST raise one notification, carrying "Fix this check". Evaluating readiness again MUST
  NOT publish it again or raise its repeat count while it stays blocked; it MUST be resolved when the check passes and
  raised again only when it fails again.
- **FR-010**: The kit add-on's unused checklist MUST be removed.

**Overview (US-OVR-02, 04 to 08)**

- **FR-011**: The readiness card MUST show both groups, each row with its chip; open rows link to their fix, done rows
  do not; its header shows "N of M", a progress bar with its value announced, and a link to the Setup Wizard only
  while it is available.
- **FR-012**: The first row MUST be four tiles: Site health, Environment, Login protection, Add-ons, each with a state
  word, an icon and one line of detail. A tile whose fact cannot be read MUST say "Could not check".
- **FR-013**: Site health MUST read CoreX's health checks (PHP and WordPress below CoreX's minimum, uploads not
  writable, block assets with a file path), ignore a missing `brand.json`, link to WordPress's Site Health, and say
  "No PHP fatal errors in the last 7 days" or "Last PHP fatal error: <date>" with ", in <plugin or theme>" for
  administrators.
- **FR-014**: CoreX MUST record a PHP fatal error's time, type and the plugin or theme its file belongs to, and nothing
  else; keep 30 days; write nothing on a request without one.
- **FR-015**: Environment MUST show the CoreX mode and, when it differs, the WordPress environment type, in the
  Operations screen's words. Login protection MUST read the Security Center and link to it. Add-ons MUST show active
  of installed and link to Add-ons.
- **FR-016**: The forms card MUST show Open (new or in progress), Assigned to you (open and owned by the viewer) and
  Unread (open and not read), excluding tests, trash and spam; each MUST link to the inbox with that filter; the inbox
  MUST accept the filters from its address. Without inbox access: no numbers and a sentence why. Forms add-on off:
  "Forms are off" linking to Add-ons. Nothing received: "No submissions yet".
- **FR-017**: Each card MUST be rendered on its own: a failure shows "Could not load" with "Try again" (a reload) and,
  for an administrator, a reference that is also written to the PHP error log. A failed card MUST NOT show 0, an
  empty state or another cause. A failing data source MUST be listed with "Could not load".
- **FR-018**: Activity MUST read as sentences with the person's display name ("A deleted user" when gone), from one
  source the Insights security card and the Access audit log also use; a kind with no sentence MUST read "Something
  changed in <area>"; a row MUST link to its object when it still exists; the card MUST have no link to a list until
  a list of all activity exists.
- **FR-019**: The data card MUST list only sources in the records group, with count and "Can edit" or "Read only",
  each linking to that source on the Data screen, a footnote naming the Data screen for system records, and a link
  to the Data screen that needs no redirect. With none, it MUST say what creates owner data.
- **FR-020**: The Insights forms counts MUST say "Could not load" when their source fails, never 0.

**Kit and wizard (US-OVR-09, US-WIZ-06)**

- **FR-021**: An apply from the Setup Wizard MUST mark the kit applied exactly as the Add-ons prompt does, only when
  every page it was to create or fill was written. A site whose pages carry the kit's record MUST be read as applied
  without applying again. A reset (which removes those records and CoreX's options) MUST leave it not applied.
- **FR-022**: The wizard's launch step MUST show the one checklist in its two groups; offer "Go live" only when the
  site is not in Production and the checklist was read; submit the Operations mode form to its handler with the
  phrase field, returning to the wizard; and say so with Retry when the checklist cannot be read.
- **FR-023**: The Done step MUST say how many checks still block going live, or that the site is live, and never that
  it is ready while something blocks.

**Insights and notifications (US-INS-02, US-SHL-09)**

- **FR-024**: Insights widget chips MUST be a shared admin style with a distinct icon, word and tone per state; text
  4.5:1 and edge 3:1 in both themes; existing `.corex-badge` chips elsewhere MUST look as before.
- **FR-025**: Each widget header MUST carry a decorative subject icon; the screen MUST be titled "Insights" and the
  agent card "AI and agent readiness".
- **FR-026**: Unread finished-export and failed-job notifications of the same kind of job MUST be shown as one item
  with a count in its title and the latest time; its members listed each with its link; the bell and the Attention
  card count it once; marking it read, dismissing it or resolving it applies to every member.
- **FR-027**: Access requests, assignments, failed email to a person and readiness blockers MUST never be grouped.
  Grouping MUST NOT change any stored notification.

### Key Entities

- **Readiness check**: key, group, label, state, what is missing, where it is fixed, when it was checked.
- **Readiness snapshot**: every check at one moment; what blocks Production; the hash a launch confirmation binds to.
- **PHP fatal error record**: time, error type, source kind (plugin, must-use plugin, theme, WordPress, unknown) and
  source slug.
- **Notification group**: a group key, its unread members, the latest member's time.

## Success Criteria *(mandatory)*

- **SC-001**: On any site, the Overview's "N of M", the Operations count and the wizard's count are the same numbers
  (0 disagreements across the five places of story 1).
- **SC-002**: Opening Operations & Security ten times with a failing check leaves exactly one notification with an
  unchanged repeat count.
- **SC-003**: With every source of every Overview card made to fail in turn, the page renders every time and the failed
  card never shows a number.
- **SC-004**: A request without a fatal error writes 0 options.
- **SC-005**: No dotted internal key appears on the Overview's activity card, the Insights security card or the Access
  audit log.
- **SC-006**: A site that can switch to Production today still can after the upgrade with the same phrase.

## Client impact

Muva, Perego and Mizzey owners see a new Overview (four state tiles, a two-group checklist, sentences in recent
activity, owner data only) and the same checklist on Operations & Security and the Dashboard widget. Muva runs without
a kit: no kit row counts against it. Perego has no Corex Mail: its test email row reads "Needs Corex Mail" and is not
counted. Nothing new blocks going live. Readiness notifications already open stay open once and stop growing their
repeat count; repeated export and job notifications are grouped, none removed. A new "Terms page" choice appears on
Settings > Brand. Client-guide screenshots of the Overview, Operations and Insights go stale.

## Assumptions

- The hardening checks keep today's pass/blocked behaviour; their severity (Action / Review) arrives with US-SEC-06,
  and the override phrase with US-OPS-05. Both change the one gate, so every screen follows.
- The rail count of US-SHL-08 is not built yet; when it is, it reads the same grouped count.
- Spec 114's Overview rows "Spam protection", "Transactional email configured" and "Hardening checks" are replaced by
  the full checklist; the Captcha, Transactional email and Login protection integration rows stay.
