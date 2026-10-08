# Feature Specification: A submissions inbox and exports a client can use

**Feature Branch**: `spec/103-submissions-inbox-export`

**Created**: 2026-10-07

**Status**: Draft

**Input**: Owner requirement, stated while using CoreX → Submissions on the first client site —
"i need the full row to be clickable not just the first column", "the right pane opened i feel that
it is not well organized", and of the export: "not helpful at all … i should be able to export on
selected rows or all rows or specific filters i have selected and the exported file should be
readable not serialized cells … why it is just a CSV why can't i export as modern excel extensions?
even if i wanted to export as a pdf! signed off with the identity! even in the data export the
operations is poor. why should i refresh to be able to download files? why it looks like this in
the first place".

## Why this spec exists

A site collects submissions so that somebody can read them, act on them, and hand them to somebody
else. The inbox and its export do each of those badly today. Read at `fb078381`:

- **The exported file is not readable.** It has one column per *group* of data — identity,
  workflow, submitted fields, hidden metadata, UTM, consent, notes — and each cell holds that whole
  group as one encoded string. A lead's five answers arrive as a single cell of punctuation. The
  header row is the group names.
- **It is written for a script.** One format, comma-separated text. Arabic and accented names open
  garbled in a spreadsheet. Dates are machine timestamps. An owner is a type and a key, not a name.
  Answers are headed by field keys, not by the questions the form asked.
- **Getting the file takes a refresh nobody is told about.** Creating an export queues it. The
  dialog shows nothing until "Refresh history" is pressed, and the file needs a second click on
  "Download" after that.
- **The dialog does not look like part of the product.** Its column choices are raw keys run
  together on one line and never translated; the scope control is unstyled; the warning about
  personal data shares a line with an unrelated option.
- **What will be exported is not stated.** A choice of scope exists, but nothing shows which filters
  are in force or how many records each choice means.
- **Only one cell of a row opens the submission.** A click on the form, the status or the date does
  nothing.
- **The detail pane puts the answers fourth**, under a status control, a read toggle, assignment and
  delivery. It labels them with field keys. Three sections that hold nothing each take a heading, a
  line saying so, and a divider. Delivery says "unavailable" and neither why nor what to do.
- **The Data export has the same faults in its own places**, and already writes a spreadsheet format
  the submissions export does not offer.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — An export somebody can read (Priority: P1)

A site owner exports the leads from one form and opens the file. Each lead is one row. Each answer
is in its own column, headed by the question the form asked. Before the answers are the columns a
person looks for first: the submission's number, when it arrived in the site's own time, which form,
its status, who it is assigned to by name, whether it has been read, and whether it was a test.

**Why this priority**: It is the owner's first and strongest complaint, and the file is what a
client keeps. Nothing else about the export matters while the file cannot be read.

**Independent Test**: Export three submissions of one form with different answers, open the file in
a spreadsheet, and read every answer without decoding anything.

**Acceptance Scenarios**:

1. **Given** a form with the questions "Name", "Email" and "What are you looking for?", **When** its
   submissions are exported, **Then** the file has one column for each question, headed by that
   wording, and one row per submission.
2. **Given** a submission that did not answer an optional question, **When** it is exported,
   **Then** its row has an empty cell in that question's column and every other answer stays in its
   own column.
3. **Given** a submission assigned to a person, **When** it is exported, **Then** the "Assigned to"
   cell holds that person's display name.
4. **Given** a site whose timezone is not UTC, **When** a submission is exported, **Then** its
   "Submitted" value is the time in the site's timezone.
5. **Given** an answer that begins with `=`, `+`, `-` or `@`, **When** it is exported, **Then** a
   spreadsheet shows it as text and does not evaluate it.
6. **Given** a form whose question was renamed after some submissions arrived, **When** they are
   exported, **Then** each answer is still in one column, headed by the question's current wording.
7. **Given** an answer with several values, such as a multiple choice, **When** it is exported,
   **Then** its cell lists the values in words, separated the same way in every row.

---

### User Story 2 — Choosing what to export, and getting the file (Priority: P1)

The owner opens the export from the inbox. The dialog states what each choice means: the rows
ticked, the submissions matching the filters in force with those filters spelled out, or every
submission the owner may see, each with its number of records. The owner picks, confirms the
personal-data notice when the choice includes personal data, and presses one button. The file
arrives when it is ready, with nothing to refresh.

