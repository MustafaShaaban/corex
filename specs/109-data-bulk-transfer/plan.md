# Implementation Plan: Several data models are exported, imported and moved together

**Branch**: `spec/109-data-bulk-transfer` | **Date**: 2026-10-11 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/109-data-bulk-transfer/spec.md`, with the owner's
five answers of 2026-10-11.

**How this was written**: with `/speckit-plan`, from the source at `2d11bce8`. What the source
does today is in [research.md](./research.md); the entities are in
[data-model.md](./data-model.md); the routes, the package's format and a model's declaration
are in [contracts/](./contracts/); how each slice is shown to work is in
[quickstart.md](./quickstart.md).

## Summary

A model states three things it does not state today: which of its columns hold personal data,
what makes a record the same record, and whether and which way its records travel. Everything
else reads those statements.

- **Export** takes a list of models where it took one. A run holds a part per model, one job
  gathers them, and the writers that already write several sheets write one file.
- **Import** recognises a row that is already there, and the Import tab reviews several files
  together; each file stays the immutable run it is today.
- **Move** is new: a data package made in steps from whole records, received in parts as a
  release is, previewed, and written by a job that only adds on a live install. A ledger gives
  every moved record an identifier of its own and remembers what was deleted for good.

Delivered in six slices, each its own pull request, in the order under "Slices".

## Technical Context

**Language/Version**: PHP 8.3; JavaScript (ES2022) through `@wordpress/scripts` 36

**Primary Dependencies**: WordPress 7.0; PHP's `ZipArchive`, already used by the Excel and CSV
writers and detected by the release installer (without it the Move tab says so and Excel and
several-model CSV are not offered); nothing new from Composer or npm

**Storage**: one new table of CoreX's own, `corex_record_origins` (D6), created by the
migration runner; export runs stay private posts (`corex_data_export`), and package runs and
receipts are private posts of two new types; files under protected uploads, `exports/` as
today and `data-packages/` for packages

**Testing**: Pest unit for the policies, the planner, the replacer and the package's format,
over real zips in the system temp directory; Pest integration on real WordPress for the
routes, the jobs, the ledger and two-install round trips (two table prefixes in one database);
Jest for the screens' state; Playwright for the Export, Import and Move tabs

**Target Platform**: shared hosting with no shell, where scheduled tasks may not run; a single
site first, and one site of a network at a time

**Project Type**: WordPress plugin (`plugins/corex-config`), with declarations in
`plugins/corex-core` and in the two add-ons that ship a table

**Performance Goals**: SC-001 (five models, one pass, under a minute); SC-007 (a package of
20,000 records made and received with no failed request). Each request of a package job stays
under a third of the host's PHP time limit and under 10 seconds

**Constraints**: every step is one request the open page can ask for (no reliance on a
scheduler); nothing on a live install is changed or removed by a package; a package's values
are untrusted input; no file is reachable by its address; memory stays flat in the number of
records (records are read and written a batch at a time, never held whole)

**Scale/Scope**: eleven models on a site with both table add-ons; three of them movable; the
first client's site holds leads in the hundreds

## Decisions

Each says what is done, why, and what was left. Facts they rest on are in research.md.

### D1. A model's three statements live where its other statements live

`ManagedTable` gains optional, named arguments for the three statements, each read-only by
default like the ones it has (contracts/model-declaration.md):

- `personalData`: a class per column, from the classes `DataField` already has.
- `identity`: the columns that make a record the same record (subscribers: `email`).
- `transfer`: nothing (the default: not movable), or the kind, `born_in_production` or
  `built_in_development`. With it, `transferFiles` names the columns that hold an uploaded
  file and `transferPeople` the columns that hold an account's number.

A fourth, `category`, sorts a model into the checklist's two groups, records or logs
(FR-003); left out, a table that is writable or movable is records and any other a log.

`TableDataSource::fields()` reads `personalData` where it wrote `none`. A table that declares
no `personalData` at all has every column classed `undeclared`, a new class that every check
treats as personal (FR-017). The eight tables CoreX ships and the two add-ons' declare theirs
in the same slice, or the activity log would start asking for the personal-data right.

Form submissions are not a table. `SubmissionsSource` reads each answer's class from the
form's field where the form declares one, and classes the rest `content` (FR-018). So that
"declared as holding none" can be told from "not declared", the forms schema keeps the class
as it was written and resolves an absent one to `undeclared`, not to `none`.

*Left*: a second registry of declarations beside the tables. A table's statements about itself
were put in one record on purpose (spec 074).

### D2. Moving is a source's ability, like exporting

A new interface in `Corex\Config\Data`, `TransferableDataSource`: its declaration, a count, the
next batch of whole records after a cursor, and receiving one record. `DataSourceCapabilities`
gains `transfer`; the catalog the screen reads carries it, with the reason when a model cannot
be moved.

Two implementations: one for any managed table that declares `transfer`, over the reader and
writer the Data screen already uses, and one for submissions over the forms store. A whole
record is every column the table has, not the writable ones: a moved subscriber keeps
`status` and `created_at`.

### D3. One right to take personal data out

A new ability, `corex_export_personal_data`: group data, risk sensitive, implied by nothing but
the admin ability, so an administrator has it and Access & Abilities can give it. Three places
ask it:

- the Data export refuses a personal column from a person without it, and the catalog tells
  the dialog not to offer the column;
- the Submissions inbox's `canExportPersonalData` defaults to it. Its filter stays, so a site
  that filters today is not changed;
- making a package without it forces personal data to be replaced (D13). Before slice 6,
  which builds the replacing, a person without it cannot make a package at all.

The acknowledgement stays as it is, and now names the classes included.

*Client impact, to be in the changelog*: a person who manages submissions and is not an
administrator loses the personal columns on the Data screen until given the right (the third
decision's cost).

### D4. An export run holds parts

`DataExportRun` holds a list of parts, each a source, its columns and its counts. A run with
one part is today's export, with its scope and its selected rows; a run with several takes
every record of each. A stored run from before reads as one part.

One job, kind `data.export` as today, with the total of all parts; its cursor is a part and an
offset. Each part gathers into its own working file, and the last step hands the writers one
document with a sheet per part. The facts a document already carries gain a line per model.
PDF is offered while the total is within `PDF_MOST_RECORDS`.

The routes for several models are not under `/data/{source}`, which matches any word. They are
`/data-exports…` (contracts/routes.md). The per-source routes stay and list the runs that hold
a part for that source, so DECISIONS #121's binding of a download to its source holds.

### D5. Export files expire, and can be deleted

`DataExportRetention`, a `PrunableStore` of 30 days, as the Submissions export has; the store
learns to remove a file and to find the runs still holding one. The history shows size and
expiry and offers delete, which is written to the activity log. The notification for a
finished Data export opens the Export tab, where it opened the screen's first tab.

### D6. A record's identifier is its own, kept in one ledger

A new table, `corex_record_origins`: the model, the record's identifier (a UUID), the record's
number here or none, the install it was born on, the receipt that brought it or none, and a
state: here, or deleted for good.

- A record gets its identifier the first time it is packaged or received. It is derived from
  the install's own identifier, the model, the record's number and the moment it was created,
  so two installs that are copies of one database give the same record the same identifier
  without having spoken.
- An install's identifier is a UUID in an option, made on first use, kept with the address it
  was made for. When the site's address is no longer that one, the install is a copy: it makes
  a new identifier, keeps the old one with the moment of the change, and derives a record's
  identifier from the one in force when the record was created. Records born on the copy after
  that cannot collide with the original's.
- The pair of model and identifier is unique. That key is what makes "received twice adds
  nothing" and "two people at once" hold: a record is claimed in the ledger before it is
  created.
- Submissions use the ledger like every other model. The `uuid` the inbox reader projects is
  a fallback nothing ever wrote; it is left alone.

*Left*: an identifier column on every movable table (research.md).

### D7. A record deleted for good leaves a note

When a movable record is deleted for good, its ledger row stays with the state "deleted" and
without the record's number. It holds an identifier and a model, nothing of the record
(FR-036). A record that had no ledger row yet gets one at that moment.

Deleting is caught where it happens: for submissions on WordPress's own delete of a
`corex_submission`, which the trash's "delete for good" and its expiry both end in; for tables
in the write adapter the Data screen deletes through. An add-on that deletes its own rows
calls the same service; slice 5 finds each such place.

### D8. The package is one zip that describes itself

`corex-data-package.json` at its root (contracts/data-package.md): the format's version, when
and by whom it was made, the install (identifier, address, mode, whether live), the CoreX
release, whether personal data was replaced, whether files are inside, and for each model its
key, kind, shape, count and the name, size and SHA-256 of its records file. Records are one
JSON object per line, `models/<key>.ndjson`. Files are `files/<record>/<n>-<name>`, each with
its hash in the record that points to it.

It holds no setting, key or password, and no account: a person is named by email address
where a record names one, and that is personal data like any other.

A check finds damage: a member whose hash is not the manifest's refuses the package. It does
not find forgery, and the plan does not pretend to: somebody able to rewrite the zip can
rewrite the manifest. So everything read from a package is validated against the receiving
model's shape and sanitised as if a stranger had typed it, entry names are checked for
traversal before anything is read, and receiving asks for the abilities of D16.

### D9. A package is made by a job, in steps

Kind `data-package.export`, so the notification that recognises `.export` covers it. A run is a
private post of type `corex_data_package`. Steps: gather each model's records into its file
(replacing personal data as they pass, D13), add files if asked, write the manifest, close.
A step stops on a time budget (D17), not on a count.

Before it starts, the screen states the records per model and, when files are asked for,
their number and size, read from the attachments; the host's free space is checked against
twice that.

A package expires after 7 days (`DataPackageRetention`) and can be deleted sooner.

### D10. A package arrives in parts, by the release's mechanism

`ReleaseUpload` is the mechanism, written for releases. It is split: a `PartedUpload` that
knows a directory, a largest size and a name's pattern, and the release's own thin use of it,
with its surface and its tests unchanged. `DataPackageUpload` is the second use. The browser
side (`releaseClient.js`'s upload and `sha256.js`) moves to a shared module the same way.

Spec 107 is in flight. Its slice 3, which these files belong to, is merged and complete; the
split is announced to that session before the files are touched, and is its own commit.

### D11. Receiving is a preview, then a job that writes what the preview listed

**Inspect**, in steps: the manifest, the members' hashes, each model against the model here
(D12), then each record classified: new, here and the same, here and different, deleted here,
cannot be taken with its reason. The counts go on a receipt, a private post of type
`corex_data_receipt`, in the state "previewed". The identifiers of the records to add, and of
those to bring up to date where that was chosen, go to a working file beside the package.

**Confirm** is bound to the receipt, its actor and the package's hash, and expires after 30
minutes. The other previews last five; this one is read model by model before it is confirmed.

**Receive**, kind `data-package.receive`: for each listed identifier, claim it in the ledger,
create the record through the model's `receive`, attach its files, complete the claim. A
record that turned up here since the preview is skipped and counted as "arrived since". A step
that dies leaves claims the next run of the same package completes.

The rules of what may be done to a record are one pure class, `ReceivePolicy`, so the
live-site rule is tested without WordPress: given whether this install is live, the package's
install was live, its personal data was replaced, the model's kind, the record's state here
and the person's choice, it answers add, leave, bring up to date or refuse, with the reason.

### D12. Shapes are compared, not assumed

The manifest carries each model's fields. A field the receiving model does not have is left
out and named in the preview; a required field the package lacks refuses the model; a model
not present here refuses that model alone. A package from a newer format version than this
install reads is refused whole, with both versions said.

### D13. Replaced personal data is made up, the same way each time, and cannot be undone

`PersonalDataReplacer`, pure: a class, a type and an original value give a replacement from a
keyed hash whose key is random per package and is never written anywhere. An email address
becomes an address at `example.invalid`; a name, "Person" and eight characters; a phone
number, a number that cannot be dialled; free text, a line saying text was replaced and how
long it was; anything classed sensitive or security, and every file, is dropped. `undeclared`
is treated as free text.

For submissions the answers, the submitter's name and email, the notes, the hidden metadata
and the consent record all pass through it.

The same original gives the same replacement inside one package and a different one in the
next, so two packages cannot be joined on a made-up value.

### D14. Live is read from the operations mode, and said before anything is confirmed

Live is any mode other than Development or Staging. An install nobody set a mode on has
WordPress's environment type, which is `production` unless the install says otherwise: the
default protects. The manifest records it at making; the receiving screen states what it takes
this install and the package's install to be, above the confirmation.

### D15. An import knows a row that is already there, and several files are several runs

A source that declares `identity` answers which of a list of identities it holds, in one
prepared query per batch. The planner classes such a row "already there", neither accepted nor
rejected; so is a row whose identity an earlier row of the same file has. An email identity is
compared lowercased and trimmed. This applies to a single file too (FR-045).

Several files stay several runs: the screen uploads each to its model's existing route, shows
the trial runs together and approves the chosen ones one after another. The server learns one
thing: a trial run may name earlier runs of the same model and person, and counts their
accepted rows as already there (the fifth acceptance scenario of User Story 6). The model
suggested for a file is worked out in the browser from the file's name and its first line
against each model's fields and aliases.

### D16. Who

| Action | Asked for |
|---|---|
| Export several models | per model, what exporting it alone asks; personal columns ask D3's right |
| Make a package | per model, the export right; D3's right, or personal data is replaced |
| Receive a package | manage data models; on a live install, also the right to run dangerous actions |
| See another person's exports, packages or receipts | the admin ability. Today the Data export's history shows a person their own and nobody anybody else's |

The Export tab asks for what an export asks for (managing data, or submissions), where it
asked for managing data models. The Move tab is shown to a person who can do either half of
it. Every route goes through the Data gateway, which applies the nonce, the ability and the
sanitising; the screen through `AdminGuard`.

### D17. A step fits the host

The export job keeps its 100 records a step. The package jobs stop a step when a third of the
PHP time limit, or 10 seconds, has passed, and at 50 records or 16 MB of files whichever is
first. Nothing holds a model in memory: records are read by cursor and written a line at a
time, and a file is copied by stream.

The open page drives every job through an advance route, as the export dialog does. A page
that is closed leaves the job where the scheduler, if there is one, carries on; the Move and
Export tabs list what is unfinished with "Continue". Free space and the ability to write are
read before a start, with the release installer's host facts.

Slice 5 measures a package of 20,000 records, with and without files, on an install held to
128 MB and 30 seconds, and the guide states the numbers (FR-053).

### D18. The record of it

Each of made, downloaded, deleted, received and refused is an activity event in the data
models area, marked as personal where personal data was included, with the models and counts
and no value. A receipt is kept and listed on the Move tab: who, when, from which install, and
per model what was added, left, not brought back and refused.

## Constitution Check

| Principle | How this plan meets it |
|---|---|
| Spec before code (X) | The spec with the owner's answers, and this plan, before the first slice. |
| Theme is a skin (I), plugins boot themselves (II) | Nothing is in a theme. The jobs run in admin, REST and cron contexts; the ledger's table is created by the migration runner. |
| Thin controllers, logic in services (III) | Three new controllers map a route to one service call. Policies, the planner and the replacer are pure; the ledger, the stores and the table reader and writer are the only places that touch the database. |
| Everything injected (IV) | Every service is bound in `ConfigServiceProvider`; the transfer adapters receive the reader, writer and stores they use. |
| Runtime tokens (V), conditional assets (VI), RTL first (VIII) | The tabs use the admin shell's tokens and logical properties and load with the Data screen's own script. |
| Security declarative (VII) | Routes through the Data gateway's pipeline; the screen through `AdminGuard`. Files leave only through a route that checks who asks. A package's content is validated and sanitised as input, entry names checked before reading, queries prepared. |
| No optional dependency is hard (IX) | `ZipArchive` is detected and its absence said. Nothing needs an optional plugin; the newsletter and careers declarations live in their add-ons. |
| i18n, WCAG 2.2 AA | Every string through `corex`; progress and counts are announced; the checklist is a labelled group with one control to tick all; confirmation is a labelled field. |
| Tests | Under each slice in tasks.md, written first. |
| Guard Gate, UI/UX gate | Each slice through its guards; each tab measured in both themes and both directions before it is presented. |

No violation to justify.

## Project Structure

```text
specs/109-data-bulk-transfer/
├── spec.md, plan.md, research.md, data-model.md, quickstart.md
├── contracts/routes.md, data-package.md, model-declaration.md
└── checklists/requirements.md

