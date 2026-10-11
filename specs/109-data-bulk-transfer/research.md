# Research: several data models exported, imported and moved together

What the source does today, read at `2d11bce8` (v0.44.0 plus spec 106's plan), and what is not
known yet. The decisions taken from these facts are in [plan.md](./plan.md), D1 to D18.

## What the source does today

### The screen and its sources

| Fact | Where |
|---|---|
| CoreX → Data is one screen, `corex-data-models`, with five tabs. A tab is shown when the person holds its ability and some source supports its operation | `DataModels/DataModelsScreen.php`, `DataModels/modelClient.js` `DATA_MODEL_TABS` |
| The Export tab asks for the ability to manage data models; an export itself asks for the ability to manage data (tables) or submissions | `modelClient.js`; `Data/TableDataSource.php` `capabilities()`; `Data/SubmissionsSource.php` |
| Sources are form submissions plus one per managed table, built when the registry is first read | `ConfigServiceProvider.php` (the `DataRegistry` binding) |
| Ten tables are managed: eight of CoreX's (role grants, access requests, activity, reading events, jobs, notifications, notification state, login attempts) and two add-ons' (job applications, newsletter subscribers) | `git grep "new ManagedTable("` |
| A table declares its columns, its writable fields with aliases and validation, its policy for unknown CSV columns, its migrations and its insert defaults. Nothing else | `corex-core/src/Database/Schema/ManagedTable.php` |
| Every column of every managed table is given the personal-data class `none` | `Data/TableDataSource.php` `fields()` |
| A submission's answers are classed by guessing from the answer's type: email and phone as contact, long text as content, everything else as none | `Data/SubmissionsSource.php` `fields()` |
| A form field can declare a personal-data class, from a list of its own (`none`, `contact`, `identity`, `consent`, `sensitive`, `custom`) that is not `DataField`'s. The flow builder stores `none` for a field nobody set, so an undeclared field and one declared as holding none read the same. The Data source does not read it | `corex-forms/src/Schema/FieldSchema.php`, `SchemaResolver.php`; `corex-config/src/Forms/components/FieldSettings.js`, `flowEditor.js` |
| The Submissions inbox's export counts every answer as personal data, whatever its field says | `Submissions/SubmissionExportRequest::includesPersonalData()` |

### Export

| Fact | Where |
|---|---|
| A Data export is one run for one source: a private post of type `corex_data_export` with its request in post meta | `DataModels/WpDataExportStore.php`, `DataExportRun.php` |
| Its routes are scoped to the source, `/data/{source}/exports…`, and a download is refused when the stored source differs from the route's (DECISIONS #121) | `Data/DataManagementController.php`, `DataExportService::download()` |
| `/data/{source}` matches any word, so a fixed path under `/data/` has to be registered before it. `migrations` is | `DataManagementController::register()` |
| The export is a bounded job of kind `data.export`, 100 records a step. A step runs when the scheduler fires or when the open dialog asks for one (`…/advance`), under a lock | `DataExportJobHandler.php`, `WpDataExportJobQueue::advance()`, `Jobs/JobRunner.php` |
| Records are gathered into a working file of one record per line, and the file is written in the last step | `DataModels/DataExportFiles.php`, `Export/ExportSpool.php` |
| A document is a title, a list of sheets and a list of facts. The Excel writer writes a sheet per sheet, the CSV writer one file for one sheet and a zip of files for several, the PDF writer a named section per sheet | `Export/ExportDocument.php`, `XlsxExportWriter.php`, `CsvExportWriter.php`, `PdfExportWriter.php` |
| A PDF holds at most 500 records and is written in one request | `Export/ExportWriters.php` `PDF_MOST_RECORDS` |
| Files are written under protected uploads, `exports/`, or the system's temporary directory where uploads cannot be written | `Export/ProtectedExportDirectory.php` |
| Personal data in an export is checked once: the request must carry an acknowledgement when any chosen column has a class other than `none`. No ability is asked for it | `DataExportService::validateColumns()` |
| The Submissions inbox's export withholds personal columns from a person whose scope says they may not export personal data; by default that is anybody who is not an administrator, through a filter | `Submissions/WpSubmissionAccessPolicy.php`, `SubmissionExportService.php` |
| The Submissions export's files are removed after 30 days by the daily sweep, which removes from every store that registers with it. The Data export's store is not one of them and has no way to remove a file | `Submissions/SubmissionExportRetention.php`, `Retention/PrunableStore.php`; `git grep unlink DataModels` finds nothing |
| A finished job whose kind ends in `.export` notifies the person who started it, with a link to the screen it came from. For a Data export the link is the Data screen with no tab named | `Notifications/Producers/ExportReadyNotificationProducer.php` |

### Import and migrations

| Fact | Where |
|---|---|
| An import is one CSV for one source: at most 2 MB, 5,000 rows and 200 columns | `DataModels/DataCsvParser.php` |
| The trial run maps columns, validates each row against the writable fields and keeps the accepted and rejected rows as an immutable run. Approval is bound to that run's hash and its actor (DECISIONS #120) | `DataImportPlanner.php`, `DataImportService.php` |
| Approval creates every accepted row. Nothing looks for a record that is already there, and the subscribers table has no unique key | `DataImportJobHandler::write()`, `Data/ManagedTableWriteAdapter::create()`, `corex-newsletter/src/Subscriber/SubscriberTable.php` |
| A created row gets the table's insert defaults: an imported subscriber is `pending` | `ManagedTable::insertDefaults()` |
| A migration is a table change a managed table declares; applying one copies the table first. One exists | `DataModels/MigrationService.php`, `SubscriberTable.php` |
| An older import handler, a form post kept in a transient, is still in the source beside the REST one | `DataModels/DataModelsImportController.php` |

### Submissions

| Fact | Where |
|---|---|
| A submission is a private post of type `corex_submission`. Its answers, form, status, owner, read state, notes, timeline, consent record, campaign data and hidden metadata are post meta | `Data/WpSubmissionsReader.php` |
| The reader projects a `uuid` from the meta `corex_submission_uuid`, falling back to `submission-<id>`. Nothing writes that meta, so every submission's uuid is the fallback | `WpSubmissionsReader.php:512`; `git grep corex_submission_uuid` finds that one line |
| A flow submission stores the ids of its flow and flow version, which are this install's own numbers, and a snapshot of the flow's label | `WpSubmissionsReader.php` (`corex_flow_id`, `corex_flow_version_id`, `corex_flow_label_snapshot`) |
| An uploaded file is an attachment marked `_corex_protected`; the answer holds the attachment's id | `Submissions/WpSubmissionTrashStore::uploadsOf()`, `corex-core/src/Security/Upload/AttachmentStore.php` |
| Deleting for good is `wp_delete_post()` from the trash, after the files and email copies are removed | `WpSubmissionTrashStore::delete()`, `SubmissionTrashService::erase()` |
| Retention's anonymising removes the answers and sets `corex_retention_state` to `anonymized` | `WpSubmissionsReader::anonymizeForRetention()` |

### What a move can reuse

| Fact | Where |
|---|---|
| A release package is received in parts: the browser hashes the file, sends parts that say where they start, and the site appends them to a file named for the hash and checks the hash at the end. An upload that was cut off resumes | `Releases/ReleaseUpload.php`, `releaseClient.js`, `sha256.js` |
| That class is written for releases: its directory, its largest size and the name a package must have are its own | `ReleaseUpload.php` |
| The host's free space and whether a folder can be written are read by a class of the release installer | `Releases/ReleaseHostFacts.php` |
| The mode an install is in is an option; where it is not set it is WordPress's environment type, which is `production` unless the install says otherwise | `Operations/OperationsModeStore::current()` |
| An administrator holds every CoreX ability through `manage_options`, and a role can be denied one. An ability is declared in one catalog and can then be given on Access & Abilities | `Access/AbilityCompatibility.php`, `corex-core/src/Access/CorexAbilityCatalog.php` |
| A job carries its site, and a job step runs inside that site's scope | `Jobs/CronJobDispatcher.php`, `JobRunner::run()` |
| `ZipArchive` is used by the Excel and CSV writers and by the release installer, which says so when PHP lacks it | `Export/*.php`, `Releases/ReleaseHostFacts.php` |

## What is not known, and when it will be

- **How large a package the first client's host makes and receives.** Its limits were reported
  by that site's session for spec 107 and not measured here. SC-007's 20,000 records is a
  target. Slice 5 measures on a local install held to 128 MB and 30 seconds a request; the
  number on the host itself needs one run there, which is the owner's or that site's session's.
- **Whether `ZipArchive` is present on that host.** Spec 107 assumes it and says so on its
  screen. The Move tab does the same.
- **How a flow is recognised on another install.** A flow has an id that is local. Whether it
  also has a key that travels with a release was not read. Slice 4 reads it; until then the
  plan matches a submission to its form by the form's slug and keeps the flow's label snapshot.
- **Which of an add-on's own code deletes a subscriber or an application.** A record deleted
  for good must leave a note (D7). Deleting through the Data screen and through the
  Submissions trash is covered by the plan; an add-on's own delete path is found in slice 5.
- **Whether a client site declares a table of its own.** Read on 2026-10-11: the first client
  site's own code declares none. A site that does is covered by the same declaration.

## Alternatives that were weighed and left

| Alternative | Why it was left |
|---|---|
| Give an export a format that imports back, and make a move "export, then import" | An import writes only declared writable fields and sets the rest; a move has to carry the status, the dates and the notes. One file cannot be both written for a person and complete for a machine (spec, "Three words") |
| Name a record on another install by the install and its number there | A database copied to another host carries the install's id with it, so two installs would name different later records alike. D6 names a record by an identifier of its own |
| Add an identifier column to every movable table | A schema change on every table that declares itself movable, add-ons' and client sites' included. One ledger table holds it for all of them, and holds the note of a deleted record, which has no row to carry a column |
| Sign a package so a changed one is refused | Two installs would need a shared secret, which this spec keeps out (Out of scope: installs that talk to each other). D8 checks a package for damage and treats every value in it as untrusted input |
| Keep the classification of every record from the preview in the database | 20,000 records times five states. D11 keeps the identifiers of the records to add in a working file beside the package, and re-checks each as it is written |
| A server-side "import batch" entity | The trial run and its approval are already one immutable run per file. Several files are several runs that the screen shows together; the one thing the server has to know is that a later run should count an earlier one's rows as present (D15) |
| One job engine step size for everything | A package step copies files as well as records. D17 gives the package jobs a time budget per step rather than a record count |
| Lock a package with a passphrase | Left out in the spec's assumptions, for the reason given there; the owner can ask for it |