**Why this priority**: The owner could not tell what would be exported and had to discover the
refresh. A readable file that cannot be obtained is no better than the one it replaces.

**Independent Test**: Filter the inbox to one form and one status, tick two rows, open the export,
and check that each choice shows the right count and that pressing export produces a file without
any other action.

**Acceptance Scenarios**:

1. **Given** two rows are ticked, **When** the export opens, **Then** "Selected" is offered with the
   count 2 and is the choice already made.
2. **Given** filters for a form, a status and a date range are in force, **When** the export opens,
   **Then** "Current filters" is offered with those filters named in words and the count of
   submissions they match.
3. **Given** no row is ticked and no filter is in force, **When** the export opens, **Then**
   "Selected" cannot be chosen and says why.
4. **Given** the owner presses the export button, **When** the file is ready, **Then** it is saved
   by the browser without another click, and the dialog says so.
5. **Given** an export large enough to take time, **When** it is running, **Then** the dialog shows
   how far it has got and may be closed; the owner is told when the file is ready and where to get
   it.
6. **Given** an export fails, **When** the dialog reports it, **Then** it says what failed in words
   and offers to try again.
7. **Given** the choice includes personal data, **When** the personal-data notice is not confirmed,
   **Then** the export cannot be started.
8. **Given** a person who may not export personal data, **When** they open the export, **Then** the
   columns holding personal data are not offered, and the dialog says they are withheld.
9. **Given** any export is started, **When** it is recorded, **Then** the activity log holds who
   started it, what it covered and how many records.

---

### User Story 3 — A dialog that belongs to the product (Priority: P1)

The export dialog is laid out in the order the decision is made: what to export, which columns, in
what format, then one line summarising the choice above one button. Every word in it is translated.
Every control in it is one of CoreX's own, looks the same as on the screen behind it in light and
dark, and works by keyboard.

**Why this priority**: "why it looks like this in the first place". It ships with stories 1 and 2 or
they are not finished.

**Independent Test**: Open the dialog in light and dark at a wide and a narrow width, complete an
export using only the keyboard, and switch the admin language.

**Acceptance Scenarios**:

1. **Given** the dialog is open, **When** it is compared with the inbox behind it, **Then** its
   controls, type and colours are the same in light and in dark.
2. **Given** a keyboard only, **When** the dialog is opened, completed and closed, **Then** focus
   starts inside it, never leaves it while it is open, and returns to the control that opened it.
3. **Given** the admin is in another language, **When** the dialog opens, **Then** no label in it is
   in English because it was never made translatable.
4. **Given** the columns to include, **When** they are listed, **Then** each is named in words a
   site owner uses, and the ones holding personal data are marked as such.
5. **Given** a narrow screen, **When** the dialog opens, **Then** nothing in it is cut off or
   scrolls sideways.

---

### User Story 4 — The whole row opens the submission (Priority: P2)

Clicking anywhere on a row of the inbox opens that submission. The row shows that it can be clicked,
and shows which submission is open while its pane is showing. Ticking the row's checkbox still only
ticks it.

**Why this priority**: The owner's first words, and small. It does not depend on the export.

**Independent Test**: Click the form, status and date cells of a row; each opens the submission.
Click the checkbox; the pane does not open.

**Acceptance Scenarios**:

1. **Given** the inbox, **When** any cell of a row other than its checkbox is clicked, **Then** that
   submission opens.
2. **Given** a row's checkbox is clicked, **When** it toggles, **Then** no pane opens.
3. **Given** a keyboard, **When** the inbox is tabbed through, **Then** each row has exactly one stop
   that opens it, and that stop is named for the submission.
4. **Given** a submission is open, **When** the inbox is looked at, **Then** its row is marked as
   the open one, and not by colour alone.

---

### User Story 5 — A detail pane in the order it is read (Priority: P2)

Opening a submission shows who sent it and what they said first. The header names the sender, links
their email and phone when the form asked for them, and gives the form, the date and the
submission's number, its status and whether it has been read. The answers follow, headed by the
form's questions. Then reply and notes. Then one triage group: the status, and one control for who
it is assigned to. Then delivery, which says what was sent, to whom, when, and why it failed when it
did. Technical details that hold nothing are not drawn as empty sections.

**Why this priority**: The owner's second complaint. It shares the question wording with the export,
so it follows it.

