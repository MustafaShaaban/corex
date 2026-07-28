# Feature Specification: Forms & Mail Correctness

**Feature Branch**: `spec/080-forms-mail-correctness`

**Created**: 2026-07-28

**Status**: Implemented

**Input**: Issue #138, items 1–5 — found while wiring a site's own branded transactional email on
top of the Forms + Mail stack.

## Why this spec exists

Five places where the code does something other than what it says. None was found by reading the
framework; every one was found by *using* it to build a real site.

The most consequential is the first, and it is worth stating plainly: **a site that replaced the
built-in notification with its own sent two emails per submission**, and there was no way to stop
it. `Form::listeners()` documents itself as overridable by concrete forms, and overriding it to
*remove* a listener did nothing at all.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - A form's listeners belong to that form (Priority: P1)

A developer overrides `listeners()` on their form. What they declare is what runs — for their form,
and only for their form.

**Why this priority**: it is the only item here that makes the framework send mail the site owner
did not ask for, and the only one with no workaround.

**Independent Test**: register two forms with different listeners; submit one; confirm the other's
listener did not run.

**Acceptance Scenarios**:

1. **Given** two forms with different listeners, **When** one is submitted, **Then** only its own
   listeners run.
2. **Given** a form that overrides `listeners()` to return `[]`, **When** it is submitted, **Then**
   nothing runs. Removal has to work, or the override is decorative.
3. **Given** a submission whose slug matches no registered form — a database-defined flow — **When**
   it is dispatched, **Then** nothing runs. Matching nothing is correct; falling back to every
   listener is the same bug in a different shape.
4. **Given** a listener shared by several forms, **When** those forms are submitted, **Then** it is
   constructed once and its dependency graph is still built lazily, at request time.

---

### User Story 2 - A notification can be read, and answered (Priority: P1)

The person who receives a form submission can read the fields apart from one another, and can press
Reply.

**Why this priority**: every CoreX site with a contact form sends this email, and it arrives wrong
on all of them.

**Acceptance Scenarios**:

1. **Given** a submission with several fields, **When** the notification arrives, **Then** each
   field is visually separate. It is delivered as `text/html`, and HTML collapses newlines.
2. **Given** a multi-line answer, **When** it renders, **Then** its line breaks survive.
3. **Given** a value containing markup, **When** it renders, **Then** it is escaped.
4. **Given** a submission that carries the submitter's email address, **When** the notification
   arrives, **Then** `Reply-To` is that address, so replying reaches the person who wrote in.
5. **Given** a submission with no usable address, **When** it arrives, **Then** there is no
   `Reply-To` — the previous behaviour, and the right answer for a form that never asked.

---

### User Story 3 - A manual reply looks like the site sent it (Priority: P2)

An operator answering from the Submissions inbox sends something that looks like every other email
the site sends.

**Acceptance Scenarios**:

1. **Given** an operator's reply, **When** it is sent, **Then** the body is wrapped in the brand
   layout rather than being the entire message.
2. **Given** a configured reply-to, **When** a reply is sent, **Then** it is used. It was
   hard-coded to null.
3. **Given** an RTL site, **When** any framework email renders, **Then** the document is RTL.

---

### User Story 4 - The brand logo is real or it is gone (Priority: P3)

**Acceptance Scenarios**:

1. **Given** a site with a custom logo or site icon, **When** a framework email renders, **Then**
   the logo appears, at an absolute URL — email clients cannot resolve a relative path.
2. **Given** a site with neither, **When** an email renders, **Then** it falls back to the site name,
   as it always has.

---

### Edge Cases

- **A listener shared by two forms**, which must not be constructed twice or run twice for one
  submission.
- **A submission whose "email" field is not an email address** — it reaches a mail header.
- **A logo URL containing a quote**, which reaches an HTML attribute.
- **A site with no custom logo and no site icon.**

## Requirements *(mandatory)*

- **FR-001**: A submission MUST run the listeners of the form that was submitted, and no others.
- **FR-002**: `Form::listeners()` MUST be overridable in both directions — adding and removing.
- **FR-003**: A slug matching no registered form MUST run nothing.
- **FR-004**: Listener resolution MUST stay lazy; the mail graph must not be built at boot.
- **FR-005**: The notification body MUST be HTML, because every CoreX transport declares
  `text/html`. The plain-text builder MUST remain for transports that want it.
- **FR-006**: Submitted values MUST be escaped, and multi-line answers MUST keep their breaks.
- **FR-007**: The notification MUST carry `Reply-To` when the submission contains a valid address,
  and MUST NOT when it does not. The value MUST be validated before it reaches a header.
- **FR-008**: A manual reply MUST be wrapped in the brand layout and MUST use the configured
  reply-to.
- **FR-009**: The brand array MUST supply a logo when the site has one, as an absolute URL, or the
  unreachable rendering branch MUST be removed.

## Success Criteria *(mandatory)*

- **SC-001**: Submitting one form runs zero listeners belonging only to another. It currently runs
  all of them.
- **SC-002**: A form returning `[]` from `listeners()` runs nothing.
- **SC-003**: A notification with N fields renders N separated fields, not one run of text.
- **SC-004**: Replying to a contact notification reaches the submitter without copying an address
  out of the body.
- **SC-005**: A manual reply and an automated email are visually the same product.
- **SC-006**: No test in the suite regresses.

## Assumptions

- **Item 2 of the issue needs no work.** `DataRegistry` already defers to first read, exactly as the
  issue proposes, via `registerDeferred()` — spec 074 shipped it hours before the issue was filed.
  Verified in the tree rather than assumed.
- **The logo is populated, not deleted.** The branch is written and correct, the site already has a
  logo concept in the custom logo and site icon, and a text-only brand looks unfinished.
- **A reply is not put through a template.** There is no template for "whatever the operator typed",
  and inventing one would put a placeholder between the operator and their own words.
- **Items 6–9 are out of scope** and are specified separately as 081. They are one feature — file
  handling — wearing four masks, not four bugs.
