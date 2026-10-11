# Tasks: Several data models are exported, imported and moved together

Tests are written first and seen to fail. One slice per pull request, in this order; each
leaves `main` releasable. Decisions are in [plan.md](plan.md) (D1 to D18), what the source does
today in [research.md](research.md), the routes and formats in [contracts/](contracts/), and
how a slice is shown to work in [quickstart.md](quickstart.md).

A task marked `[P]` touches files no other open task of its slice touches. Every slice ends
with its guards, its docs, `CHANGELOG.md`, `PROGRESS.md` and `DECISIONS.md`.

There is no setup phase: the plugin, its suites and its build exist. What every later slice
stands on is slice 1.

## Slice 1: personal data is marked where it is, and asked about wherever it leaves (US2; FR-016 to FR-021; D1, D3)

**Goal**: a model says which of its columns hold personal data; the Data export and the
Submissions inbox ask the same right for them.

**Independent test**: export newsletter subscribers alone. The dialog names the email column
as personal data and will not continue without the confirmation; a person not allowed to
export personal data is not offered that column, and the route refuses it.

**The declaration (corex-core)**

- [ ] T001 [P] [US2] Pest unit, `tests/Unit/Data/ManagedTableDeclarationTest.php`: a table
      with no `personalData` answers `undeclared` for every column; one that names some
      columns answers the declared class for those and `undeclared` for the rest; a class that
      is not one of `DataField`'s, and a column that is not the table's, are refused when the
      table is constructed; `declaresPersonalData()` is false only when nothing was declared
- [ ] T002 [P] [US2] Pest unit, `tests/Unit/Data/DataFieldPersonalDataTest.php`:
      `DataField` accepts the class `undeclared`; `DataField::holdsPersonalData()` is false
      for `none` and true for every other class, `undeclared` among them
- [ ] T003 [US2] `DataField::PERSONAL_UNDECLARED` and `holdsPersonalData()` in
      `plugins/corex-core/src/Data/DataField.php`; the named argument `personalData`,
      `personalDataClass(string $column)` and `declaresPersonalData()` in
      `plugins/corex-core/src/Database/Schema/ManagedTable.php`

**The right (corex-core)**

- [ ] T004 [P] [US2] Pest unit, `tests/Unit/Access/CorexAbilityCatalogTest.php`:
      `corex_export_personal_data` is in the data group, rated sensitive, not locked; whoever
      manages the admin holds it; managing data, data models or submissions does not carry it
- [ ] T005 [US2] `CorexAbility::EXPORT_PERSONAL_DATA` in
      `plugins/corex-core/src/Access/CorexAbility.php` and its entry in
      `plugins/corex-core/src/Access/CorexAbilityCatalog.php`, implied by the admin ability only

**What each shipped model declares**

- [ ] T006 [P] [US2] Pest unit, `tests/Unit/Data/ShippedTablesPersonalDataTest.php`: each of
      the ten managed tables CoreX and its add-ons ship declares a class for every one of its
      columns (none is `undeclared`); subscribers' `email` is contact; an application's `name`
      is identity, its `email` contact and its `cv_attachment` sensitive; a login attempt's
      `user_id` is security; the activity log's `actor_label` is identity
- [ ] T007 [US2] `personalData` on the eight tables of `plugins/corex-config/src/`:
      `Access/AccessTables.php` (both), `Activity/ActivityTable.php`,
      `Blog/ReadingEventTable.php`, `Jobs/JobTable.php`,
      `Notifications/NotificationTable.php`, `Notifications/NotificationUserStateTable.php`,
      `Security/LoginProtection/LoginAttemptTable.php`; classes as in data-model.md
- [ ] T008 [P] [US2] `personalData` on
      `addons/corex-newsletter/src/Subscriber/SubscriberTable.php` and
      `addons/corex-careers/src/Application/ApplicationTable.php`

**The sources read it (corex-config)**

- [ ] T009 [P] [US2] Pest unit, `tests/Unit/Data/TableDataSourceTest.php`: a source's fields
      carry the table's declared classes; a table that declares nothing gives every field
      `undeclared`; the same for a writable table's source
- [ ] T010 [P] [US2] Pest unit, `tests/Unit/Data/SubmissionsSourcePersonalDataTest.php`: every
      answer field is personal data: an answer keyed like an email address or a phone number
      is contact, any other answer content, none is `none`; `date` and `form` are `none`
- [ ] T011 [US2] `plugins/corex-config/src/Data/TableDataSource.php` `fields()` reads
      `ManagedTable::personalDataClass()`; `plugins/corex-config/src/Data/SubmissionsSource.php`
      classes every answer as contact or content

