# Tasks: A submissions inbox and exports a client can use

**Input**: [spec.md](./spec.md), [plan.md](./plan.md)

**How this was written**: by hand to the Spec Kit tasks template. `/speckit-tasks` was not run.

Tests are written first and seen to fail. `[P]` marks a task that touches no file another open task
touches. Each slice ends with its guards, its rendered check where it has UI, and its notes
(`CHANGELOG.md` with client impact, `PROGRESS.md`, `DECISIONS.md`).

## Slice 1 — The row opens the submission (US4)

- [x] T001 Playwright: clicking the form, status and received cells of a row opens the submission; clicking its checkbox does not (`tests/e2e/`)
- [x] T002 Stretch `.corex-inbox__row-button` over its row and lift the checkbox above it (`assets/submissions-admin.scss`)
- [x] T003 Row affordance: pointer, hover and focus-within background, from tokens (`assets/submissions-admin.scss`)
- [x] T004 Mark the open row: `aria-current` on the row and `aria-expanded` on its button, with a marker that is not colour alone (`Submissions/index.js`, stylesheet)
- [x] T005 ~~Jest: the row for the open submission carries the state~~ Covered by T001 on the real page instead: `InboxTable` is not exported, and the browser test asserts `aria-current` appearing and going
- [x] T006 Rendered check in dark and light at 1280, RTL dark at 1280, light at 782; guards; notes
- [x] T007 (found by T001) The inbox no longer grows past the window: its column is `minmax(0, 1fr)` and the filters wrap by the space they have

## Slice 2 — A readable file, and getting it (US1, US2, US3, US7)

### The table

- [x] T010 [P] Pest unit: `SubmissionExportTable` — one row per submission, one column per question in the form's order, the fixed columns first, an empty cell for an unanswered question, several values joined, a renamed question, a question missing from the form (FR-001 to FR-007)
- [x] T011 [P] Pest unit: typed cells — date-time in the site's timezone, a number, a boolean in words
- [x] T012 `SubmissionExportTable` and its cell value object (`Submissions/`)
- [x] T013 Contract `SubmissionQuestions`, with an empty default bound in corex-config (`Submissions/`)
- [x] T014 Its implementation in corex-forms for a code form and for a flow; integration test for both
- [x] T015 Owner display name for "Assigned to"; "Unassigned" when there is none (FR-004)

### The file

- [x] T020 [P] Pest unit: `CsvExportWriter` — byte-order mark, the chosen separator, quoting, the formula guard on every text cell (FR-008, FR-017)
- [x] T021 `ExportDocument`, `ExportWriter`, `CsvExportWriter` (`Export/`)
- [x] T022 Covered by `SubmissionExportFileTest`: the real job writes into `uploads/corex-private/exports/` and the file exists. Streaming and deleting are slice 2b and slice 5
- [x] T023 `ExportDirectory`, answered by `ProtectedExportDirectory` on `ProtectedUploads`
- [x] T024 `SubmissionExportRequest`: `format`, `separator`, columns as a list of named columns; the old group names still accepted and mapped (a stored run must still load)
- [x] T025 `SubmissionExportJobHandler` builds the table and writes through the writer; the last batch finalises the file
- [x] T026 Several forms in one CSV export: one file per form in an archive, and the request says so (FR-018)
- [x] T027 A file name from the site, the form and the date (FR-025): the service names what it holds and when, the controller adds the site

