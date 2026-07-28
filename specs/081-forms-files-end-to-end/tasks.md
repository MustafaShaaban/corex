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
- [x] **T032** `COREX-EMAIL-ADDON.md` describes what exists. `attach()` and `attachGenerated()` are
      recorded as not implemented, with the reason, rather than quietly deleted.

## Phase E — close

- [x] **T040** Full gate: Pest unit + integration, Jest, Playwright, lints, token inventory.
- [x] **T041** Guards: `wp-guard`, `clean-code-guard`, `test-guard`, `docs-guard`.
- [x] **T042** `PROGRESS.md`, `DECISIONS.md`, PR, close #138.

## Deliberately not done

- **Multi-file per field.** One file per field is the decided scope: a field that sometimes holds
  one reference and sometimes many is a different storage shape, render and validation story, and
  none of the four reported defects needs it. `SubmitController::uploads()` skips a multi-file
  descriptor rather than half-handling it.
- **A real HTTP upload in the integration suite.** `wp_handle_upload()` refuses anything
  `is_uploaded_file()` rejects, which is every file a test process can create — by design. Faking it
  would mean weakening the check in production to make a test pass.
