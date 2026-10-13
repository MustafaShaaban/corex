# Feature Specification: Files, End to End

**Feature Branch**: `spec/081-forms-files-end-to-end` *(not yet created)*

**Created**: 2026-07-28

**Status**: Draft — specified, not implemented

**Input**: Issue #138, items 6–9, found while building a site on CoreX.

## Why this spec exists

A site cannot collect a file.

Not "collecting a file is awkward" — cannot. The issue lists four problems and then makes the
observation that matters more than any of them individually:

> Any one of them alone leaves a workable path; together they force a fully parallel implementation
> outside the framework.

That is the shape of this feature. It is one capability wearing four masks:

1. **Nothing can be uploaded.** `FieldTypeRegistry` seeds 16 field types and none is a file.
   `SubmitController::payload()` reads JSON and body params only; `$_FILES` is never consulted.
   `sanitizeShape()` has three branches and flattens anything unknown to text. There is no mime rule
   and no size rule. So a form with an upload **cannot be a CoreX Form at all** — and therefore
   cannot use the submission pipeline, the storage, or the Submissions inbox.
2. **Nothing uploaded can be seen.** `RecordsTable.js`, `RecordDetail.js` and the Submissions
   drawer's `DetailSection` render values with `String(value)` / `JSON.stringify(value)`. `DataField`
   has no media type and `TableDataSource::fields()` hardcodes every column to `TYPE_TEXT`. An
   attachment id displays as a bare integer with no way to reach the file.
3. **Nothing uploaded can be sent.** `MailRequest`, `EmailMessage` and `MessageBuilder` carry no
   attachments field, and `WpMailDriver::send()` calls `wp_mail()` with four arguments — the
   `$attachments` parameter is never passed.
4. **The one add-on that tries, silently fails.** `corex-careers` reads and sanitises `$_FILES['cv']`
   and passes the *descriptor* to `ApplicationService::apply()`, whose signature expects
   `int $cvAttachmentId = 0` in that position. No caller ever supplies the id, so `cv_attachment` is
   **always written as `0`**. There is no `wp_handle_upload` anywhere in the add-on, despite an
   inline comment describing one and spec 014 requiring a stored CV reference.

There are two smaller pieces of dishonesty attached, and they are in scope because leaving them is
how the next person loses a day:

- `Block/FieldRenderer::INPUT_TYPES` **contains `'file'`**. It is unreachable — the registry rejects
  the type upstream — so it reads as support that exists.
- `COREX-EMAIL-ADDON.md` documents `attach()`, `attachMedia()`, `attachGenerated()` and an
  `AttachmentResolver`. **None of them exists.**

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A form can ask for a file (Priority: P1)

A developer adds a file field to a CoreX form. A visitor attaches a document. It is stored, and the
submission behaves like every other submission.

**Why this priority**: it is the entry point. Without it the other three have nothing to carry.

**Independent Test**: define a form with a file field, submit one through the real endpoint, and
confirm a submission exists with a resolvable attachment.

**Acceptance Scenarios**:

1. **Given** a form declaring a `file` field, **When** it renders, **Then** the field appears and the
   form submits as `multipart/form-data`.
2. **Given** a permitted file, **When** submitted, **Then** it lands in the media library and the
   submission stores a reference to it — not a filename, not a path.
3. **Given** a file whose type is not allowed, **When** submitted, **Then** it is refused with a
   field-level message naming what is accepted, and **nothing is stored**.
4. **Given** a file over the size limit, **When** submitted, **Then** the same, with the limit named.
5. **Given** a file whose extension and real content disagree, **When** submitted, **Then** it is
   refused. The check is on content, because an extension is chosen by whoever uploads.
6. **Given** a submission that fails validation *after* the upload, **When** it is refused, **Then**
   no orphaned file is left behind.
7. **Given** a form with no file field, **When** submitted, **Then** nothing about its behaviour
   changes.

---

### User Story 2 - An administrator can open what was uploaded (Priority: P1)

Someone reading a submission or a record can see that a file is attached, what it is, and can open it.

