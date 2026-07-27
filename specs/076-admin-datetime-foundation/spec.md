# Feature Specification: CoreX Admin Date & Time Foundation

**Feature Branch**: `spec/076-admin-datetime-foundation`

**Created**: 2026-07-27

**Status**: Draft

**Input**: User description: "CoreX Admin Date & Time Foundation — one shared PHP + JS date/time formatting contract for the whole CoreX admin. English full format `1 August 2026 at 10:20 PM`; WordPress site timezone as the single source of truth; active WordPress locale; semantic `<time>` markup; relative time with an exact value that is not hover-only; truthful fallbacks for missing/invalid values; table sorting kept on the machine value; storage and REST stay canonical UTC ISO 8601."

## Why this spec exists

Every CoreX admin screen that shows *when* something happened tells the operator something
different, and several tell them something wrong. This was measured against the running install
before the spec was written, not assumed:

- **No shared formatter exists** in either language. There is no `AdminDateTimeFormatter`,
  `DateTimePresenter`, or JS equivalent anywhere in `plugins/`, `packages/`, or `addons/`.
- **Six admin surfaces render dates in the reader's browser timezone and browser locale** via
  `new Date( value ).toLocaleString()` — Blog Pro moderation, three Email Studio panels, and the
  Forms flow list. A submission that arrived at 09:00 site time shows a different hour to a
  colleague in another country, and neither of them is told which zone they are reading.
- **Six more print the stored ISO string straight to the screen** — the Submission Inbox date
  column, the submission drawer, the submission audit timeline, the security activity list, and the
  notification item's `datetime`/`title` pair. The operator reads `2026-07-27T01:30:24+00:00`.
- **The three PHP surfaces that do format correctly disagree with each other in presentation.**
  All three use `wp_date()`, so the timezone is right, but each builds its format from
  `get_option('date_format') . ' ' . get_option('time_format')` — so output changes per site, has no
  "at" separator, and is not guaranteed to be a 12-hour clock. One of them
  (`OperationsSecurityScreen.php:332`) falls back to `$date->format('c')` — raw ISO — when
  `wp_date()` returns an empty string.
- **The one relative-time surface hides its exact value behind a hover `title`**, which is not
  reachable by touch and not reliably announced.

Storage and transport are already correct — `gmdate( DATE_ATOM )` throughout persistence and REST —
so this is a presentation-layer problem with a presentation-layer fix.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - An operator reads a date and knows what it means (Priority: P1)

An operator opens any CoreX admin screen and sees when something happened, written the way a person
writes it, in the site's own timezone, in their own language. They never see a machine timestamp, a
half-formatted string, or an hour that silently belongs to some other part of the world.

**Why this priority**: This is the whole point. Every other story is a refinement of it, and it is
the only one that removes a class of *wrong* information rather than merely awkward information.
Two operators in different countries currently read different times for the same event.

**Independent Test**: Set the site timezone to `Africa/Cairo`, create a submission, and open the
Submission Inbox with the browser's own timezone forced to something else. The listed time matches
the site's wall clock and reads `1 August 2026 at 10:20 PM`, not an ISO string and not the browser's
hour.

**Acceptance Scenarios**:

1. **Given** the site timezone is `Africa/Cairo` and a record is stored as `2026-08-01T19:20:00Z`,
   **When** the operator views any admin surface showing that record's date,
   **Then** it reads `1 August 2026 at 10:20 PM` — day without a leading zero, full month name,
   four-digit year, a localized "at", a 12-hour clock with minutes and a localized AM/PM, and no
   seconds.
2. **Given** the operator's browser is set to a different timezone from the site,
   **When** they view the same record,
   **Then** the time shown is unchanged, because the site timezone is the only source of truth.
3. **Given** a surface rendered by the server and a surface rendered in the browser show the same
   record, **When** both are read, **Then** they show character-identical text.
4. **Given** the active locale is Arabic, **When** any date is shown, **Then** the month name, the
   period marker, and the connector are Arabic, the text is ordered correctly in RTL, and the
   English word "at" appears nowhere.

---

### User Story 2 - A date can be inspected precisely, and machines can read it too (Priority: P2)

An operator investigating an incident needs the exact moment, to the second, and needs to compare
two records that happened close together. Assistive technology needs to announce the date correctly,
and a table of dates needs to sort chronologically.

**Why this priority**: It builds on P1 and is what makes the human format safe to adopt. Without it,
replacing ISO strings with prose would *remove* capability — precision and sortability — which is
worse than the defect being fixed.

**Independent Test**: Sort the Submission Inbox by date with records spanning a month boundary and
confirm chronological order; inspect any rendered date and confirm it carries a valid
machine-readable value alongside the human text.

