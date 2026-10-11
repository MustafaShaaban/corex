# Feature Specification: Several data models are exported, imported and moved together

**Feature Branch**: `spec/109-data-bulk-transfer`

**Created**: 2026-10-11

**Status**: Decided. The owner answered the five questions on 2026-10-11 with "as recommended";
what that decided is under "Decided by the owner". [plan.md](./plan.md) follows it.

**Input**: The owner's report of 2026-10-11, from using the first client site: "the
import/export/migrate model is using one by one, i can't do a bulk or multiselect". The client
site's session recorded it as: "records of one model can be chosen and exported together, and
several models cannot be exported, imported or moved to another install in one go".

**The brief this spec was written from was checked against the code. Two things in it are
corrected here and one is qualified** (read at `21fb2dd9`, v0.44.0):

- **Import is already on the screen, and so is "Migrations".** The brief said the screen has no
  import. CoreX → Data has five tabs: Models, Records, Import, Export and Migrations. Import,
  Export and Migrations each begin with one dropdown named "Model". That dropdown, three times,
  is what the owner met.
- **"Migrations" on that screen does not move data.** It applies a change to a model's table
  that the model ships (the one that exists adds two indexes), after copying the table, and can
  put the copy back. Nothing on the screen, and nothing in CoreX, carries records from one
  install to another.
- **Qualified: what an export keeps and tells.** The brief lists a background job, retention
  and a notification with a download. The job runs in steps with its progress shown and the
  file saved by the browser when ready (spec 103). A finished export does notify the person
  who made it; that is older than spec 103, and its link opens the Data screen, on its first
  tab, not the file. The 30-day expiry is the Submissions export's: no expiry was found for a
  Data export's file, though the notification says the file "stays available until the export
  retention window ends".

## Why this spec exists

**What the screen does today, one model at a time:**

- **Export.** Choose one model, press "Export <model>…", choose columns, a format (Excel, CSV,
  PDF) and confirm. The history under it lists that one model's exports. A site with both
  add-ons that declare a table has eleven models; exporting all of them is the same dialog
  eleven times and eleven files.
- **Import.** Choose one model, give one CSV file of at most 2 MB and 5,000 rows, read the trial
  run, map the columns it did not recognise, approve. Two models accept an import: newsletter
  subscribers, and job applications by their declaration (only the status and the CV can be
  written, so an imported application would have no name; read from the declaration, not run).
  Form submissions accept none.
- **Migrations.** Choose one model, preview one change, apply it. One model ships one today.

**What is wrong beyond the dropdown, found while reading for this spec:**

- **An import adds every row it accepts, whether or not the record is already there.** Nothing
  compares a row with what the model holds. The same file imported twice doubles the list.
- **An export cannot be imported back, and is not meant to be.** Since spec 103 an export is
  written for a person: columns headed by their labels, a list written as words. An import
  reads field keys, writes only the fields a model declared writable, and sets
  the rest itself: an imported subscriber is always "pending", whatever they were. So "export
  here, import there" loses a confirmed subscriber's confirmation and the day they subscribed,
  and for submissions is not possible at all.
- **No column of a table model is marked as personal data.** Every managed table's columns are
  declared as holding none: a subscriber's email, an applicant's name and email, a login
  attempt. Exporting them asks for no confirmation. Form submissions do mark their answers, by
  guessing from the answer's type: an email, a phone number and a long text are marked, a name
  is not.
- **The same submissions are guarded differently in two places.** In the Submissions inbox, the
  columns holding personal data are offered only to a person allowed to export personal data,
  by default an administrator. On the Data screen, anybody who manages submissions exports them
  after ticking a box.