Slice 2a ends here (DECISIONS #254). T030, T031 and everything below is slice 2b.

### Scope and counts

- [x] T030 Pest integration: the preview answers the three counts and the filters in words; tests are left out unless asked for; a person sees only their own (FR-010 to FR-013)
- [x] T031 `SubmissionExportPreview` and `GET …/exports/preview`
- [x] T032 An export of nothing is refused with a reason (FR-014)

### The flow

- [x] T040 Pest integration: two runs of the same job at once append each batch once
- [x] T041 A per-job lock in `JobRunner` (`Jobs/JobRunner.php`)
- [x] T042 Pest integration: `POST …/exports/{id}/advance` runs one batch and answers processed, total and state; another person's export is refused
- [x] T043 The advance route (FR-022, FR-026)
- [ ] T043b The download as a stream with its content type and name; it still travels base64 inside a JSON answer
- [x] T044 A stored run whose file is still in post meta downloads as before
- [ ] T045 Column choice per person and form in user meta, re-validated on read (FR-009). Also open: choosing single questions and their order in the dialog; the route and the model already take them. FR-027 is done: personal columns are not offered to somebody who cannot export them

### The dialog

- [x] T050 [P] `CorexDialog` on `<dialog>`: focus in, Escape, focus back, labelled by its title; Jest
- [x] T051 Jest: the dialog's state — default scope from the selection, disabled reasons, the summary line, the personal-data gate
- [x] T052 The export dialog rebuilt in `Submissions/export/`: scope with counts and filters in words, columns named and marked, format and separator, summary, confirmation, one primary action
- [x] T053 Progress, completion with the automatic save, a button when the browser blocks it, failure with retry (FR-022 to FR-024)
- [x] T054 Every label translatable; the raw column keys gone (FR-033)
- [x] T055 `ConfirmBulk` moved onto `CorexDialog`
- [x] T056 Styles from tokens, logical properties (`assets/submissions-admin.scss`)
- [x] T057 Playwright: filter, select two rows, export as CSV, receive a file with the questions as headings
- [x] T058 Rendered check in light and dark, LTR and RTL, 1280 and 782 wide, keyboard only; guards; notes

## Slice 3 — Excel (US6)

- [x] T060 [P] Pest unit: the workbook read back part by part — a sheet per form named for it, shared strings, date-time and number cells with their styles, the frozen row, the autofilter, widths
- [x] T061 `XlsxExportWriter` (`Export/`)
- [x] T062 The formula guard in a workbook (FR-008)
- [x] T063 Excel in the dialog and the request
- [x] T064 Open one produced workbook in a real spreadsheet, with Arabic text, and record what was seen
- [x] T065 Guards; notes

## Slice 4 — The detail pane (US5)

- [x] T070 The submission's REST payload carries its questions' wording (D2) and the people who can own it
- [x] T071 Jest: the pane's order; answers headed by wording with the key as fallback; no empty technical section; one "nothing recorded" line
- [x] T072 Header: name, mailto and tel links, form, date, number, status, read state (FR-040)
- [x] T073 Answers first; reply and notes; one triage group with a labelled status and one assignment control (FR-039, FR-042)
- [x] T074 Delivery: the result, when, and the reason; a line saying why nothing was recorded for a form defined in code
- [ ] T074b The rest of FR-043: to whom each attempt went, and where a failure can be fixed. A submission stores one
  delivery record with no recipient, and the inbox is not told the mail screen's address or whether the person may
  open it. Both are server work
- [x] T075 Technical details as one closed group (FR-044)
- [x] T076 Opening marks read; the header marks unread (FR-047)
- [x] T077 Focus into the pane, Escape, focus back to the row; clear of the admin toolbar; the inbox behind it inert (FR-045, FR-046)
- [x] T078 Playwright: open by keyboard, read, close, focus is on the row
- [x] T079 Rendered check as above; guards; notes

## Slice 5 — History (US9)

- [x] T080 Pest integration: a run records format, size and expiry; expired runs lose their file and keep their entry; deleting is recorded in the activity log; a person sees only their own
- [x] T081 The run's new fields; `DELETE …/exports/{id}`; the daily cleanup
- [x] T082 The history in the dialog: who, when, what in words, format, count, size, expiry, download again, delete
- [x] T083 Rendered check; guards; notes

## Slice 6 — PDF (US8)

- [x] T090 Spike: a PHP library that shapes Arabic and what it adds to the distribution, against a print-styled document; the result recorded in `plan.md` before any code (mPDF; plan D3)
- [x] T091 Pest unit: the document's parts — the site's name, title, filters, count, who and when, page numbers, CoreX's signature
- [x] T092 `PdfExportWriter`
- [x] T093 CoreX's signature: the lockup as a print image, and the copyright line (the owner's answer of 2026-10-08, in place of the brand logo and the sign-off block)
- [x] T093b No PDF for more records than one step can write; the dialog says so (FR-019a)
- [x] T094 One produced document opened and printed, with Arabic answers; what was seen recorded
- [x] T095 PDF in the dialog; guards; notes

## Slice 7 — The Data export (US10)

Slice 7a, the server:

- [x] T100 Audit `DataModels/ExportPanel.js` and its services against FR-048's list; add the findings to `plan.md` (D12: there are two surfaces, and Excel was never reachable)
- [x] T101 The Data export builds typed cells from the source's field types, writes through `Export/` writers and keeps its file in `ExportDirectory` (D12a)
- [x] T101b Excel for the sources CoreX ships (D12b)
- [x] T102 Preview with a count for each scope; a step taken on request; no file for no records; a file name for the site, the source and the date
- [x] T102b Pest unit and integration for the above; guards; notes

Slice 7b, the dialog:

- [x] T103 One dialog on `CorexDialog` for the Records tab and the Export tab, in the same order as the Submissions export (D12c)
- [x] T104 Playwright: one Data export end to end
- [x] T105 Rendered check, measured; guards; notes

After 7b, a defect found in a real export of Form submissions (D12e):

- [x] T106 Failing tests first: the source's export rows (Pest unit), the export through the job for ticked, filtered and all records in CSV and Excel (Pest unit), and a stored submission exported through the routes and read back (integration)
- [x] T107 `ExportableDataSource`, implemented by the submissions source and the managed-table source; the job and the service read export rows only; the export capabilities need it as their adapter
- [x] T108 Guards; the guide, the changelog and its Client impact; notes

## Dependencies

- Slice 1 depends on nothing.
- Slice 2 is the base of 3, 5, 6 and 7.
- Slice 4 needs T013 and T014 from slice 2.
- Slices 3, 4 and 5 are independent of each other once slice 2 is in.
