# Implementation Plan: Files, End to End

**Spec**: `specs/081-forms-files-end-to-end/spec.md` · **Branch**: `spec/081-forms-files-end-to-end`

## The three decisions the spec deferred

**FR-015 storage — decided: media library, protected directory, authenticated route.**

An uploaded CV is personal data somebody sent to one company. The three candidate answers:

- *A plain attachment.* Simplest, reuses everything, and wrong: the file gets a guessable public URL
  and appears in the media library for every author on the site.
- *A bespoke table outside `uploads/`.* Private, and gives up the media pipeline —
  `addons/corex-media` hooks `wp_generate_attachment_metadata`, so a bespoke store silently opts out
  of everything the framework already does with files.
- *Media library, into a protected subdirectory, served through a capability-checked route.* Keeps
  the pipeline, keeps the file out of reach. This one.

The protection is deny rules written on activation plus a delivery route that checks the viewer —
**both**, because a deny file is a server-configuration promise and CoreX cannot verify it held.
The route is the guarantee; the deny rules are what stops a misconfigured server leaking a file the
route would have refused.

**Multi-file — out of scope for v1. One file per field.** Stated rather than left ambient: a field
that sometimes holds one reference and sometimes many is a different storage shape, a different
render, and a different validation story, and none of the four reported defects needs it.

**Lifecycle — the attachment goes when its submission goes, unless another submission references
it.** Retention already deletes submissions; a file that outlived its submission would be personal
data nobody can find to delete.

## Sequence

Four phases on one branch, each independently testable. Ordered so the first is the one everything
else needs and the last is the one that proves the rest.

### Phase A — the store, and the boundary that does not exist yet

`plugins/corex-core/src/Security/Upload/AttachmentStore.php` is new, and it is the whole feature's
foundation: **a repo-wide grep finds no `wp_handle_upload` anywhere** — only two comments describing
one. `UploadValidator` exists but reads `$file['type']`, which is client-supplied; FR-003 wants the
content, so it gains `finfo`/`wp_check_filetype_and_ext`. Its own docblock already claims "the
boundary store re-checks the real MIME" about a store that was never written.

### Phase B — collect (#138 item 7)

The `file` field type in `FieldTypeRegistry`, `MimeType`/`MaxFileSize` rules through `RuleRegistry`,
`$_FILES` through `Request` → `SanitizeMiddleware` → `SubmitController::payload()`, and
`enctype="multipart/form-data"`. `FieldRenderer::INPUT_TYPES` already lists `'file'` and is
unreachable; this is what makes it true.

**The file the issue does not mention, and the one with the widest blast radius:**
`plugins/corex-core/assets/js/corex-runtime.js`. `collect()` reads `el.value`, which for a file
input is `C:\fakepath\name.pdf`, and `viaFetch` forces `Content-Type: application/json`. FR-002 is
impossible without switching to `FormData` when the form carries a file — in a build-free asset
enqueued on every page that has a form.

### Phase C — display (#138 item 6)

`DataField` gains an attachment type. Two corrections to the issue's own file list, found by
reading: the hardcode is in `TableDataSource::schema()`, not `fields()`; and the React flattening is
in `recordRows.js`, not `RecordDetail.js` — which matters because `recordRows` already receives
`source.fields`, so it has the type information it needs.

Spec 085's `FieldValue` already links a stored **URL**. This adds the case it deliberately did not:
an attachment **id**, which needs resolving to a name and a delivery link before anything can render
it.

### Phase D — mail (#138 item 9) and careers (#138 item 8)

Attachments through `MailRequest` → `MessageBuilder` → `EmailMessage` → `WpMailDriver` (still four
arguments to `wp_mail()`), surviving `QueuedMailer` serialisation — the same seam spec 085 threaded
`$from` through, so the shape is established.

`COREX-EMAIL-ADDON.md` documents `attach()`, `attachMedia()`, `attachGenerated()` and an
`AttachmentResolver`, none of which exist. **Correcting that doc is a ten-minute change and should
land first, whatever happens to the rest** — it is currently a promise the framework does not keep.

Careers last, because it is the consumer that proves A–C: a real attachment id in `cv_attachment`,
and the applications table registered as a `ManagedTable` so its rows are visible at all.

## Verification

- Integration through the real endpoint: a submitted file is stored, resolvable, and refused
  submissions leave nothing behind (FR-005).
- **The protection is tested from the outside**: an anonymous request for the stored file's path is
  refused, and the delivery route refuses a signed-in user without the capability. A test that only
  checks the deny file was written proves nothing about whether the server honours it.
- Jest: `collect()` builds `FormData` when a file field is present and a plain object otherwise.
- Playwright: a real upload through a real form, and the file opening from the Submissions drawer.
- Guards: `wp-guard` (upload handling is its sharpest area), `clean-code-guard`, `test-guard`,
  `docs-guard` on `COREX-EMAIL-ADDON.md`.