**The need behind the report.** Client sites run on servers that own live data: the leads and
briefs received in production exist nowhere else. On 2026-10-08 the first client site moved to a
shared host with no command line, and the plan for its leads, as that site's session reported
it, was a general backup plugin, because CoreX had nothing for it. An outline agreed with the
owner that day had three parts: A, install a release from the admin; B, each kind of data flows
one way, so a push never writes what production received; C, pull production data down,
optionally anonymised. A became spec 107. B
and C were recorded as "the next two specs" (DECISIONS #282) and were not written. Moving several
models at once is where B's rule has to hold, so this spec states it for the Data screen's
models and carries C for them. What it leaves of B, and why, is the second of the owner's
decisions.

## Three words, kept apart

The owner's sentence uses three words. This spec gives each one meaning:

- **Export**: a file for a person to read or hand to somebody. It can hold several models. It
  cannot be given back to CoreX.
- **Import**: records from somewhere else (another product's list, a spreadsheet) brought into a
  model through a trial run. Several files can be reviewed and approved together.
- **Move**: records carried from one CoreX install to another, complete and unchanged, in a
  file made by CoreX for CoreX: a **data package**. A move is not an export followed by an
  import. It differs in five ways:
  1. It carries the whole record, with the fields an import may not write: the status, the
     dates, the owner, the notes, the consent record.
  2. Each record carries where it was born, so the receiving install knows a record it has
     already received, however often the package arrives.
  3. It only ever adds to a live site. It changes and removes nothing there.
  4. It knows which way each kind of data may travel.
  5. It can be made with the personal data replaced, for a development copy.

"Migrations", the tab, keeps its meaning: a change to a model's table. The owner meant the move,
not the tab (the first decision); the tab stays one model at a time.

## Clarifications

### Session 2026-10-11

Five questions were put to the owner, each with a recommendation. He answered all five on
2026-10-11 with "as recommended". Each is written out under "Decided by the owner".

- Q: What did "migrate" mean: moving records to another install, the Migrations tab, or both? →
  A: Moving records. The Migrations tab stays one model at a time (User Story 7 and FR-048
  withdrawn).
- Q: Does a move include pages, templates, patterns, form definitions and settings? → A: No. A
  spec of their own, after spec 107 can install a release (FR-024, Out of scope).
- Q: Who may take personal data out in a file? → A: Only a person given that right, by the
  Submissions inbox's rule, on the Data screen too (FR-020, FR-055).
- Q: On a copy that is not live, may receiving update records that changed since the last
  package? → A: Yes, per model, off unless ticked. A live site is add-only (FR-034, FR-035).
- Q: Does a package carry uploaded files? → A: Only when asked, off by default, with the size
  stated first (FR-027).

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Several models are exported in one go (Priority: P1)

The owner opens Export and sees his models as a list he can tick, each with how many records it
holds. He ticks the ones he wants, or "all", chooses a format once and confirms once. One file
is saved. The history shows the export once, with the models it covered.

**Why this priority**: It is the report, word for word, and it is useful with nothing else
built.

**Independent Test**: On a site with three models that hold records, tick the three, export as
Excel, and open the file: three sheets, each named for its model, each with the number of rows
the dialog stated.

**Acceptance Scenarios**:

1. **Given** five models the person may export, **When** they open Export, **Then** all five
   are listed with their record counts, none is ticked for them, and one control ticks all.
2. **Given** three ticked models, **When** the person exports as Excel, **Then** one workbook
   is saved with one sheet per model and a first sheet saying what was exported, by whom and
   when.
3. **Given** three ticked models, **When** the person exports as CSV, **Then** one archive is
   saved holding one CSV file per model, and the dialog said so before the export.
4. **Given** ticked models whose records together exceed what a PDF holds, **When** the person
   looks at the formats, **Then** PDF is not offered and the dialog says why.
5. **Given** a model the person may not export, **When** they open Export, **Then** it is not
   in the list, and one line says how many models are not offered and where each is explained.
6. **Given** one ticked model holds no records, **When** the export is made, **Then** the file
   holds the others, and the dialog said beforehand that the empty one would be left out.
7. **Given** one model is ticked, **When** the person exports, **Then** the dialog is the one
   they know today, with its choice of columns and its three scopes.

---

### User Story 2 — Personal data is marked where it is, and asked about wherever it leaves (Priority: P1)

A model says which of its columns hold personal data, and of which kind. Every way a record
leaves the site (an export of one model, of several, a data package) reads that one statement:
it asks for confirmation when personal data is included, offers those columns only to a person
allowed to take them, and writes down that it happened.

**Why this priority**: A file of several models is a larger leak than a file of one. Shipping
User Story 1 on today's marking would let a person without the right export every subscriber
and applicant in one file, unasked.

**Independent Test**: Export newsletter subscribers alone. The dialog names the email column as
personal data and will not continue without the confirmation; a person not allowed to export
personal data is not offered that column.

**Acceptance Scenarios**:

1. **Given** a model with a column holding an email address, **When** any export includes that
   column, **Then** the confirmation is required and names the kinds of personal data included.
2. **Given** a person who may export a model and may not export personal data, **When** they
   export it, **Then** the columns holding personal data are not offered, in the Data screen as
   in the Submissions inbox.
3. **Given** a model that has not said which of its columns are personal, **When** it is
   exported, **Then** all of its columns are treated as personal data.
4. **Given** a form submission with a name typed into a plain text field, **When** the
   submission is exported, **Then** that answer is treated as personal data.
5. **Given** any export or package that included personal data, **When** it finishes, **Then**
   the activity log holds who made it, which models and how many records, and none of the
   values.

---

### User Story 3 — Records are moved from one install to another (Priority: P2)

On the install that has the records, the owner ticks the models to move and makes a data
package: one file. On the other install he gives CoreX that file. CoreX reads it and says, for
each model, how many records are new here, how many are already here, and how many it cannot
take and why. He confirms, and the new ones are added, complete: a lead arrives with its
answers, its status, the day it was received and its notes; a confirmed subscriber arrives
confirmed.

**Why this priority**: It is what a host move and a development copy both need; on 2026-10-08
the first client's move was planned with a backup plugin instead. It comes after the P1 stories
because it is larger and because it depends on the marking in User Story 2.

**Independent Test**: Make a package of submissions and subscribers on one install, receive it
on an empty one, and compare: the same number of each, each record's fields equal to the
original's.

**Acceptance Scenarios**:

1. **Given** models that can be moved, **When** the person opens the move, **Then** they are
   listed to tick with their record counts, and models that cannot be moved are named with the
   reason.
2. **Given** ticked models, **When** the package is made, **Then** one file is offered, named
   for the site, what it holds and the date, and the screen said beforehand how many records
   and roughly how large.
3. **Given** a package from another install, **When** it is given to CoreX, **Then** before
   anything is written the screen states where and when it was made and, per model, the counts
   of new, already here, and cannot be taken, with each reason.
4. **Given** the person confirms, **When** the records are added, **Then** each new record
   holds what the original held, including what an import could not have set.
5. **Given** the same package is received a second time, **When** the preview is shown,
   **Then** every record counts as already here and confirming adds none.
6. **Given** a record that names a person (the lead's owner) who has no account here, **When**
   it is received, **Then** it arrives unassigned and the preview said how many would.
7. **Given** a package whose file was changed after it was made, or that is not a data package,
   **When** it is given to CoreX, **Then** it is refused before anything is written, with the
   reason.
8. **Given** a package holding a model this install does not have, **When** it is previewed,
   **Then** that model is listed as cannot be taken, and the others can still be received.

---

### User Story 4 — A live site keeps everything it received (Priority: P2)

Whatever is received on a live site, nothing that was there before is different afterwards. The
record a visitor sent yesterday is still there, with its status, its notes and its answers,
even when the package holds an older or a different copy of it. A record erased here does not
come back.

**Why this priority**: It is the rule the owner set on 2026-10-08, "if production has new form
submissions a push must not remove them", and it is the reason a move is not an import. It is
delivered together with User Story 3, never after it.

**Independent Test**: On a live install holding ten leads, three of them edited since a package
was made and one deleted for good, receive that package. The ten are unchanged, the deleted one
is still gone, and the preview said so in numbers.

**Acceptance Scenarios**:

1. **Given** a live install, **When** any package is received, **Then** no record that existed
   before is changed or removed, and the screen offers no choice that would.
2. **Given** a record that is already here and differs from the package's copy, **When** the
   package is previewed, **Then** it is counted as "already here, left as it is".
3. **Given** a record deleted for good here, **When** a package holding it is received,
   **Then** it is not added, and is counted as "deleted here, not brought back".
4. **Given** a record whose answers were removed here by retention, **When** a package holding
   the answers is received, **Then** the record stays as retention left it.
5. **Given** a package made on an install that is not live, holding records of a kind born in
   production, **When** it is given to a live install, **Then** those models are refused, with
   the reason that test data does not belong on a live site.
6. **Given** an install that is not live, **When** a package is received, **Then** the person
   may choose, per model, to bring records that differ up to date; records are still never
   removed.

---

### User Story 5 — A development copy without real people in it (Priority: P3)

When a package is for a development or staging install, the owner ticks "Replace personal
data". The package then holds the same records with every personal value replaced by a made-up
one, and none of the uploaded files. A developer gets the shape and the volume of production
without its people.

**Why this priority**: It is part C of the outline. A move works without it; it makes the
common use of a move, a copy for development, safe to hand over.

**Independent Test**: Make a package with personal data replaced, and search the file for any
email address, phone number or name present in the original records: none is found.

**Acceptance Scenarios**:

1. **Given** "Replace personal data" is ticked, **When** the package is made, **Then** no
   original value of a column marked as personal data is in the file, and no uploaded file is.
2. **Given** the same original value appears in two records, **When** it is replaced, **Then**
   both hold the same made-up value, so that records which belonged together still do.
3. **Given** a package made with personal data replaced, **When** it is given to a live
   install, **Then** it is refused.
4. **Given** a person who may not export personal data, **When** they make a package, **Then**
   "Replace personal data" is ticked and cannot be unticked.

---

### User Story 6 — Several files are imported in one review (Priority: P3)

The owner has three lists from elsewhere. He gives the Import tab the three files together.
CoreX suggests a model for each from its name and its columns, he corrects any it got wrong,
and one trial run reports on all three. He approves the ones he is satisfied with, together.
Rows that are already in a model are reported as such and are not added again.

**Why this priority**: It is the report's "import". It comes last among the three words because
few models accept an import today, so the saving is small until a site declares more.

**Independent Test**: Give two CSV files for two importable models at once, approve both in one
action, then give the same two again: the second trial run reports every row as already there.

**Acceptance Scenarios**:

1. **Given** several CSV files chosen together, **When** they are uploaded, **Then** each is
   shown with the model suggested for it and the person can change it before the trial run.
2. **Given** the trial run, **When** it is shown, **Then** each file has its own counts of
   accepted, already there and rejected, its own unknown columns to map and its own rejection
   report.
3. **Given** a file with rejected rows and one without, **When** the person approves, **Then**
   they can approve the clean one alone or both, and each file is written or not as a whole
   approval of its accepted rows.
4. **Given** a row whose identity (for a subscriber, the email address) is already in the
   model, **When** the trial run reports, **Then** it is counted as already there and approval
   does not add it.
5. **Given** two files given for the same model, **When** the trial run reports, **Then** a row
   in the second that the first already holds is counted as already there.

---

### Edge Cases

- A model is ticked and removed (its add-on switched off) before the export or package is made:
  the others are made, and the result says which was left out.
- The person closes the page while an export or a package is being made or received: it is not
  lost. On a host where scheduled tasks do not run, it continues when the page is opened again,
  from where it stopped.
- A package is larger than the host accepts in one upload: it is sent in parts, as a release
  package is (spec 107).
- A package was made by a newer CoreX than the receiving install runs, or holds a field the
  receiving model does not have: the preview says so per model; a field that is not known is
  left out and named; a model whose required fields are missing is refused.
- A submission's form does not exist on the receiving install: the submission is received and
  shown under the form's key.
- A record holds an uploaded file and the package was made without files: the record arrives,
  and where the file would be it says the file was not carried.
- Receiving stops halfway (a timeout, a full disk): what was added stays, the screen says how
  far it got, and receiving the same package again adds only the rest.
- Two people receive the same package at the same moment: each record is added once.
- A subscriber is in the package and also subscribed here by themselves: they are counted as
  already here and the record here is the one kept.
- The export or package would hold nothing: no file is made, and the screen says there is
  nothing to export.
- The site is one of a network: an export, an import and a package belong to the one site they
  were made on or given to.
- An archive of CSV files is opened on a machine with no tool to open one: out of this spec;
  Excel is offered first for that reason.

## Requirements *(mandatory)*

### Functional Requirements

**Choosing several models**

- **FR-001**: Export, and the making of a data package, MUST let a person choose any number of
  models at once, from a list that shows each model's name and how many records it holds.
- **FR-002**: The list MUST hold only the models the person may use for that action and that
  support it. It MUST say how many models are not offered and point to where each model's
  abilities are explained.
- **FR-003**: The list MUST set apart the records a site receives or keeps for its work
  (submissions, subscribers, applications, a site's own models) from the site's own logs (the
  activity log, login attempts, background jobs, notifications), and MUST offer to tick all of
  either group.
- **FR-004**: Nothing MUST be ticked on the person's behalf.
- **FR-005**: Before anything is made, the screen MUST state, per ticked model, how many
  records will be included, and the file MUST hold that number.
- **FR-006**: The Records tab stays one model at a time. Its export of ticked rows and of the
  filters in force is unchanged.

**An export of several models**

- **FR-007**: An export of several models MUST produce one file: as Excel, one workbook with
  one sheet per model; as CSV, one archive with one file per model; as PDF, one document with
  one section per model.
- **FR-008**: Each sheet, file or section MUST be named for its model, and the file as a whole
  MUST say what was exported, by whom and when, as a single-model export does.
- **FR-009**: PDF MUST be offered only when the records of all ticked models together are
  within what a PDF holds, and the dialog MUST say why when it is not.
- **FR-010**: An export of several models MUST include every record of each model and every
  column the person may export. Narrowing one model's columns MUST be possible and MUST NOT be
  required.
- **FR-011**: A ticked model with no records MUST be left out, and said to be before the
  export. An export in which every ticked model is empty MUST NOT produce a file.
- **FR-012**: The history MUST show an export of several models once, with the models it
  covered, the format, the number of records, its size, who made it, when, and when its file
  expires. It MUST be downloadable again until then and deletable.
- **FR-013**: Every exported file and every data package MUST expire after a retention period,
  after which the file is removed and the entry stays. This applies to an export of one model
  as well.
- **FR-014**: A person MUST see their own exports and packages only, unless they may manage all
  of them.
- **FR-015**: Everything spec 103 requires of the Data export (its FR-048) MUST hold for an
  export of several models: the progress shown, the file saved by the browser when ready, a
  failure said in words and retryable, every format on a site with no optional plugin, and
  right-to-left text correct.
- **FR-015a**: A finished export and a finished package MUST notify the person who made it, as
  an export does today, and the notification MUST open the place where that file is listed.

**Personal data**

- **FR-016**: Each model MUST state, per column, whether it holds personal data and of which
  kind. The models CoreX and its add-ons ship MUST state it truthfully.
- **FR-017**: A model that states nothing MUST be treated as holding personal data in every
  column.
- **FR-018**: Every answer of a form submission MUST be treated as personal data, as the
  Submissions inbox treats it. (Until 2026-10-11 this read "unless the form states that the
  field holds none". The flow builder stores "none" for every field nobody set, so a stored
  "none" is not a statement; and the inbox already withholds every answer.)
- **FR-019**: Every export and every package that includes personal data MUST require a
  confirmation that names the kinds included.
- **FR-020**: Columns holding personal data MUST be offered only to a person allowed to export
  personal data, by the same rule on the Data screen as in the Submissions inbox. That right
  is its own: managing a model does not carry it, administrators hold it, and it MUST be
  something Access & Abilities can give to somebody else. A person without it still exports,
  without those columns.
- **FR-021**: Making, downloading, deleting and receiving an export or a package MUST each be
  written to the activity log with who, which models and how many records, and with no value
  of any record.

**A data package**

- **FR-022**: A model MUST state whether its records can be moved, and what makes a record the
  same record on another install. A model that states nothing MUST NOT be offered for a move.
- **FR-023**: A model MUST state which way its records travel: **born in production** (received
  from visitors or made by the site's work) or **built in development** (made by the team and
  released). A site's own logs are neither and MUST NOT be offered for a move.
- **FR-024**: Form submissions, newsletter subscribers and job applications MUST be movable,
  and MUST be declared as born in production. No model CoreX ships is declared as built in
  development by this spec: the kinds that are (pages, templates, patterns, form definitions,
  settings) get a spec of their own.
- **FR-025**: A package MUST be one file. It MUST hold, for each record, every field the model
  keeps, and MUST record the install it was made on, whether that install was live, when, by
  whom, the CoreX release, each model's shape, and whether personal data was replaced.
- **FR-026**: A package MUST NOT hold a password, a key, a token or any setting.
- **FR-027**: Uploaded files that records point to MUST be included only when the person asks
  for them, and the screen MUST state their number and size before the package is made.
- **FR-028**: Making a package with personal data replaced MUST replace every value of a column
  marked as personal data with a made-up value of the same kind, the same original always by
  the same replacement within one package, and MUST NOT include uploaded files.

**Receiving a package**

- **FR-029**: A package MUST be checked before anything is written: that it is a data package,
  that it is whole and unchanged since it was made, and that this install can read it. One that
  fails MUST be refused with the reason.
- **FR-030**: Before anything is written, the screen MUST state where and when the package was
  made, whether its personal data was replaced, and per model the number of records that are
  new here, already here and the same, already here and different, deleted here, and cannot be
  taken, with each reason.
- **FR-031**: Receiving MUST write exactly what the preview stated, for the person who read it.
  A package or an install that changed in between MUST be previewed again.
- **FR-032**: A record MUST be recognised as already here when it was received before, or when
  it was born here, whatever was edited since; and, where the model states a natural identity
  (a subscriber's email address), when a record with that identity is here.
- **FR-033**: Receiving MUST NOT remove any record, on any install.
- **FR-034**: On a live install, receiving MUST NOT change any record that is already here, and
  MUST NOT offer to.
- **FR-035**: On an install that is not live, the person MAY choose per model to bring records
  that differ up to date. Nothing is brought up to date unless they choose it.
- **FR-036**: A record deleted for good on an install MUST NOT be brought back by a package.
  What the install keeps in order to know this MUST NOT hold personal data.
- **FR-037**: A record whose personal data was removed here by retention or by an erasure MUST
  NOT have it filled again by a package.
- **FR-038**: A live install MUST refuse records of a model born in production from a package
  made on an install that was not live, and MUST refuse any package whose personal data was
  replaced.
- **FR-039**: A record naming a person MUST be matched to an account here by email address.
  With no match the record MUST arrive without that person and the preview MUST count it. No
  account is ever created.
- **FR-040**: Receiving that stops before the end MUST leave what it added, MUST say how far it
  got, and MUST be completable by receiving the same package again.
- **FR-041**: A received record MUST hold where it was born, so that a package made here later
  carries it onward as the same record.

**An import of several files**

- **FR-042**: The Import tab MUST accept several CSV files at once and MUST suggest a model for
  each, which the person can change before the trial run.
- **FR-043**: The trial run MUST report each file separately, and each file MUST keep what an
  import has today: its mapping of unknown columns, its rejection report, and an approval bound
  to exactly the rows the trial run accepted.
- **FR-044**: The person MUST be able to approve any of the files together in one action.
- **FR-045**: An import MUST report a row as already there when the model states what makes a
  record the same record and a record with that identity exists, and MUST NOT add it. This
  applies to an import of one file as well.
- **FR-046**: An import MUST NOT change or remove an existing record.
- **FR-047**: The limits on one CSV file stay as they are and MUST be stated on the tab before
  a file is chosen.

- **FR-048**: Withdrawn. It asked for several table changes applied together on the Migrations
  tab; the owner meant the move. The number is kept so that no other requirement is renumbered.

**Size, and a host with no command line**

- **FR-049**: Every action in this spec MUST complete from the admin alone, on a host with no
  command line and where scheduled tasks may not run.
- **FR-050**: Making and receiving MUST proceed in steps that each fit within the host's limits
  on time and memory, MUST show progress, and MUST survive the page being closed.
- **FR-051**: A package larger than the host accepts in one upload MUST be sent in parts.
- **FR-052**: The screen MUST refuse to start what it can tell will not fit (free disk space,
  the host's limits it can read), and MUST say what would not fit.
- **FR-053**: The largest export and package that are promised MUST be stated in the product's
  documentation as measured numbers, not as estimates.

**Who may do what**

- **FR-054**: Exporting several models MUST require, for each model, what exporting it alone
  requires.
- **FR-055**: Making a package MUST require the right to export each model in it, and the right
  to export personal data unless the personal data is replaced.
- **FR-056**: Receiving a package MUST require the right to manage data models, and on a live
  install the right CoreX reserves for actions that cannot be undone.
- **FR-057**: Every route these actions use MUST declare its protection, as every CoreX route
  does, and a package or an export file MUST NOT be reachable by its address alone.

**Across all of it**

- **FR-058**: Every screen and dialog in this spec MUST meet WCAG 2.2 AA, MUST be operable by
  keyboard alone, and MUST be verified in light and dark, left-to-right and right-to-left, at
  a wide and a narrow width.
- **FR-059**: Every label MUST be translatable, and every count shown MUST be a real count.
- **FR-060**: Everything MUST work on a site with no optional plugin installed.

### Key Entities

- **Model**: one kind of record the Data screen lists. Besides what it states today (its
  fields, what may be done to it), it states which columns hold personal data, whether its
  records can be moved, which way they travel, and what makes a record the same record.
- **Export**: one request for a file to read: who asked, which models, which columns of each,
  the format, the number of records per model, its progress, its file, its size and when it
  expires.
- **Data package**: one file made by CoreX for CoreX: the install and moment it was made, by
  whom, whether that install was live, the release, the models with their shape and records,
  whether personal data was replaced, whether files are included, and what proves it is whole.
- **Receipt**: one receiving of a package on an install: who, when, the preview that was
  confirmed, and per model how many records were added, left as they were, not brought back and
  refused.
- **Record origin**: for a record, the install it was born on and its identity there. It
  travels with the record.
- **Deleted-record note**: that a record of a given origin was deleted for good here. It holds
  nothing of the record.
- **Import batch**: several files given together, each with its model, its trial run and its
  approval.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A person exports five models into one file by going through the dialog once, in
  under one minute, without choosing a model more than once.
- **SC-002**: For every export and every package, the number of records stated per model before
  it is made equals the number in the file.
- **SC-003**: Every lead and every subscriber of one install is on another install after one
  pass on each side, with no step outside the admin, and the number received equals the number
  stated.
- **SC-004**: Receiving the same package a second time adds no record.
- **SC-005**: After any package is received on a live install, every record that existed
  before is unchanged: none changed, none removed.
- **SC-006**: A subscriber confirmed on the first install is confirmed on the second, with the
  day they subscribed, and receives no second confirmation email.
- **SC-007**: A package of 20,000 records is made and received on the first client's shared
  host, with no command line, without a failed request. (A target; planning measures what the
  host holds and FR-053 states it.)
- **SC-008**: In a package made with personal data replaced, a search for any email address,
  phone number or name from the original records finds none.
- **SC-009**: No model that holds a name, an email address or a phone number can be exported
  without the personal-data confirmation.
- **SC-010**: A record erased on a live site is still absent after a package holding it is
  received.
- **SC-011**: Importing the same file twice leaves a model with the records it had after the
  first import.
- **SC-012**: Every action in this spec can be completed with the keyboard alone, and no label
  is shown in English on a translated site because it was not translatable.

## Decided by the owner

He was asked five questions on 2026-10-11, each with a recommendation and its reason, and
answered "as recommended". Each is his to reopen.

1. **"Migrate" meant moving records to another install.** The Migrations tab applies changes to
   a model's table and does not move records; it stays one model at a time. One model ships one
   change today, so applying several together would have saved nothing yet. *Cost*: the third
   dropdown he met is still a dropdown.
2. **A move does not include pages, templates, patterns, form definitions or settings.** They
   were the "built in development" half of part B of the 2026-10-08 outline. They are not
   models on the Data screen; pages and templates reach a site with a release, which is spec
   107 and is not finished; and settings hold keys, which a file that travels should not. This
   spec builds the rule they would follow (which way each kind travels) and applies it to the
   models the Data screen has. *Cost*: a site's content still reaches a host by hand until that
   spec is written.
3. **One rule for taking personal data out in a file: the Submissions inbox's.** The right is
   its own, administrators hold it, and Access & Abilities can give it to others. A person
   without it still exports, without the personal columns, and still makes a package, with the
   personal data replaced. *Cost*: somebody who manages submissions and exported them from the
   Data screen loses the personal columns there until given the right. A client site that
   relies on that has to give it.
4. **On an install that is not live, receiving may bring records that differ up to date**, as a
   choice per model, off unless ticked. Without it a development copy shows a lead as "new"
   that production closed a week ago. A live site is add-only and is offered no such choice.
5. **A package carries uploaded files only when asked**, off by default, with their number and
   size stated first. A host move ticks it. A package with personal data replaced never has
   them. *Cost*: a host move made without ticking it arrives with records whose files are
   missing; each such record says so.

## Assumptions

- **"Live" means the install's operations mode is anything other than Development or
  Staging.** Those two are the only modes that say an install is a copy; Production, Maintenance
  and Coming soon are all states of a real site. An install nobody ever set a mode on has the
  mode CoreX reads from WordPress's own environment type, which is production unless the
  install says otherwise, so the default protects. The receiving screen states which it takes
  the install to be before anything is confirmed, because the protection of User Story 4 hangs
  on it.
- **Where each tool lives.** Export keeps its tab and gains the list. Import keeps its tab and
  takes several files. Making and receiving a package get a place of their own on the same
  screen, named "Move". Planning may place them differently; the three words stay apart.
- **The site's own logs can be exported to read and cannot be moved**: the activity log, login
  attempts, background jobs, notifications and their read state, reading events, access
  requests and role grants. A log is the history of one install, and several of them point at
  accounts and posts by numbers that mean something else elsewhere. Role grants are a setting
  and fall under the second decision.
- **A client site's own models take part by declaring the same things** as CoreX's. One that
  declares nothing stays exportable to read, with every column treated as personal data, and
  is not offered for a move.
- **A package expires after 7 days and an exported file after 30**, each removable sooner by
  the person who made it. A package holds whole records and is meant to be used at once.
- **A package is not locked with a password.** It is a file holding whole records, and it is
  as safe as the place the person keeps it; the screen that makes it says so, and it expires on
  the site after 7 days. A package that opens only with a passphrase is the alternative. It was
  left out because a passphrase that is lost makes a host move fail on the day, and one that is
  written beside the file protects nothing; the owner can ask for it.
- **An archive of CSV files, one per model**, follows what spec 103 chose for several forms.
- **Columns in an export of several models** are every column the person may export, in the
  model's own order.
- **A person is matched by email address** when a record names one. A lead assigned to somebody
  with no account on the receiving install arrives unassigned.
- **Replaced personal data is made up, not scrambled**: an email address becomes another
  address at a domain that cannot receive mail, a name another name. The record's dates, form,
  status and the answers not marked personal are kept.
- **Free text is personal data.** A long answer is replaced whole when personal data is
  replaced; CoreX does not look inside it for a name.
- **The notification CoreX already sends for a finished export is reused**, for an export of
  several models and for a package. It is not sent by email.
- **The trial run's limits per CSV file stay** (2 MB, 5,000 rows) unless planning finds them
  arbitrary. A package has no such ceiling: FR-050 to FR-053 govern it.
- **The Export tab asks for the ability to manage data models while an export itself asks for
  the ability to manage data.** Read from the code. The plan makes the tab ask for what an
  export asks for (its D16).
- **A package's check finds damage, not forgery.** FR-029's "whole and unchanged" is checked
  against what the package says of itself, which catches a broken upload or a damaged file.
  Somebody able to rewrite the file can rewrite that too; refusing a forged package would need
  a secret shared by two installs, which this spec keeps out. Everything read from a package
  is therefore checked as if a stranger had typed it (the plan's D8).

## Out of scope

- Pages, posts, templates, patterns, menus, media library items, form definitions and settings
  (the second decision).
- Sending a package from one install to another over the network. A package is a file a person
  carries. Two installs that talk to each other need credentials kept on both, which is a
  larger decision.
- Scheduled or recurring exports and packages, and files sent by email.
- A backup. A package holds the models that declare themselves movable; it is not the
  database, and restoring a broken site from it is not promised (DECISIONS #282, the fourth
  decision).
- User accounts. No account is created, changed or moved.
- Importing from a format other than CSV, and importing form submissions from a CSV.
- Merging two differing copies of one record field by field.
- Applying several table changes together on the Migrations tab (the first decision).
- The reply emails and the release installer (specs 106 and 107), which the same report named.

## Delivery

In slices, each its own pull request, in this order. Each is useful alone.

1. Personal data marked truthfully and guarded by one rule, on the single-model export that
   exists (User Story 2).
2. Several models in one export, with its history and file expiry (User Story 1).
3. Rows already there are recognised by an import, then several files in one review (User
   Story 6).
4. A model's declarations for a move, and making a package (User Story 3, first half).
5. Receiving a package, with the live-site rule from its first day (User Stories 3 and 4).
6. Personal data replaced (User Story 5).
