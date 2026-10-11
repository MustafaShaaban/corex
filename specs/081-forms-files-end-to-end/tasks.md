# Tasks: Files, End to End

Four phases on one branch. The order is the dependency order: nothing can be shown or sent before
something can be stored, and the consumer that proves the rest goes last — except that careers came
first, because it was the cheapest end-to-end proof the store worked at all.

## Phase A — the boundary that did not exist

- [x] **T001** `AttachmentStore` — the `wp_handle_upload` boundary. A repo-wide search found no
      upload handling in the framework at all: two comments describing one, and no code.
- [x] **T002** Content-based type checking through `wp_check_filetype_and_ext()`. `UploadValidator`
      still checks the declared type cheaply, and its docblock no longer claims a store re-checks
      the real MIME — it names the store that now does.
- [x] **T003** `ProtectedUploads` — the directory and its Apache/IIS deny rules, written before
      every store rather than once on activation, because a plugin updated by copying files over
      the old ones never runs an activation hook.
- [x] **T004** `AttachmentDelivery` — the capability check in PHP, which is the actual guarantee.
      Registered from `ConfigServiceProvider`, so every add-on that stores a file shares one route.
- [x] **T005** `AttachmentStorage` interface, for the same reason `Mailer` is one.
- [x] **T006** Careers (#138 item 8): the `int $cvAttachmentId = 0` parameter no caller supplied is
      gone; `ApplicationTable` registers the table as managed.

## Phase B — collect (#138 item 7)

- [x] **T010** `file` in `FieldTypeRegistry`. `Block/FieldRenderer::INPUT_TYPES` had listed it all
      along and the registry rejected it upstream.
- [x] **T011** `MimeType` and `MaxFileSize` rules, both reading the file rather than the browser's
      description of it. `max_size` takes megabytes.
- [x] **T012** `$_FILES` through `Request` → `SubmitController` → `FormSubmissionService`, on a
      channel beside `$input` so no sanitizer has to learn what a file looks like.
- [x] **T013** Validate first, store second — FR-005 by construction rather than by cleanup. A
      second file failing gives the first one back.
- [x] **T014** `enctype="multipart/form-data"` on both renderers; `accept` derived from the field's
      own `mime:` rule.
- [x] **T015** `corex-runtime.js`: skip file inputs in `collect()`, switch to `FormData` only when
      the form carries a file, and stop forcing `Content-Type` over the multipart boundary.

## Phase C — display (#138 item 6)

- [x] **T020** `DataField::TYPE_ATTACHMENT`.
- [x] **T021** `TableDataSource::schema()` reads the declared type instead of hardcoding text — the
      issue names `fields()`, which was already correct.
- [x] **T022** Rows hydrate an attachment id into `{id, name, url, missing}` server-side, so one
      list request stays one request.
- [x] **T023** `FieldValue` renders it as a link to the delivery route; a missing file says so
      rather than showing an em dash.
- [x] **T024** `recordRows.js` (not `RecordDetail.js`, which the issue names), `RecordsTable.js` and
      the Submissions drawer all stop stringifying.

## Phase D — mail (#138 item 9)

- [x] **T030** `AttachmentResolver` — ids in, paths out, never the reverse.
- [x] **T031** Attachments through `MailRequest` → `MessageBuilder::attachMedia()` →
      `EmailMessage` → the fifth argument of `wp_mail()`, surviving the queue.
      **Not met until 2026-10-08:** `MailService::deliver()` rebuilt the message without them. See
      spec 085, Phase 6, and DECISIONS #264.
- [x] **T032** `COREX-EMAIL-ADDON.md` describes what exists. `attach()` and `attachGenerated()` are
      recorded as not implemented, with the reason, rather than quietly deleted.

## Phase E — close

- [x] **T040** Full gate: Pest unit + integration, Jest, Playwright, lints, token inventory.
- [x] **T041** Guards: `wp-guard`, `clean-code-guard`, `test-guard`, `docs-guard`.
- [x] **T042** `PROGRESS.md`, `DECISIONS.md`, PR, close #138.

## Phase F — a submission's file, where a submission is read (reported 2026-10-11)

T024 says the Submissions drawer stopped stringifying, and it did: the pane draws a described
file as a link. Nothing describes one for it. The detail route answers a `file` question with
the attachment's id, so the pane printed `224` (SC-002, unmet for the inbox). The export wrote
the same number where spec 103 says the cell holds the file's name. Tests first.

- [x] **T050** Pest, unit: `SubmissionFileAnswers` says which answer is a file, which file is
      missing, and which answer is not a file at all.
- [x] **T051** Pest, unit: the detail payload describes a file answer; `SubmissionQueryService::file()`
      answers within the actor's scope and for nobody else.
- [x] **T052** Pest, unit: an export's file column holds the file's name, or says it is missing.
- [x] **T053** Pest, integration, on real WordPress: an upload is described, in the inbox and in
      the trash; the delivery address refuses a bad link, a signed-out request, a person the
      scope leaves out, and a field that holds no file.
- [x] **T054** Jest: the pane draws the file's name as a link, says a missing file is missing,
      and leaves out a file question nobody answered.
- [x] **T055** Playwright: in the inbox walk the file is opened from the pane and its bytes
      arrive; signed out, they do not.
- [x] **T056** `SubmissionFiles` (the port), `WpSubmissionFiles`, `SubmissionFileAnswers`.
- [x] **T057** `SubmissionQueryService::detail()` describes file answers; `file()`.
- [x] **T058** `SubmissionFileDelivery`, the address a person allowed to read the submission
      opens; `AttachmentDelivery::deliver()` is the one place a protected file is sent from.
- [x] **T059** `SubmissionExportTable` names the file.
- [x] **T060** Guards, `PROGRESS.md`, DECISIONS #317, `CHANGELOG.md`. No guide describes the
      inbox's pane, so none was changed.

Not in this phase, and written under "Open, and not hidden" in `PROGRESS.md`: a code-defined
form's notification email prints the attachment's id and carries no file (US3, SC-004: the mail
stack carries attachments, and `SendEmailListener` gives it none); CoreX → Data → Form
submissions prints the id as well.

## Phase G — the notification email of a form with a file (decided 2026-10-11)

Phase F left this to the owner: the email printed the number the file is stored under and
carried no file. His decision: link to the submission, do not attach (DECISIONS #319). US3,
FR-011 and SC-004 are amended to say so. Tests first.

- [x] **T070** Pest, unit: the email names the file in place of the number, links to the
      stored submission, and offers no link without a file or without a stored submission.
- [x] **T071** Pest, unit: whatever stores a submission tells the event which one it became.
- [x] **T072** Pest, integration: the submission the email points at is the one WordPress
      stored, at the address the inbox opens a submission from; nothing is attached.
- [x] **T073** `FormSubmittedEvent::storedAs()` / `submissionId()`; `StoreSubmissionListener`
      says it.
- [x] **T074** `Corex\Mail\SubmissionAddress` (core), `InboxSubmissionAddress` (config), the
      one place that address is written.
- [x] **T075** `UploadedFileNames`; `SendEmailListener` names the files and adds the link.
- [x] **T076** Guards, `PROGRESS.md`, DECISIONS #319, `CHANGELOG.md`.

## Deliberately not done

- **Multi-file per field.** One file per field is the decided scope: a field that sometimes holds
  one reference and sometimes many is a different storage shape, render and validation story, and
  none of the four reported defects needs it. `SubmitController::uploads()` skips a multi-file
  descriptor rather than half-handling it.
- **A real HTTP upload in the integration suite.** `wp_handle_upload()` refuses anything
  `is_uploaded_file()` rejects, which is every file a test process can create — by design. Faking it
  would mean weakening the check in production to make a test pass.