**One rule where data leaves**

- [ ] T012 [P] [US2] Pest unit, `tests/Unit/Data/DataSourceServiceTest.php` (new) or the
      catalog cases of `DataRegistryCapabilitiesTest.php`: a described source carries
      `permissions.personal_data`, true for a person holding the right and false for one who
      only manages data; `mayExportPersonalData()` answers the same
- [ ] T013 [P] [US2] Pest unit, `tests/Unit/DataModels/DataExportServiceTest.php`: a request
      that names a personal column from a person without the right is refused, acknowledged
      or not, and nothing is stored or queued; the same person exports the other columns with
      no acknowledgement; a person with the right is still asked to acknowledge; an
      `undeclared` column is treated as personal in each of these
- [ ] T014 [US2] `DataSourceService::mayExportPersonalData()` and `permissions` in
      `describe()` (`plugins/corex-config/src/Data/DataSourceService.php`);
      `DataExportService::validateColumns()` asks it before the acknowledgement
      (`plugins/corex-config/src/DataModels/DataExportService.php`), with a translated reason
- [ ] T015 [P] [US2] Pest unit, `tests/Unit/Submissions/WpSubmissionAccessPolicyTest.php` (or
      the existing policy test): `canExportPersonalData` is true for a person given
      `corex_export_personal_data` and for an administrator, false for one who only manages
      submissions; the filter `corex_submission_export_personal_data` still has the last word
- [ ] T016 [US2] `plugins/corex-config/src/Submissions/WpSubmissionAccessPolicy.php`: the
      default of `canExportPersonalData` is the ability. The spec 106 session is told before
      this file is touched
- [ ] T017 [US2] Pest integration, `tests/Integration/Data/DataManagementControllerTest.php`:
      as a person who manages data and submissions and is not an administrator,
      `POST /data/newsletter-subscribers/exports` with `email` answers a refusal and makes no
      run; without `email` it makes one; after the role is given the ability, with `email`
      and the acknowledgement it makes one; `GET /data/sources` carries `permissions` and the
      declared classes; an administrator is unchanged

**The screen**

- [ ] T018 [P] [US2] Jest, `plugins/corex-config/src/DataModels/__tests__/dataExportState.test.js`:
      `dataColumnChoices(fields, mayExportPersonal)` leaves personal columns out for a person
      without the right and says how many were left out; `personalKinds()` names, in words and
      once each, the kinds among the chosen columns, `undeclared` as "not yet classified";
      `personalDataLabel()` words every class
- [ ] T019 [US2] `plugins/corex-config/src/DataModels/dataExportState.js` (`dataColumnChoices`,
      `withheldColumns`, `personalKinds`, `personalDataLabel`);
      `plugins/corex-config/src/DataModels/DataExportDialog.js`: the columns offered, the line
      saying columns were withheld and why, the acknowledgement naming the kinds
- [ ] T020 [P] [US2] Jest (rendered), `plugins/corex-config/src/DataModels/__tests__/DataExportDialog.test.js`:
      without the right the personal columns are absent and the line is shown; with it the
      acknowledgement lists the kinds and the export is blocked until it is ticked; a source
      with no personal column shows neither
- [ ] T021 [P] [US2] `plugins/corex-config/src/DataModels/ModelsPanel.js` and
      `plugins/corex-config/src/admin/data/FieldControl.js` word a field's class through
      `personalDataLabel()` where they printed its key; Jest for both in their `__tests__`
- [ ] T022 [US2] Playwright, `tests/e2e/data-management.spec.js`: the Models tab words
      subscribers' `email` as contact details; the export dialog for subscribers names the
      kinds and holds the export until the box is ticked; measured spacing of the withheld
      line and the acknowledgement, both directions, light and dark

**Closing the slice**

- [ ] T023 [US2] Guards on the diff: `clean-code-guard`, `wp-guard`, `test-guard`,
      `docs-guard`; the UI/UX gate on the dialog
- [ ] T024 [US2] Docs: a page on the Data screen's exports and personal data in
      `docs/en/03-operations/` with its mirror in `docs/ar/03-operations/`; how a table
      declares `personalData` in `docs/en/06-cookbooks/` with its mirror; the in-admin guide
      if one covers the Data screen
- [ ] T025 [US2] `CHANGELOG.md` under Unreleased with Client impact (who loses the personal
      columns on the Data screen, and that a site's own table counts as personal data until it
      declares); `PROGRESS.md` (the row, and the item under "Open, and not hidden" removed);
      `DECISIONS.md`; the two client sites' sessions told of the client impact