**Independent Test**: Open a submission from a form with no hidden metadata, no campaign data and no
consent record; the answers are the first thing under the header and no empty section is shown.

**Acceptance Scenarios**:

1. **Given** a submission is opened, **When** the pane appears, **Then** the answers are the first
   section under the header and are headed by the form's questions.
2. **Given** the form asked for an email and a phone number, **When** the pane's header is shown,
   **Then** each is a link that starts an email or a call.
3. **Given** nothing was recorded for hidden metadata, campaign data or consent, **When** the pane
   is shown, **Then** it holds one closed "Technical details" group that says nothing was recorded,
   and no heading per empty part.
4. **Given** a notification could not be sent, **When** delivery is shown, **Then** it gives the
   reason and, where the site can fix it, where.
5. **Given** the assignment control, **When** it is used, **Then** it is one control listing the
   people who can own the submission and "Unassigned".
6. **Given** a keyboard, **When** a submission is opened, **Then** focus moves into the pane, Escape
   closes it, and focus returns to the row it was opened from.
7. **Given** a narrow screen or a window with the admin toolbar, **When** the pane is open,
   **Then** nothing of it is hidden under the toolbar or the window's edge, and the inbox behind it
   is either usable or plainly not in use.
8. **Given** an unread submission is opened, **When** the pane appears, **Then** it is marked read,
   and one control in the header marks it unread again.

---

### User Story 6 — Excel (Priority: P2)

The owner chooses Excel and receives a workbook. Dates are dates and numbers are numbers, so they
sort and filter. The header row stays in view while scrolling, columns are wide enough to read, and
filtering is already on. Submissions from several forms arrive as one sheet per form, since each
form has its own questions.

**Why this priority**: "why can't i export as modern excel extensions". The plain-text file of
story 1 already opens in a spreadsheet; this makes it a spreadsheet.

**Independent Test**: Export two forms' submissions as Excel, open the workbook, sort one sheet by
its date column and check the order is chronological.

**Acceptance Scenarios**:

1. **Given** Excel is chosen, **When** the file is opened, **Then** the "Submitted" column sorts as
   dates and a numeric answer sorts as a number.
2. **Given** submissions from two forms, **When** exported as Excel, **Then** the workbook has one
   sheet per form, named for the form, each with that form's questions as its columns.
3. **Given** the workbook is scrolled down, **When** the header is looked for, **Then** it is still
   in view.
4. **Given** text in Arabic or with accents, **When** the workbook is opened, **Then** it reads
   correctly.

---

### User Story 7 — Text files that open correctly (Priority: P2)

The owner chooses CSV and the file opens correctly in a spreadsheet on the first try, in any
language. Where a region's spreadsheet expects a different separator, the owner can choose it.
Several forms in one text export are stated, not left to chance.

**Why this priority**: The format most tools accept. It is the same content as story 1 with the
things that make it open correctly.

**Independent Test**: Export submissions with Arabic names as CSV, double-click the file, and read
the names.

**Acceptance Scenarios**:

1. **Given** names in Arabic, **When** the CSV is opened by double-clicking, **Then** the names read
   correctly.
2. **Given** the separator is set to a semicolon, **When** the file is opened where that is the
   regional separator, **Then** every value is in its own column.
3. **Given** submissions from two forms, **When** exported as CSV, **Then** the dialog says before
   the export how they will arrive, and they arrive that way.

---

### User Story 8 — A document to file (Priority: P3)

The owner chooses PDF and receives a document a client can keep: the site's name, a title, what
was exported and under which filters, who exported it and when, the submissions laid out to be
read, page numbers, and CoreX's signature on every page: its logo and its copyright line.

**Why this priority**: Asked for by name, and the one format with a layout of its own. It depends on
the content the earlier stories settle.

**Independent Test**: Export five submissions as PDF and print it; every page carries the site's
name, a page number and CoreX's signature.

**Acceptance Scenarios**:

1. **Given** a site, **When** a PDF is exported, **Then** the site's name heads every page.
2. **Given** a PDF export, **When** it is read, **Then** it states the form or forms, the filters
   used, the number of submissions, the person who exported it and the date and time.
3. **Given** a PDF of several pages, **When** it is printed, **Then** each page is numbered as
   "page N of M" and no submission's answers are split from their question.