plugins/corex-core/src/
├── Database/Schema/ManagedTable.php          # personalData, identity, transfer (D1)
├── Data/DataField.php                        # the class "undeclared"
├── Data/DataSourceCapabilities.php           # transfer
└── Access/CorexAbility.php, CorexAbilityCatalog.php   # corex_export_personal_data (D3)

plugins/corex-forms/src/Schema/               # a field's class kept as written (D1)

plugins/corex-config/src/
├── Data/
│   ├── TransferableDataSource.php, TransferDeclaration.php, TransferRecord.php   # D2
│   ├── TableTransfer.php, SubmissionsTransfer.php                                # D2
│   ├── IdentityAwareDataSource.php                                               # D15
│   ├── TableDataSource.php, SubmissionsSource.php, DataSourceService.php         # D1, D3
│   └── DataAccessPolicy.php, WpDataAccessPolicy.php                              # D3
├── DataModels/
│   ├── DataExportRun.php, DataExportRequest.php, DataExportService.php,
│   │   DataExportJobHandler.php, DataExportFiles.php, WpDataExportStore.php      # D4
│   ├── DataExportRetention.php, DataExportSetController.php                      # D4, D5
│   ├── DataImportPlanner.php, DataImportService.php                              # D15
│   ├── ExportPanel.js, ImportPanel.js, DataExportDialog.js, ModelChecklist.js    # the tabs
│   └── MovePanel.js, PackageMaker.js, PackageReceiver.js, moveClient.js
├── Transfer/                                                                     # new
│   ├── InstallIdentity.php, RecordOrigins.php, RecordOriginsTable.php            # D6, D7
│   ├── DataPackageManifest.php, DataPackageWriter.php, DataPackageReader.php     # D8, D12
│   ├── DataPackageRun.php, DataPackageStore.php, DataPackageJobHandler.php       # D9
│   ├── DataPackageRetention.php, DataPackageUpload.php                           # D9, D10
│   ├── PersonalDataReplacer.php                                                  # D13
│   ├── ReceivePolicy.php, LiveInstall.php                                        # D11, D14
│   ├── DataReceipt.php, DataReceiptStore.php, DataPackageInspector.php,
│   │   DataReceiveJobHandler.php                                                 # D11
│   └── DataPackageController.php, DataReceiptController.php
├── Upload/PartedUpload.php                   # split out of Releases/ReleaseUpload.php (D10)
├── admin/upload/partedUpload.js, sha256.js   # split out of Releases/ (D10)
├── Notifications/Producers/ExportReadyNotificationProducer.php   # the tab, the package kind
└── ConfigServiceProvider.php