## Slice 2: several models are exported in one go (US1; FR-001 to FR-015a; D4, D5, D16)

**Goal**: tick any number of models, one file, one entry in the history.

**Independent test**: tick three models that hold records, export as Excel, open the file:
three sheets, each named for its model, each with the rows the dialog stated.

- [ ] T030 [P] [US1] Pest unit, `tests/Unit/DataModels/DataExportRunTest.php`: a run holds
      parts; a stored run with `source_key` and `columns` and no `parts` reads as one part;
      scope and selected rows are refused with more than one part
- [ ] T031 [US1] `parts` in `plugins/corex-config/src/DataModels/DataExportRun.php` and
      `DataExportRequest.php`
- [ ] T032 [P] [US1] Pest unit, `tests/Unit/DataModels/DataExportServiceTest.php`: a request
      for several models authorises each, counts each, leaves an empty one out and says so,
      refuses when all are empty, refuses PDF over the limit across the parts, and applies
      slice 1's rule to each part's columns
- [ ] T033 [P] [US1] Pest unit, same file: the job gathers part by part into a working file
      each, and the last step writes one document with a sheet per part and a fact per model;
      a model removed mid-run is left out and named
- [ ] T034 [US1] `DataExportService`, `DataExportJobHandler`, `DataExportFiles`,
      `DataExportAbout` and `WpDataExportStore` in `plugins/corex-config/src/DataModels/`
- [ ] T035 [P] [US1] Pest unit, `tests/Unit/DataModels/DataExportRetentionTest.php`: a file is
      removed 30 days after its run was made and the entry stays; a deleted file's entry says
      who deleted it
- [ ] T036 [US1] `DataExportRetention.php` registered with the sweep; `removeFile()` and
      `holdingFilesBefore()` on the store; the notification's link names the Export tab in
      `plugins/corex-config/src/Notifications/Producers/ExportReadyNotificationProducer.php`
- [ ] T037 [US1] Pest integration, `tests/Integration/DataModels/DataExportSetTest.php`: the
      six `/data-exports` routes of contracts/routes.md, each refusal, another person's run
      answering as absent, the per-source history listing a run that holds a part for it, the
      activity log entries with no value
- [ ] T038 [US1] `plugins/corex-config/src/DataModels/DataExportSetController.php`, its
      binding and registration in `ConfigServiceProvider.php`
- [ ] T039 [P] [US1] Jest, `plugins/corex-config/src/DataModels/__tests__/modelChecklist.test.js`
      and `dataExportState.test.js`: the two groups, tick all of a group, nothing ticked at
      first, the counts, the line for models not offered; the payload for several models; the
      reason PDF is not offered; an unfinished run and "Continue"
- [ ] T040 [US1] `ModelChecklist.js`; `ExportPanel.js` with the checklist, the history with
      size, expiry, delete and unfinished runs; `DataExportDialog.js` for several models;
      `modelClient.js`: the Export tab asks for what an export asks for (D16)
- [ ] T041 [US1] Playwright, `tests/e2e/data-management.spec.js`: quickstart slice 2, steps 1
      to 7; the checklist by keyboard alone; measured spacing, both directions, light and dark
- [ ] T042 [US1] Guards; the export guide and its mirror; CHANGELOG, PROGRESS, DECISIONS

## Slice 3: an import knows what is already there, and takes several files (US6; FR-042 to FR-047; D15)

**Goal**: a row already in a model is reported and not added; several files are reviewed and
approved together.

**Independent test**: give two CSV files for two importable models at once, approve both in
one action, then give the same two again: every row is reported as already there.

- [ ] T050 [P] [US6] Pest unit, `tests/Unit/Data/ManagedTableDeclarationTest.php`: `identity`
      names columns of the table or is refused
- [ ] T051 [US6] `identity` on `ManagedTable`; `IdentityAwareDataSource` in
      `plugins/corex-config/src/Data/`, implemented by the writable table source with one
      prepared query per batch in `WpTableDataReader.php`; `identity: ['email']` on
      `SubscriberTable.php`
- [ ] T052 [P] [US6] Pest unit, `tests/Unit/DataModels/DataImportServiceTest.php`: a row whose
      identity the model holds, an earlier row of the file holds, or a named earlier run
      accepted, is "already there" and neither accepted nor rejected; an email identity is
      compared lowercased and trimmed; the run's hash covers the duplicates and `after_runs`;
      a source with no identity behaves as today
- [ ] T053 [US6] `DataImportPlanner.php`, `DataImportRequest.php`, `DataImportRun.php`,
      `DataImportService.php`, `ImportReportWriter.php` in `plugins/corex-config/src/DataModels/`