**Why this priority**: equal to US1. A file that cannot be reached is the same as no file — and this
is where the current gap is most visible, since an attachment id renders today as a bare integer.

**Acceptance Scenarios**:

1. **Given** a stored attachment reference, **When** the record or submission is displayed, **Then**
   it renders as a named, openable file — not an integer, not JSON.
2. **Given** an image, **When** displayed, **Then** a thumbnail is shown.
3. **Given** a reference to an attachment that no longer exists, **When** displayed, **Then** it says
   so plainly rather than linking to a 404.
4. **Given** a viewer without permission to the file, **When** it is displayed, **Then** they cannot
   reach it.

---

### User Story 3 - A notification can carry the file (Priority: P2)

The person notified of a submission gets the document with it, or a link they can use.

**Acceptance Scenarios**:

1. **Given** a submission with a file, **When** the notification is sent, **Then** the email
   names the file and links to the submission, where the file is opened by whoever may read it.
   The file is not attached. *(Amended 2026-10-11, DECISIONS #319. It read "the file is
   attached, or linked when attaching would be inappropriate".)*
2. **Given** a submission with a file that was not stored, or a site with no inbox, **When** the
   notification is sent, **Then** the email names the file and offers no link. *(Amended with
   scenario 1. It read "a file over the attachment size threshold is linked instead, and says
   why": there is no threshold, because nothing is attached.)*
3. **Given** a send that fails, **When** it is recorded, **Then** the failure is about the send, not
   a fatal about a missing file.

---

### User Story 4 - `corex-careers` stores the CV it already validates (Priority: P2)

**Acceptance Scenarios**:

1. **Given** an application with a CV, **When** it is accepted, **Then** `cv_attachment` holds a real
   attachment id. It is currently always `0`.
2. **Given** a stored application, **When** an administrator views it, **Then** the CV is reachable —
   which also requires the applications table to be a `ManagedTable`, since it is not one today.
3. **Given** a CV, **When** it is stored, **Then** it is not publicly enumerable. A CV is personal
   data, and the media library is world-readable by default.

---

### Edge Cases

- **A file that is refused after another field already validated** — partial success must not leave
  a file behind.
- **Two submissions uploading the same filename.**
- **An upload larger than the server's own limits**, where PHP discards it before CoreX sees it and
  `$_FILES` is empty or truncated. The message must not be "no file provided".
- **A multi-file field**, if supported at all — the spec must say either way.
- **A file field on a multi-step flow**, where the file arrives at one step and the submission
  completes at another.
- **Deleting a submission**: does its file go too? Retention already governs submissions.
- **An attachment referenced by two submissions.**

## Requirements *(mandatory)*

**Collecting**

- **FR-001**: A `file` field type MUST exist in the registry, and the block MUST render it. The
  unreachable `'file'` entry in `FieldRenderer::INPUT_TYPES` MUST become reachable or be removed.
- **FR-002**: The submit pipeline MUST accept `multipart/form-data` and read `$_FILES`.
- **FR-002a**: A `file` field's answer MUST be the file the request carried in its file parts,
  or absent. What the request's body says under the field's name MUST NOT be kept, validated
  or handed to a listener. Where a route reads no file parts (a flow built in Forms & Flows),
  a `file` field has no answer. Added 2026-10-13 (DECISIONS #353).
- **FR-003**: Uploads MUST be validated for mime type **by content**, extension, and size, with an
  allow-list. Rejection MUST be field-level and MUST name the constraint.
- **FR-004**: A validated file MUST be stored through WordPress's own upload handling, and the
  submission MUST store a reference to it rather than a path.
- **FR-004a**: The store MUST NOT read a file PHP did not receive as an upload in the request
  it is storing for. Added 2026-10-13 (DECISIONS #353).
- **FR-005**: A refused submission MUST leave no stored file.
- **FR-006**: Forms without a file field MUST be unaffected — same payload handling, same
  sanitisation, same tests.

**Displaying**

- **FR-007**: A field type representing an attachment MUST exist, and table/detail/drawer surfaces
  MUST render it as a named, openable file with a thumbnail where applicable.
- **FR-008**: A missing attachment MUST render as missing.
- **FR-009**: Access MUST respect the viewer's permission to the file.

**Sending**

- **FR-010**: The mail stack MUST carry attachments end to end, and `WpMailDriver` MUST pass them to
  `wp_mail()`.
- **FR-011**: A form's notification MUST name each file sent with the form and MUST link to the
  stored submission; it MUST NOT attach the file. The link MUST be to the submission and not to
  the file, and the email MUST say that the reader has to be signed in. *(Amended 2026-10-11,
  DECISIONS #319: the owner's decision. It read "a size threshold MUST decide
  attach-versus-link, and the message MUST say which happened". FR-010 stands: the mail stack
  carries an attachment a caller gives it.)*
- **FR-012**: `COREX-EMAIL-ADDON.md` MUST describe what exists. Either `attach()`/`attachMedia()`/
  `attachGenerated()`/`AttachmentResolver` are built, or the document is corrected.

**Careers**

- **FR-013**: `ApplicationService::apply()` MUST receive a real attachment id, and `cv_attachment`
  MUST hold it.
- **FR-014**: The applications table MUST be registered as a `ManagedTable` so its rows are visible
  in admin.
- **FR-015**: A stored CV MUST NOT be publicly enumerable.

## Success Criteria *(mandatory)*

- **SC-001**: A CoreX form with a file field can be defined, rendered, submitted and stored without
  writing a REST endpoint outside the framework. It currently cannot be defined at all.
- **SC-002**: A stored attachment is openable from the Submissions inbox and the Records explorer in
  one click. It currently displays as an integer.
- **SC-003**: A disallowed type, an over-size file, and a content/extension mismatch are each refused
  with a message naming the constraint, and store nothing.
- **SC-004**: A notification for a submission with a file arrives naming the file and linking to
  the submission it is opened from. *(Amended 2026-10-11; it read "attached or linked".)*
- **SC-005**: A careers application stores a `cv_attachment` that resolves. It is currently always
  `0`.
- **SC-006**: No form without a file field changes behaviour, and no existing test regresses.
- **SC-007**: No document describes an API that does not exist.

## Assumptions

- **WordPress's media library is the store.** `wp_handle_upload` and an attachment post, not a
  bespoke files table. It gives permissions, metadata, thumbnails, retention and the media modal for
  free, and spec 048's WebP pipeline already works on it.
- **A reference is an attachment id.** Not a path, not a URL — both break when the site moves.
- **Attach-versus-link has a threshold, not a preference.** Mail providers reject large attachments,
  so the product decides and says what it did.
- **Personal-data files need somewhere that is not world-readable.** FR-015 is likely the hardest
  requirement here, because the media library is public by default and the honest options — a
  protected directory, a signed URL, an authenticated delivery route — are each a real design choice.
  It should be settled in the plan and not discovered during implementation.
- **Multi-file is out of scope** unless the plan says otherwise, and the spec should say so rather
  than leave it ambiguous.
- **Who may open a submission's file is who may read the submission** (added 2026-10-11,
  DECISIONS #317). FR-009 says access respects the viewer's permission to the file and did not
  say which permission that is. For a file uploaded with a form it is the Submissions inbox's
  own rule: the person manages submissions and their scope allows that one. WordPress's rule
  for a private attachment (`read_private_posts`) is a different one, held by editors and
  administrators and not by every role that can be given the inbox. A file in a managed table
  (a careers CV) keeps the attachment's own rule, as built.
- **An answer is a file when the form asks it as one**, or when the form no longer asks that
  question and the answer is the id of a file uploaded through a field of that name. An answer
  the form asks as anything else is never read as a file, whatever number it holds.

## Dependencies

- Spec 048's media pipeline (WebP conversion runs on `wp_generate_attachment_metadata`, so it will
  see these uploads — including the palette-PNG path fixed under issue #142).
- Spec 080's per-form listeners: a form declaring a file field will usually declare its own
  notification listener too.
- Retention (submissions already have a retention policy; files inherit that question).