addons/corex-newsletter/src/Subscriber/SubscriberTable.php    # personalData, identity, transfer
addons/corex-careers/src/Application/ApplicationTable.php     # personalData, transfer
docs/en/03-operations/, docs/ar/03-operations/                # exporting, importing, moving
docs/en/06-cookbooks/                                         # declaring a model
tests/Unit/{Data,DataModels,Transfer}/, tests/Integration/{Data,Transfer}/,
tests/e2e/data-management.spec.js, tests/e2e/data-move.spec.js
```

**Structure Decision**: the move is a module of its own in corex-config, `Transfer/`, beside
`DataModels/`, which keeps export, import and migrations. `DataManagementController` already
carries nineteen routes and is not given more: each new surface has its own controller.

## Slices

Each is one pull request and leaves `main` releasable. No slice before the fifth writes a
record on another install.

| # | Stories | What lands |
|---|---|---|
| 1 | US2 | The three declarations on `ManagedTable` and their reading; every shipped table's personal data declared; submissions' answers classed from the form; the ability and its three uses; the acknowledgement naming classes. Nothing new on screen but the truth. |
| 2 | US1 | A run with parts, the job over parts, the `/data-exports` routes, the checklist and the dialog for several models, the history with size, expiry and delete, the 30-day expiry, the notification's link. |
| 3 | US6 | `identity` read by the planner, "already there" in the trial run and its report, a run naming earlier runs, and the Import tab taking several files. |
| 4 | US3 (making) | The ledger and the install's identifier, the transfer interface with its two implementations, the package's format and writer, the making job, its expiry, the Move tab's first half. A package can be made and downloaded; nothing receives one yet. |
| 5 | US3 (receiving), US4 | The split of the upload in parts, the inspector, `ReceivePolicy`, the receipt, the receiving job, the note of a deleted record, the Move tab's second half, the receipts list, the measurements and the guide. |
| 6 | US5 | `PersonalDataReplacer`, the tick that is forced without the right, the refusal on a live install. |

Slice 1 goes first because a file of several models on today's marking would hand every
subscriber to a person never asked. Slice 6 is last and small. Until it lands a package always
holds the original personal data, so only a person with the right of D3 can make one, and a
package is not something to hand to a developer; the Move tab says both.

## Risks

- **A copied database on the same address.** D6 tells an original from a copy by the site's
  address. A copy served at the original's address (a local copy behind a hosts entry) keeps
  the install's identifier, and a record born there can take an identifier a later record of
  the original takes: same model, same number, same second. On a live install the cost is one
  record wrongly left out, never one overwritten; the preview counts it as already here.
- **A package is as private as where it is kept.** It is not encrypted (the spec's
  assumption). It expires on the site; a copy on a laptop does not.
- **A flow's numbers are local.** A moved flow submission keeps its form's slug and its flow's
  label; whether it can be tied to the flow on the receiving install is read in slice 4
  (research.md).
- **Delivery history does not travel.** A submission's email attempts live in the mail log's
  own table. A moved lead arrives without them, and its pane says the history is on the
  install it came from.
- **The split of `ReleaseUpload` touches a file of spec 107**, which another session is
  building. It is announced first, changes no behaviour, and keeps that class's tests green.
- **The personal-data right changes who sees what today** (D3). It is in the changelog's client
  impact and is said to both client sites' sessions before the release that carries slice 1.
- **The number promised for a package is measured on a development machine** held to a shared
  host's limits, not on the host. One run on the first client's host is asked of that site's
  session before the guide states a number as the host's.
- **A model declared movable by a client site is written to by a package.** A wrong `identity`
  there doubles or drops records. The cookbook says how to test a declaration with a round
  trip, and the preview is the place the mistake shows before anything is written.