4. **Given** any page of the document, **When** it is read, **Then** it carries the CoreX logo and
   a copyright line naming CoreX.
5. **Given** a site with no extra plugin installed, **When** PDF is chosen, **Then** it works.
6. **Given** answers in Arabic, **When** the PDF is produced, **Then** they read correctly and in
   the right direction.

---

### User Story 9 — What was exported before (Priority: P3)

The owner sees past exports: who made each, when, what it covered, in which format, how many
records and how large. A past export can be downloaded again until it expires, and deleted.

**Why this priority**: The history exists today as three values and a link. It is what makes an
export accountable, and it follows the formats it lists.

**Independent Test**: Make two exports, reopen the dialog, download the first again and delete the
second.

**Acceptance Scenarios**:

1. **Given** past exports, **When** the history is shown, **Then** each has the person, the date and
   time, what it covered in words, the format, the record count, the size and when it expires.
2. **Given** a past export that has not expired, **When** "Download" is pressed, **Then** the same
   file is saved, with a name that says the site, the form and the date.
3. **Given** a past export, **When** it is deleted, **Then** its file is gone, the history says it
   was deleted, and the activity log records who deleted it.
4. **Given** an export older than its retention, **When** the history is shown, **Then** it is
   listed as expired and cannot be downloaded.
5. **Given** a person who did not make an export and may not manage all submissions, **When** they
   open the history, **Then** they do not see it.

---

### User Story 10 — The Data export, to the same standard (Priority: P3)

Every requirement above that applies to an export applies to CoreX → Data export: what is exported
is stated with its count, the file is readable, the same formats are offered, the file arrives
without a refresh, and its dialog is built from the same parts.

**Why this priority**: "even in the data export the operations is poor". It is a second screen
sharing the first one's machinery, so it comes after that machinery exists.

**Independent Test**: Run the Data export and the Submissions export side by side and find the same
choices, the same formats and the same behaviour on completion.

**Acceptance Scenarios**:

1. **Given** the Data export, **When** an export is started, **Then** the file arrives without a
   refresh.
2. **Given** the Data export, **When** formats are offered, **Then** they are the ones the
   Submissions export offers for the same kind of content.
3. **Given** both exports, **When** their dialogs are compared, **Then** a person who has used one
   can use the other without learning it.

---

### Edge Cases

- An export of zero records: the dialog says there is nothing to export and does not produce an
  empty file.
- A form deleted after its submissions arrived: its answers are still exported, headed by the field
  keys stored with them, and the file says the form's wording was not available.
- Two questions with the same wording in one form: both columns are kept and can be told apart.
- A file upload answer: the cell holds the file's name, not its contents.
- A very long answer: it is not cut in a spreadsheet format; a PDF wraps it.
- A submission deleted or reassigned out of the exporter's sight while the export runs: the export
  finishes with the records still available and states the difference from the count it promised.
- Two exports started at once by the same person: both complete and both appear in the history.
- A selection larger than the current limit on selected rows: the dialog says the limit and offers
  "Current filters" instead.
- The browser blocks the automatic save: the dialog offers the file by a button.
- The dialog is closed while an export runs: the export continues and is in the history.
- A site in a right-to-left language: the dialog, the pane and the PDF are laid out for it.
- A personal-data column is among those remembered for a person who has since lost the right to
  export personal data: it is dropped, and the dialog says so.

## Requirements *(mandatory)*

### Functional Requirements

**The exported content**

- **FR-001**: An export MUST contain one row per submission.
- **FR-002**: Each answer MUST be in its own column, headed by the wording of the form's question as
  it is now. Where that wording is not available, the stored field key is the heading.
- **FR-003**: Before the answers, an export MUST offer these columns, named in words: the
  submission's number, when it was submitted, the form, the status, who it is assigned to, whether
  it was read, and whether it was a test.
- **FR-004**: "Assigned to" MUST be a person's display name, and "Unassigned" when there is none.
- **FR-005**: Dates and times MUST be in the site's timezone, in a form a person reads.
- **FR-006**: An answer holding several values MUST be written as those values in words, with one
  separator used throughout.
- **FR-007**: Optional groups — hidden metadata, campaign data, the consent record, notes — MUST be
  offered as columns a person can add, each value in a column of its own, and MUST be left out by
  default.
- **FR-008**: A value that a spreadsheet would evaluate as a formula MUST be written so that it is
  shown as text, in every format a spreadsheet opens.