**Acceptance Scenarios**:

1. **Given** any visible date, **When** the markup is inspected, **Then** it is a semantic time
   element whose machine-readable attribute holds a valid ISO 8601 value and whose visible text is
   the human form.
2. **Given** a table column of dates spanning a month and a year boundary, **When** it is sorted,
   **Then** rows order chronologically and never alphabetically by month name.
3. **Given** a relative time such as "2 hours ago", **When** it is read by an operator on a touch
   device or by a screen reader, **Then** the exact date is available without hovering.
4. **Given** a technical diagnostic view where the second matters, **When** a date is shown there,
   **Then** seconds are included; everywhere else they are omitted.

---

### User Story 3 - A missing or broken date says so honestly (Priority: P3)

A record with no timestamp, or a corrupt one, tells the operator plainly that there is nothing to
show — in words that fit the field's meaning — instead of inventing a date or breaking the layout.

**Why this priority**: Lower frequency, but it is where the current code fails most visibly when it
fails at all, and it is cheap to get right once the formatter exists.

**Independent Test**: Render a record whose timestamp is empty, and one whose timestamp is
malformed; confirm neither produces `Invalid Date`, `NaN`, `01 January 1970`, the current time, or
an empty cell that collapses the row.

**Acceptance Scenarios**:

1. **Given** a record with no timestamp, **When** its date is rendered, **Then** the surface shows a
   truthful phrase appropriate to that field's meaning — "Not recorded", "Never", "No expiry",
   "Pending" — and never a fabricated date.
2. **Given** a malformed timestamp, **When** its date is rendered, **Then** the surface shows the
   same kind of truthful phrase, the layout is unaffected, and the problem is recorded where
   developers can find it.
3. **Given** a missing date in a sortable column, **When** the column is sorted, **Then** missing
   values group consistently at one end rather than scattering.

---

### Edge Cases

- **Midnight and noon.** `12:00 AM` and `12:00 PM` — never `0:00 AM` and never a 24-hour `12:00`
  that reads as either.
- **Month, year, and leap-day boundaries.** `31 December` → `1 January` across a year change, and
  `29 February 2028`.
- **Daylight-saving transitions.** A timestamp inside a spring-forward gap or an autumn-fallback
  overlap resolves to a single defensible wall-clock time and never throws.
- **A site configured with a raw UTC offset** (`UTC+3`) rather than a named zone, which WordPress
  permits and which has no DST rules to apply.
- **A site genuinely configured for UTC**, where UTC display is correct rather than a fallback.
- **Legacy values**: a Unix integer, an ISO string ending `Z`, and an ISO string carrying an offset
  must all be accepted and produce the same instant.
- **The current second.** "0 seconds ago" and a timestamp a few seconds in the future (clock skew
  between server and browser) must not produce a negative or nonsensical relative phrase.
- **Very old and very distant dates**, where a relative phrase stops being useful and the absolute
  date should be shown instead.
- **Narrow viewports and 200% zoom**, where the longest localized month name must not overflow its
  column or force the document to scroll sideways.

## Requirements *(mandatory)*

### Functional Requirements

**The shared contract**

- **FR-001**: A single date/time presentation contract MUST exist, implemented once for the server
  and once for the browser, and both implementations MUST produce identical output for the same
  instant, locale, and site timezone.
- **FR-002**: The contract MUST offer exactly these presentations: full date and time, date only,
  time only, relative time, and the exact machine-readable value. Any surface needing a date uses
  one of these; no surface composes its own.
- **FR-003**: The English full presentation MUST be `1 August 2026 at 10:20 PM` — day with no
  leading zero, full month name, four-digit year, a connector, a 12-hour clock, minutes, and a
  period marker, with no seconds.
- **FR-004**: Seconds MUST be available as a distinct technical presentation and MUST NOT appear in
  ordinary operator-facing views.

**Timezone**

- **FR-005**: The WordPress site timezone MUST be the only source of truth for what wall-clock time
  a stored instant is displayed as.
- **FR-006**: The contract MUST handle a named timezone, a bare UTC-offset configuration, and
  daylight-saving transitions, and MUST accept instants stored as UTC, as an offset-bearing string,
  or as a Unix integer.
- **FR-007**: The server's timezone and the browser's timezone MUST NOT influence displayed values.
  UTC MUST NOT be displayed unless the site is configured for UTC.
- **FR-008**: The site timezone and formatting configuration MUST reach the browser through one
  shared admin configuration boundary. No screen may carry its own copy.

**Locale**

- **FR-009**: The active WordPress locale MUST determine month names, period markers, and the
  connector.