- [ ] T054 [US6] Pest integration, `tests/Integration/Data/ManagedModelImportMigrationTest.php`:
      the same file imported twice leaves the model as the first import left it; `after_runs`
      of another person or another model is refused; the report lists the rows already there
- [ ] T055 [P] [US6] Jest, `plugins/corex-config/src/DataModels/__tests__/importBatch.test.js`:
      the model suggested from a file's name and first line; a batch's state per file;
      approving the chosen ones in order; a later file of the same model sent with the earlier
      run's id
- [ ] T056 [US6] `importBatch.js` and `ImportPanel.js`: several files, the suggested model, one
      review, one approval; the limits per file stated before a file is chosen
- [ ] T057 [US6] Playwright, `tests/e2e/data-management.spec.js`: quickstart slice 3; measured
      spacing, both directions, light and dark
- [ ] T058 [US6] Guards; the import guide and its mirror; CHANGELOG, PROGRESS, DECISIONS

## Slice 4: a package is made (US3, first half; FR-022 to FR-027; D2, D6, D8, D9)

**Goal**: the models that can be moved are packaged into one file, in steps.

**Independent test**: make a package of submissions and subscribers; unzipped, it holds the
description, a records file per model with every field, and every hash matches.

- [ ] T060 [P] [US3] Pest unit, `tests/Unit/Data/ManagedTableDeclarationTest.php`: `transfer`,
      `transferFiles`, `transferPeople` and `category` as contracts/model-declaration.md
      states them, with each refusal
- [ ] T061 [US3] Those arguments on `ManagedTable`; `transfer` on
      `DataSourceCapabilities`; the catalog carries it with the reason a model cannot be moved
- [ ] T062 [P] [US3] Pest unit, `tests/Unit/Transfer/InstallIdentityTest.php` and
      `RecordOriginsTest.php`: an identifier is made once; a changed address makes a new one
      and keeps the old with its moment; a record's identifier is the same on two copies of
      one database and differs for a record born after the copy's address changed