- **FR-009**: The owner MUST be able to choose which columns are included and their order, and the
  choice MUST be remembered for that person and that form.

**What is exported**

- **FR-010**: The export MUST offer three scopes — the rows selected, the submissions matching the
  filters in force, and every submission the person may see — and MUST show the number of records
  each would export before it is started.
- **FR-011**: The "current filters" scope MUST name the filters in force in words.
- **FR-012**: Submissions marked as tests MUST be left out unless the person asks for them.
- **FR-013**: An export MUST only ever contain submissions the person may see, whatever was asked
  for.
- **FR-014**: An export that would contain no records MUST NOT produce a file.

**Formats**

- **FR-015**: The export MUST offer Excel, CSV and PDF.
- **FR-016**: The Excel format MUST carry dates as dates and numbers as numbers, keep the header row
  in view, turn filtering on, size columns to be read, and put each form on its own sheet.
- **FR-017**: The CSV format MUST open correctly, in any language, when opened directly in a
  spreadsheet, and MUST offer a choice of separator.
- **FR-018**: When a text export covers several forms, the dialog MUST say before the export how
  they will be delivered, and MUST deliver them that way.
- **FR-019**: The PDF format MUST carry the site's name, a title, what was exported and under which
  filters, the number of records, who exported it and when, page numbers, and CoreX's signature on
  every page: the CoreX logo and a copyright line.
- **FR-019a**: A PDF MUST NOT be offered for more records than it can be written for in one step,
  and the dialog MUST say what the most is and offer the workbook instead.
- **FR-020**: Every format MUST work on a site with no optional plugin installed.
- **FR-021**: Every format MUST render right-to-left text correctly.

**Getting the file**

- **FR-022**: When an export is ready, the file MUST be saved by the browser without a further
  action, and the dialog MUST offer it by a button if the browser prevents that.
- **FR-023**: While an export runs, the dialog MUST show its progress, and closing the dialog MUST
  NOT stop it.
- **FR-024**: A failed export MUST say what failed in words and MUST be retryable.
- **FR-025**: An exported file MUST be named for the site, what it holds and the date.
- **FR-026**: The capability check, the personal-data confirmation and the activity-log entry that
  exist today MUST be kept, and MUST apply to every format.
- **FR-027**: A person who may not export personal data MUST NOT be offered the columns that hold
  it.

**History**

- **FR-028**: The history MUST show, for each export, who made it, when, what it covered, the
  format, the number of records, the size, and when it expires.
- **FR-029**: A past export MUST be downloadable again until it expires and MUST be deletable;
  deleting it MUST be recorded in the activity log.
- **FR-030**: Exported files MUST expire after a retention period, after which the file is removed
  and the entry remains.
- **FR-031**: A person MUST see only their own exports unless they may manage all submissions.

**The dialog**

- **FR-032**: The export dialog MUST be laid out as: what to export, which columns, the format,
  then a one-line summary and any confirmation above a single primary action.
- **FR-033**: Every label in the dialog MUST be translatable.
- **FR-034**: The dialog MUST use CoreX's own controls and MUST look the same as the screen behind
  it, in light and in dark, wherever in the page it is drawn.
- **FR-035**: The dialog MUST be operable by keyboard alone, MUST hold focus while open, and MUST
  return focus to the control that opened it.

**The inbox row**

- **FR-036**: Clicking any part of an inbox row other than its checkbox MUST open that submission.
- **FR-037**: A row MUST have exactly one keyboard stop that opens it.
- **FR-038**: The row of the open submission MUST be marked as open, and not by colour alone.

**The detail pane**

- **FR-039**: The pane MUST show, in this order: a header; the answers; reply and notes; triage;
  delivery; technical details.
- **FR-040**: The header MUST give the sender's name, their email and phone as links when the form
  asked for them, the form, the date and time, the submission's number, its status, and whether it
  is read.
- **FR-041**: The answers MUST be headed by the form's questions, with the field key as the fallback.
- **FR-042**: Triage MUST be one group holding a labelled status control and one assignment control
  listing the people who can own the submission, and "Unassigned".
- **FR-043**: Delivery MUST say, for each attempt, to whom, when and with what result, and MUST give
  the reason for a failure and where it can be fixed when the site can fix it.
