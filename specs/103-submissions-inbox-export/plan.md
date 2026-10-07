# Implementation Plan: A submissions inbox and exports a client can use

**Branch**: `spec/103-submissions-inbox-export` | **Date**: 2026-10-07 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/103-submissions-inbox-export/spec.md`

**How this was written**: by hand to the Spec Kit plan template, from reading the source at
`fb078381`. `/speckit-plan` was not run.

## Summary

Make an exported submission file something a person reads — one row per submission, one column per
answer, headed by the form's question — in Excel, CSV and PDF; make the export dialog state what it
will export and hand over the file without a refresh; make the whole inbox row open a submission;
put the detail pane in the order it is read; and bring the Data export to the same standard.

Delivered in seven slices, each its own pull request, in the order under "Slices".

## Technical Context

**Language/Version**: PHP 8.3; JavaScript (ES2022) through `@wordpress/scripts`

**Primary Dependencies**: WordPress 7.0, `@wordpress/element`, `@wordpress/components` (the inbox
uses `Button` and `Spinner`), CoreX admin components in `plugins/corex-config/src/admin/components`

**Storage**: submissions as `corex_submission` posts; export runs as `corex_sub_export` posts, the
file today in post meta as text; jobs in the bounded-job table

**Testing**: Pest unit (`tests/Unit`), Pest integration against real WordPress
(`phpunit-integration.xml.dist`), Jest (`wp-scripts test-unit-js`), Playwright (`tests/e2e`)

**Target Platform**: WordPress admin, single site and multisite

**Project Type**: WordPress plugin (`plugins/corex-config`), with one change in `plugins/corex-core`
(job lock) and a read of `plugins/corex-forms` (question wording)

**Performance Goals**: SC-002 — a file of 500 submissions saved within ten seconds of the click

**Constraints**: no optional plugin as a hard dependency (constitution; FR-020); all styling through
tokens and logical properties; every string translatable; WCAG 2.2 AA

**Scale/Scope**: exports of tens to tens of thousands of rows; batches of 100 (the job runner's)

## What the source does today

| Fact | Where |
|---|---|
| One column per data group, the group JSON-encoded into one cell | `Submissions/SubmissionExportCsvWriter.php` `cell()` |
| Format is fixed to `csv` | `SubmissionExportRequest::from()` |
| The file is appended to post meta as text, batch by batch | `SubmissionExportJobHandler::handle()`, `WpSubmissionExportStore::saveArtifact()` |
| A job advances only when WP-Cron or Action Scheduler fires, 100 records a run | `Jobs/CronJobDispatcher.php`, `Jobs/JobRunner.php` |
| `JobRunner::execute()` takes no lock | `Jobs/JobRunner.php` |
| The download answers JSON with the file inside it | `SubmissionsController::downloadExport()` |
| The dialog is a `@wordpress/components` `Modal`, drawn outside `.corex-admin`; every inbox style is scoped under `.corex-admin`, so none reach it | `Submissions/index.js` `ExportModal`, `assets/submissions-admin.scss` |
| Column labels are `choice.replaceAll('_', ' ')`, untranslated | `Submissions/index.js` |
| Only `.corex-inbox__row-button` opens a submission | `Submissions/index.js` `InboxTable` |
| The Data export writes CSV and a one-sheet, all-text XLSX, stored base64 in post meta | `DataModels/DataExportArtifactWriter.php`, `WpDataExportStore.php` |
| No PDF or spreadsheet library is installed | `composer.json` |

## Decisions

**D1 — A table before a file.** A pure `SubmissionExportTable` turns records and a form's questions
into headings and rows of typed cells (text, number, date-time, boolean). Every writer takes that
table. The flattening rules of FR-002 to FR-007 live in one place and are unit-tested without a
file format.

**D2 — Question wording through a contract.** corex-config asks for a form's questions through a
small contract it owns, `SubmissionQuestions` (`for(string $form): list<{key,label,type}>`).
corex-forms is where the answer is: a code form's `fields()`, a flow's published schema. With
corex-forms absent, or the form gone, the answer is empty and the field key is the heading. The
detail pane reads the same contract, through the submission's REST payload.

**D3 — Writers behind one interface.** `ExportWriter::write(ExportDocument): ExportFile`, with CSV,
XLSX and PDF implementations chosen by format. They live in a namespace both exports use
(`Corex\Config\Export`), since US10 needs the same three.

- *CSV*: UTF-8 with a byte-order mark, a chosen separator, the formula guard kept.
- *XLSX*: the existing hand-built writer grown into a real one — shared strings, number and
  date-time cells with styles, a frozen first row, an autofilter, column widths from content, one
  sheet per form. `ZipArchive` is already required by the Data export. No library is added.
- *PDF*: decided in its own slice, after a short spike. Two candidates: a bundled PHP library (the
  requirement that Arabic reads correctly, FR-021, narrows this to one that shapes and reorders
  Arabic), or a print-styled HTML document the browser saves as PDF. The first gives a real file
  from the server and costs dependency weight in the shared-host distribution; the second costs
  nothing and is not a file until the person prints it. The spec asks for a document "to file", so
  the library is the presumption and the spike has to show its weight is acceptable.

**D4 — Files, not post meta.** An export's file is written to the protected uploads area
(`Corex\Security\Upload\ProtectedUploads`), named at random, and its path recorded on the run.
Binary formats and large exports do not belong in a meta row. The download streams the file with
its own content type and `Content-Disposition`, after the same capability check; nothing is wrapped
in JSON. Deleting or expiring a run removes the file.

**D5 — The dialog advances the job.** Creating an export still queues a job, so a closed dialog
loses nothing. While the dialog is open it calls `POST …/exports/{id}/advance`, which runs one
batch and answers with processed and total. When the job completes the dialog fetches the file and
saves it. This does not depend on WP-Cron firing, which on a low-traffic site it may not for
minutes. `JobRunner` gains a per-job lock so a cron run and an advance cannot both append the same
batch; the lock is a change in the runner, for every job kind.

**D6 — A CoreX dialog.** A `CorexDialog` component on the native `<dialog>` element, rendered inside
the screen's own tree so every scoped style and token reaches it. The element gives the focus trap,
Escape and the backdrop. It replaces `Modal` for the export and for the bulk confirmation, and is
what the Data export's dialog uses.

**D7 — Counts before the export.** `GET …/exports/preview` answers, for the filters and selection
sent, the count of each scope and the filters in words. The dialog shows what it is told; it does
not count rows itself.

**D8 — Column choice per person and form**, in user meta, validated against what the person may
export every time it is read.

**D9 — The row.** The row's existing button stays its one control and its hit area is stretched
over the row; the checkbox is lifted above it. No handler on `<tr>`, no second focus stop. The cost
is that text in a row cannot be selected by dragging; the pane is where it is read and copied.

**D10 — The pane** is reordered and regrouped as FR-039 to FR-047 say, in the same file, using D2
for headings. Assignment needs the list of people who can own a submission from the server; the
free-text key goes.

**D11 — History.** A run records its format, size, file, expiry and whether it was deleted. A daily
task removes expired files.

**D12 — Data export.** Audited at the start of its slice against FR-048's list; the findings are
added to this plan before any code.

## Constitution Check

| Principle | How this plan meets it |
|---|---|
| Spec before code | This directory, before the first slice. |
| Thin controllers, logic in services | New routes map input to a service call; `SubmissionExportTable` and the writers hold the logic. |
| Everything injected | Writers, the questions contract and the file store are bound in the providers. |
| Tokens, logical properties, no CSS framework | The dialog and pane use the admin tokens; RTL is verified, not assumed. |
| No optional plugin as a hard dependency | D2 degrades without corex-forms; D3 adds no plugin. A PDF library, if chosen, is a Composer dependency of the framework, not a plugin. |
| i18n | Every new string through the `corex` text domain, including the column names that are raw keys today. |
| Tests | Pest unit for the table and each writer; integration for the routes and the job; Jest for the dialog's state and the inbox reducer; Playwright for the row, the pane and one export end to end. |
| Guard Gate, UI/UX gate | Each slice's diff through its guards; each UI slice rendered in light and dark, LTR and RTL, wide and narrow, before it is presented. |

No violation needs justifying.

## Project Structure

```text
plugins/corex-config/src/
├── Export/                      # new: shared by Submissions and Data
│   ├── ExportDocument.php       # title, sheets of typed cells, provenance
│   ├── ExportWriter.php         # the contract
│   ├── CsvExportWriter.php
│   ├── XlsxExportWriter.php
│   ├── PdfExportWriter.php      # its slice
│   └── ExportFileStore.php      # protected files: put, stream, delete
├── Submissions/
│   ├── SubmissionExportTable.php      # new: records + questions -> typed rows
│   ├── SubmissionQuestions.php        # new contract
│   ├── SubmissionExportPreview.php    # new: counts and filters in words
│   ├── SubmissionExportRequest.php    # format, separator, columns
│   ├── SubmissionExportJobHandler.php # writes through ExportWriter
│   ├── SubmissionsController.php      # preview, advance, stream, delete
│   ├── index.js                       # inbox row, pane, export dialog
│   └── export/                        # new: dialog parts, split out of index.js
├── admin/components/CorexDialog.js    # new
└── Jobs/JobRunner.php                 # per-job lock
plugins/corex-config/assets/submissions-admin.scss
plugins/corex-forms/src/…               # the questions contract's implementation
tests/Unit/Config/Export/, tests/Unit/Submissions/, tests/Integration/Submissions/, tests/e2e/
```

## Slices

Each is one pull request and leaves `main` releasable.

| # | Stories | What lands |
|---|---|---|
| 1 | US4 | The row opens the submission; the open row is marked. This plan and the spec ship with it. |
| 2 | US1, US2, US3, US7 | The table model, question wording, CSV writer, file store, preview with counts, the advance route and the job lock, `CorexDialog`, the rebuilt export dialog, automatic download. |
| 3 | US6 | The XLSX writer and its place in the dialog. |
| 4 | US5 | The detail pane. |
| 5 | US9 | History: size, expiry, delete, cleanup. |
| 6 | US8 | PDF, after its spike. |
| 7 | US10 | The Data export audit and its adoption of slices 2, 3, 5 and 6. |

## Risks

- **A stored export from before slice 2** has its file in post meta. The download has to serve both
  until those runs expire.
- **The stretched row button** changes what a click on a status badge or a date does. Anything
  interactive added to a row later has to be lifted above it; a comment at the rule says so.
- **XLSX by hand** is the largest piece of new format code. It is tested by reading the produced
  workbook back, part by part, and by opening it once in a real spreadsheet before the slice is
  presented.
- **PDF and Arabic.** If no acceptable library shapes Arabic, the slice ships the print-styled
  document and says so, and FR-015 is met in that form until the owner decides otherwise.
- **`SC-002`** depends on batches of 100 completing quickly on the install it is measured on. The
  advance route makes it independent of cron; it does not make a slow query fast.