- [ ] T063 [US3] `plugins/corex-config/src/Transfer/InstallIdentity.php`, `RecordOrigins.php`,
      `RecordOriginsTable.php`, registered with the schema registry (read
      `Boot::providersForState()` first, DECISIONS #299)
- [ ] T064 [P] [US3] Pest unit, `tests/Unit/Transfer/TableTransferTest.php` and
      `SubmissionsTransferTest.php`: a whole record holds every column or meta the model
      keeps, a person as an email address, a file as a reference; read how a flow is
      recognised on another install and record it in research.md
- [ ] T065 [US3] `TransferableDataSource.php`, `TransferDeclaration.php`,
      `TransferRecord.php`, `TableTransfer.php`, `SubmissionsTransfer.php` in
      `plugins/corex-config/src/Data/`; the three shipped models declare `transfer`
- [ ] T066 [P] [US3] Pest unit, `tests/Unit/Transfer/DataPackageWriterTest.php` over a real
      zip: the members, the description and each hash of contracts/data-package.md; no member
      outside the three kinds; no setting, key or account in it
- [ ] T067 [US3] `DataPackageManifest.php`, `DataPackageWriter.php`, `DataPackageRun.php`,
      `DataPackageStore.php`, `DataPackageJobHandler.php`, `DataPackageRetention.php` in
      `plugins/corex-config/src/Transfer/`; the notification's destination for the kind
- [ ] T068 [US3] Pest integration, `tests/Integration/Transfer/DataPackageMakingTest.php`: the
      `/data-packages` routes and refusals of contracts/routes.md; a step stops on its time
      budget; a person without slice 1's right is refused; the file expires after 7 days
- [ ] T069 [US3] `DataPackageController.php`; `MovePanel.js`, `PackageMaker.js`,
      `moveClient.js`; the Move tab in `modelClient.js` and `DataModelsApp.js`, with Jest
- [ ] T070 [US3] Playwright, `tests/e2e/data-move.spec.js`: quickstart slice 4
- [ ] T071 [US3] Guards; CHANGELOG, PROGRESS, DECISIONS

## Slice 5: a package is received, and a live install keeps what it has (US3, second half, and US4; FR-029 to FR-041, FR-049 to FR-053; D7, D10 to D12, D14, D17, D18)

**Goal**: a package is uploaded in parts, previewed, and written by a job that only adds on a
live install.

**Independent test**: on a live install holding ten leads, three edited since a package was
made and one deleted for good, receive that package: the ten are unchanged, the deleted one is
still gone, and the preview said so in numbers.

- [ ] T080 [US3] Tell the spec 107 session, then split `Releases/ReleaseUpload.php` into
      `plugins/corex-config/src/Upload/PartedUpload.php` and its release use, and
      `Releases/releaseClient.js` and `sha256.js` into `admin/upload/`, as one commit that
      changes no behaviour; that class's tests stay green
- [ ] T081 [P] [US4] Pest unit, `tests/Unit/Transfer/ReceivePolicyTest.php`: every combination
      of this install live or not, the package's install live or not, personal data replaced
      or not, the model's kind, the record new, same, different, deleted here or anonymised
      here, and the choice to bring up to date, with the action and its reason
- [ ] T082 [US4] `ReceivePolicy.php` and `LiveInstall.php` in `plugins/corex-config/src/Transfer/`
- [ ] T083 [P] [US3] Pest unit, `tests/Unit/Transfer/DataPackageReaderTest.php` over real
      zips: a damaged member, a traversing entry name, a newer format, a file that is not a
      package, a model not here, a field not here, a required field missing, a value of the
      wrong type
- [ ] T084 [US3] `DataPackageReader.php`, `DataPackageInspector.php`, `DataReceipt.php`,
      `DataReceiptStore.php`, `DataPackageUpload.php`
- [ ] T085 [P] [US4] Pest unit, `tests/Unit/Transfer/RecordOriginsTest.php`: a claim, its
      completion, a stale claim taken over, a deleted record's note holding nothing of the
      record
- [ ] T086 [US4] The note of a deleted record: on WordPress's delete of a `corex_submission`,
      in `Data/ManagedTableWriteAdapter.php`, and in each add-on path that deletes a
      subscriber or an application (found and listed first)
- [ ] T087 [US3] `receive()` and `bringUpToDate()` on the two transfer implementations;
      `DataReceiveJobHandler.php`; `DataReceiptController.php`
- [ ] T088 [US4] Pest integration, `tests/Integration/Transfer/DataPackageRoundTripTest.php`
      over two table prefixes: quickstart slice 5, steps 2 to 8, and a step that dies half way
      completed by receiving again; two receivers at once add each record once
- [ ] T089 [P] [US3] Jest for `PackageReceiver.js` and the receipts list; then the components
- [ ] T090 [US3] Playwright, `tests/e2e/data-move.spec.js`: quickstart slice 5, steps 1 to 5
- [ ] T091 [US3] Measure a package of 20,000 records with and without files at 128 MB and 30
      seconds; write the numbers into the guide; ask the first client site's session for one
      run on its host
- [ ] T092 [US3] Guards; the moving guide in `docs/en/03-operations/` and
      `docs/en/05-deployment/` with mirrors; the cookbook for declaring a movable model;
      CHANGELOG, PROGRESS, DECISIONS

## Slice 6: a development copy without real people in it (US5; FR-028, FR-038; D13)

**Goal**: a package made with every personal value replaced, and none of the files.

**Independent test**: make such a package and search the unzipped file for any email address,
phone number or name of the original records: none is found.

- [ ] T100 [P] [US5] Pest unit, `tests/Unit/Transfer/PersonalDataReplacerTest.php`: each class
      and type gives a replacement of its kind; the same original gives the same replacement
      under one key and another under a second; nothing of the original is in the result;
      sensitive and security values and files are dropped; `undeclared` is replaced as text
- [ ] T101 [US5] `PersonalDataReplacer.php`; the making job passes every record through it;
      a person without slice 1's right can make a package, with the tick forced
- [ ] T102 [US5] Pest integration, `tests/Integration/Transfer/DataPackageReplacedTest.php`:
      quickstart slice 6, steps 1 to 5, the search over the real file included
- [ ] T103 [US5] The tick on `PackageMaker.js` with Jest; Playwright for the forced tick and
      the refusal on a live install
- [ ] T104 [US5] Guards; the guide; CHANGELOG, PROGRESS, DECISIONS

## Order and what can run together

- Slice 1 before everything: slices 2, 4 and 6 read its declarations and its right.
- Slices 2 and 3 do not depend on each other and could be built by two sessions. Slice 3's
  `identity` is also read by slice 5.
- Slice 4 before 5 before 6. Slice 5 waits on the spec 107 session's answer about
  `ReleaseUpload.php`.
- Inside slice 1: T001, T002, T004, T006 are tests in four files and can be written together;
  T003 and T005 are in different files; T007 and T008 too. T009 to T016 follow T003 and T005.
  The screen (T018 to T022) follows T014.

## Smallest thing worth releasing

Slice 1 alone: it adds nothing to the screen and closes a gap a site has today. Slices 1 and 2
together are the owner's report, word for word.