- **FR-044**: Hidden metadata, campaign data and the consent record MUST be one closed group; a part
  with nothing recorded MUST NOT be drawn as an empty section.
- **FR-045**: Opening a submission MUST move focus into the pane; Escape MUST close it; closing it
  MUST return focus to the row it was opened from.
- **FR-046**: The pane MUST be fully visible below the admin toolbar and within the window at every
  supported width, and the inbox behind it MUST be either usable or plainly not in use.
- **FR-047**: Opening an unread submission MUST mark it read, and the header MUST offer to mark it
  unread.

**The Data export**

- **FR-048**: The Data export MUST meet FR-010, FR-014, FR-015 to FR-018, FR-020 to FR-026 and
  FR-032 to FR-035 for its own content.

**Across all of it**

- **FR-049**: Every screen and dialog in this spec MUST meet WCAG 2.2 AA and MUST be verified in
  light and dark, left-to-right and right-to-left, at a wide and a narrow width.
- **FR-050**: Nothing in this spec MUST show data that is not real: no sample rows, no placeholder
  counts.

### Key Entities

- **Submission**: one response to a form — its answers, its form, when it arrived, its status, its
  owner, whether it was read, whether it was a test, and the optional groups recorded with it.
- **Form's questions**: the wording and order of what a form asked, used to head answers in the
  export and in the pane.
- **Export**: one request for a file — who asked, what scope and filters, which columns, which
  format, how many records, its progress, its file, its size and when it expires.
- **Column choice**: the columns and order a person last chose for a form.
- **Delivery attempt**: one try at sending a notification — to whom, when, the result and the
  reason.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A person who has never seen the site can open an exported file and say what each of
  three submissions answered to each question, without help, in under one minute.
- **SC-002**: From pressing the export button, a file of up to 500 submissions is saved in under ten
  seconds, with no other click.
- **SC-003**: Before starting an export, a person can state how many records it will contain, and
  the file contains that number.
- **SC-004**: A file with Arabic text opens readable, on the first try, in each of the three
  formats.
- **SC-005**: In a workbook export, sorting by the submitted column orders every row
  chronologically.
- **SC-006**: Any cell of an inbox row opens its submission; none of them does nothing.
- **SC-007**: In the detail pane, the first answer is visible without scrolling at a window height
  of 720 pixels.
- **SC-008**: A submission with no technical details recorded shows no empty section.
- **SC-009**: The export can be completed, and a submission opened, read and closed, with the
  keyboard alone.
- **SC-010**: No label on these screens is shown in English on a translated site because it was not
  translatable.
- **SC-011**: A person who has used the Submissions export completes a Data export without being
  shown how.

## Assumptions

- **Opening a submission marks it read.** It is what an inbox does, and the separate "Mark read"
  click is one of the things that made the pane feel disorganised. The header keeps a way to mark it
  unread. The owner has not confirmed this.
- **"Signed off with the identity" means CoreX's own signature.** The owner, 2026-10-08: "use a
  corex signature, that what i did mean, pdf exported should has the corex logo on it as it has the
  copyrights of the tool". The earlier reading here, the site's brand and a block for the exporting
  person to sign, was wrong and is withdrawn. A cryptographic signature is not in scope.
- **Several forms in one text export** arrive as one file per form inside a single archive, since
  each form has its own columns. One file with the union of every form's columns is the alternative
  and was judged harder to read.
- **Exported files expire after 30 days** unless the site already defines a retention for exports.
- **The limit on selected rows stays** at what it is today unless planning finds it arbitrary.
- **The question wording used is the form's current wording.** A form's history of wording is not
  reconstructed per submission.
- The brand name and logo come from the site's existing brand settings; a site with neither gets the
  site's title and no logo.
- A person's saved column choice is theirs alone and is not shared between people.

## Out of scope

- Importing submissions.
- Scheduled or recurring exports, and exports sent by email.
- Editing a submission's answers.
- A captcha for forms defined in code, and the form block's rendering contract (#264, #248). They
  share nothing with this spec but the form's question wording.
- Changing what an inbox filter can filter by.

## Delivery

In slices, each its own pull request, in this order: the inbox row (US4); the readable file, the
scopes with counts, the dialog and the automatic download, with CSV as the first format (US1, US2,
US3, US7); Excel (US6); the detail pane (US5); the history (US9); PDF (US8); the Data export (US10).