- **FR-010**: No user-visible date text may be assembled from hardcoded English fragments. The
  connector equivalent to "at" MUST be translatable as part of a whole, translatable pattern rather
  than concatenated.
- **FR-011**: Output MUST remain correctly ordered and readable in RTL.

**Markup and precision**

- **FR-012**: Every visible date MUST be rendered as a semantic time element carrying a valid
  machine-readable value, with the human form as its visible text.
- **FR-013**: A relative presentation MUST make its exact date available by a route that does not
  require hovering — accessible text, a visible secondary line, or an equivalent.

**Storage, transport, and sorting**

- **FR-014**: Persistence formats MUST NOT change. Canonical machine timestamps remain canonical.
- **FR-015**: API responses MUST continue to carry the canonical machine value. A server-formatted
  display value MAY be added alongside it, but MUST NOT replace it.
- **FR-016**: Where dates appear in a sortable table, sorting MUST use the machine value and produce
  chronological order; missing values MUST group consistently.
- **FR-017**: Dates inside machine artifacts — JSON, CSV exports, debug reports, API payloads, log
  files — MUST remain machine-readable unless that artifact's contract explicitly calls for a human
  column.

**Absent and invalid values**

- **FR-018**: A missing or unparseable value MUST render a truthful phrase chosen for that field's
  meaning, and MUST NEVER render `Invalid Date`, `NaN`, the Unix epoch, the current time, raw
  malformed input, or a blank that breaks the layout.
- **FR-019**: Malformed stored timestamps MUST be recorded in developer-facing diagnostics without
  exposing anything sensitive.

**Coverage**

- **FR-020**: Every CoreX admin surface that displays a date MUST use the shared contract. The
  audited set is: Overview, Add-ons, Forms & Flows, Submission Inbox and its detail drawer and audit
  timeline, Exports, Data Models, Imports, Migrations, Email Studio and its logs and events,
  Operations & Security including mode-change history and login lockouts and security activity,
  Notifications and notification details, Access & Abilities including access requests and the
  access audit, Blog Pro including editorial history and comments and analytics, Insights, the Setup
  Wizard, Settings, media jobs, and retention.
- **FR-021**: After this spec, no CoreX admin surface may render a raw ISO string, a bare Unix
  integer, or a browser-locale-formatted date as operator-facing text.

### Key Entities

- **Displayable instant**: a moment in time drawn from storage or an API response, carrying its
  canonical machine value and knowing whether it is present, absent, or unparseable.
- **Presentation kind**: which of the five forms a surface is asking for.
- **Display context**: the site timezone and active locale that turn an instant into text —
  resolved once and shared, never re-derived per screen.
- **Absent-value meaning**: the field-specific truthful phrase used when there is no instant —
  distinct per field, because "Never", "No expiry" and "Not recorded" are different statements.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An operator can read any date on any CoreX admin screen and state the day, month,
  year, hour and minute without interpreting a machine format — verified across every surface listed
  in FR-020.
- **SC-002**: Zero operator-facing surfaces display a raw machine timestamp. Currently twelve do.
- **SC-003**: Two operators in different countries, viewing the same record at the same moment, read
  the identical time. Currently six surfaces give them different answers.
- **SC-004**: A date shown by the server and the same date shown in the browser are
  character-identical, verified for the same instant across both languages the product ships.
- **SC-005**: Every date in the product is announced correctly by a screen reader and reachable
  without a pointing device, including every relative time.
- **SC-006**: Sorting a date column always yields chronological order, verified across month, year
  and leap-day boundaries.
- **SC-007**: No absent or corrupt timestamp produces a fabricated date, a broken layout, or a
  developer-facing error string on any screen.
- **SC-008**: The full acceptance matrix — both languages, RTL, narrow width, 200% zoom, light and
  dark — passes with no horizontal overflow and no console error.

## Assumptions

- **The required English format is fixed by the owner**, not derived from the site's date/time
  settings. WordPress's Settings → General format options therefore do not govern CoreX admin
  presentation. This is a deliberate departure from the three existing PHP call sites, which do
  follow those options and consequently vary per site; it is what makes SC-004 achievable.
- **The product ships English and Arabic.** Other locales must degrade correctly through the
  localization system rather than through CoreX-specific special cases.
- **"Every CoreX admin surface" means the screens listed in FR-020** — the surfaces that exist as of
  `af1d352`. Cache operations and error correlation views arrive with specs 078 and 079 and adopt
  this contract when they are built.
- **The front office is out of scope.** This spec covers the administration experience.
- **Storage migration is out of scope.** No stored value changes shape.
- **Relative time stays where it already is** (notification items) and is not introduced to new
  surfaces by this spec; the contract merely makes it correct and accessible.
