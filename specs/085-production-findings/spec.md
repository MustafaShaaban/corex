# Feature Specification: Production Findings from a Live Build

**Feature Branch**: `spec/085-production-findings`

**Created**: 2026-07-28

**Status**: Draft

**Input**: GitHub issues **#148** (corex-forms + runtime, eight defects), **#149** (corex-config Data
admin, two defects) and **#150** (corex-email, one missing capability) — all filed from building two
production forms and a multi-mailbox transactional setup on a bilingual EN/AR site.

## Why this spec exists

These are the most valuable reports this project gets, for the same reason spec 080's were: they
came from using the framework rather than reading it. Every claim below was **re-verified against
the current tree before any code was written**, because the last round found one reported defect
already fixed — and this round found another.

### The one that was already fixed

**#150 closes with a "Correction to #138, item 3", stating that `EmailStudioSubmissionGateway::reply()`
still passes `null` positionally for reply-to.** It does not. It calls `replyToAddress()`, added by
spec 080. The reporter's own later comment on #149 says so — *"Same for the `replyToAddress()` half
of #138 item 3, which is what my earlier comment there said was missing"* — so the correction is
stale, written before they saw the merged fix. **Nothing is done about it here, deliberately.**

### The one that a good fix made harder to see

**#149's first item is the whole Data record detail modal, broken for every source since it shipped.**
`useDataExplorer.detail()` returns `payload.record`, and `DataController::show()` puts the record at
the envelope root — there is no `record` key. Every source, every install, `undefined`.

Spec 080 made this *less* visible, not more. Before it, the symptom was a modal full of em dashes,
which reads as broken. After it, `recordRows(undefined)` returns `[]` and the modal renders **"This
record has no readable fields."** — a sentence that reads as a true statement about the record. An
operator seeing that on a submission concludes the submission is empty.

The unit test covering the modal passed throughout, because it calls `recordRows()` directly with a
well-formed record — it bypasses the only layer that was broken.

### The one where the obvious fix is worse than the bug

**#148's first item is silent data loss on every multi-value field.** The runtime sent `el.value` for
a `<select multiple>`, which is the *first* selected option. Observed live: a visitor selected three
services and one was stored.

Sending the real list alone makes it worse. Every arm of `sanitizeShape()` maps to a scalar
sanitizer and `sanitize_text_field()` returns `''` for an array, so the field would be blanked
entirely — strictly worse than storing one value, and indistinguishable in the inbox from a visitor
who answered nothing.

## Requirements *(mandatory)*

### #149 — the Data admin

- **FR-001** `detail()` MUST return the record the endpoint sent, and a test MUST exercise that
  layer rather than the one below it.
- **FR-002** A stored `http(s)` URL MUST be openable from the Data detail and the Submissions
  drawer. Anything that is not an absolute `http(s)` URL — `javascript:`, `data:`, a bare path,
  prose containing a URL — MUST stay inert text, and links MUST carry `rel="noopener noreferrer"`.

### #148 — forms and the runtime

- **FR-003** A multi-value field MUST keep every value, in the browser and through sanitization.
  The two halves MUST ship together.
- **FR-004** Validation messages MUST be translatable through the site's existing `.mo` catalogue,
  with no build step; the server's own rejection reasons MUST go through `__()`.
- **FR-005** Rendered forms MUST set `novalidate`, so the runtime's validation is what a visitor
  meets rather than the browser's native bubble.
- **FR-006** A `phone` rule MUST exist server-side, and the client table MUST mirror it exactly,
  along with `url`.
- **FR-007** A field error MUST clear as the field is corrected, without disturbing its neighbours.
- **FR-008** `phone`, `email` and `url` inputs MUST carry the keyboard, autofill and direction the
  browser needs. `dir="ltr"` on `phone`/`url` is correctness, not polish: on an RTL page an unmarked
  `tel` input reorders digit groups as they are typed.
- **FR-009** `corex:form:error` MUST fire on client-side failure as well as server-side, carrying
  the failing field names.
- **FR-010** The spinner MUST render on a theme that defines neither of the custom properties it
  reads.

### #150 — the mail stack

- **FR-011** A message MUST be able to name the mailbox it is sent from, defaulting to the
  configured sender, and MUST keep it across the queue.
- **FR-012** Only the address is per-message. The display name stays configured.

## Success criteria

- **SC-001** Opening any record in the Data explorer shows its values.
- **SC-002** A `javascript:` value in a submission is never rendered as a link.
- **SC-003** Three services selected are three services stored.
- **SC-004** A validation message appears in the site's language with nothing built.
- **SC-005** A queued message and an immediate one leave from the same address.

## Out of scope

- **`max_words`.** #148 item 4 lists it as missing from the client rule table. It has no server rule
  either, so no schema can emit it and a client arm would be unreachable code. That is a feature to
  add on both sides at once, not a mirror that is missing.
- **#138 items 6–9** (files end to end). #149 item 2 is a partial answer to item 6 — it covers a
  stored URL, not an attachment id — and the rest belongs to spec 081.
