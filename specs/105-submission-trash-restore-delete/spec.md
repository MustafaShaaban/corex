# Feature Specification: Trash, restore and permanently delete a submission

**Feature Branch**: `spec/105-submission-trash-restore-delete`

**Created**: 2026-10-08

**Status**: Draft

**Input**: Reported from the first client site's production host on 2026-10-08, where the owner
tried to remove a test lead from the Submissions inbox and could not: the inbox "gives an
administrator no way to trash, restore or permanently delete a submission", so "a site cannot
remove one person's data on request, and test leads stay in the inbox". Asked for: "per-row and
bulk trash, a trash view with restore and delete permanently, and the adapter/capability mismatch
on SubmissionsSource put right." Asked what a permanent delete should remove, the owner: "you
decide the best for me".

## What is there today *(read from the code on 2026-10-08)*

- The inbox's bulk actions are mark read, assign, mark spam and archive. Anything else is refused.
  The detail pane changes a status and nothing more. No route deletes a submission.
- The only way to trash one is the retention panel's "Move to trash": for submissions older than
  the retention window, in bulk, and hidden while the window is 0. It writes no history and no
  audit entry.
- Nothing shows trashed submissions, restores one, or deletes one for good. The post type has no
  screen of its own.
- **WordPress deletes a trashed submission for good by itself**, 30 days after it was trashed (its
  daily trash clean-up, for every post type). CoreX has no part in that today: nothing is said,
  nothing is recorded, and a file uploaded with the submission is left on disk with no record
  pointing at it.
- A submission's answers, notes, history and delivery records are all stored on the submission.
  Stored elsewhere and tied to it: files uploaded with it, the record and a captured copy of each
  email about it (kept 30 days, with the full text), and one notification ("assigned to you").
  The mail log's rows are not tied to a submission: a row holds a recipient and a subject, and
  neither a submission nor an attempt (found when slice 2 was planned).
- The Data screen's "Form submissions" source declares that it can delete and has nothing behind
  the declaration, so the screen offers no Delete. A second route can trash a submission by id
  for any administrator, outside the inbox's access rules and with no record.
- Retention's "anonymize" removes the answers and leaves an uploaded file on disk, and leaves a
  second copy of the hidden fields, campaign data and consent record.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Move a submission to the trash, and get it back (Priority: P1)

Somebody who manages submissions removes a test lead, or a message that should not be in the
inbox, and it is gone from the inbox at once. If that was a mistake, they open the trash and
restore it, and it is back exactly as it was.

**Why this priority**: it is what the owner tried to do and could not. It is also safe: nothing
is lost by it.

**Independent Test**: trash one submission from its pane and three from the list; the inbox no
longer shows them and its counts fall by four; open the trash, restore one; it is in the inbox
with its status, owner, notes and history unchanged, and its history says who trashed and who
restored it, and when.

**Acceptance Scenarios**:

1. **Given** an open submission, **When** the person chooses "Move to trash" and confirms,
   **Then** the pane closes, the row leaves the list, and a notice says what happened with an
   "Undo" that restores it.
2. **Given** several ticked rows, **When** the person chooses "Move to trash" from the bulk
   actions, **Then** a confirmation says how many will be moved, and afterwards all of them are in
   the trash and none in the inbox.
3. **Given** a trashed submission, **When** the person opens the trash, **Then** they see it with
   who trashed it, when, and the date it will be deleted for good.
4. **Given** a trashed submission, **When** the person restores it, **Then** it is in the inbox
   under the status it had, and nothing about it has changed except its history.
5. **Given** a person who may see only some submissions, **When** they trash, view the trash or
   restore, **Then** only the submissions they may see are affected or shown.
6. **Given** a trashed submission, **When** anybody opens it, **Then** it can be read and cannot be
   replied to, assigned, annotated or given a status until it is restored.

---

### User Story 2 - Delete a submission for good (Priority: P1)

A person asks a site to remove what they sent. Somebody with the right to do so deletes that
submission permanently, is told exactly what that removes, and afterwards nothing on the site
holds what the person wrote.

**Why this priority**: it is the reason the report matters "beyond tidiness". A site that cannot
do this cannot answer a removal request.

**Independent Test**: submit a form with an uploaded file and have a notification email captured
for it; trash the submission and delete it permanently; the submission, its answers, notes and
history, the uploaded file, the captured email and the record of its attempt are gone, the
"assigned to you" notification no longer leads anywhere, and the activity stream holds one entry
saying a
submission of that form was deleted, by whom and when, with none of its answers.

**Acceptance Scenarios**:

1. **Given** a trashed submission, **When** somebody allowed to chooses "Delete permanently",
   **Then** a confirmation lists what will be removed, says it cannot be undone, and does not
   proceed until that is acknowledged.
2. **Given** the confirmation is acknowledged, **When** the deletion runs, **Then** the
   submission and everything listed in FR-011 are removed, and the activity stream records it.
3. **Given** a submission that is not in the trash, **When** anybody looks for a way to delete it
   permanently, **Then** there is none: a submission is trashed first.
4. **Given** somebody who manages submissions and may not delete permanently, **When** they open
   the trash, **Then** they can restore and cannot delete, and are told why.
5. **Given** kept export files that include the submission, **When** the confirmation is shown,
   **Then** it says so, says when those files expire, and links to where they can be deleted.
6. **Given** several trashed submissions are ticked, **When** they are deleted permanently,
   **Then** the confirmation says how many, and one that cannot be deleted does not stop the
   others; the result says how many were deleted and names why any were not.

---

### User Story 3 - The trash empties itself, and says so (Priority: P2)

A trashed submission is deleted for good after a stated number of days, by CoreX, with the same
care as a deletion by hand, and a record that it happened.

**Why this priority**: WordPress already deletes them after 30 days, silently and incompletely.
Until CoreX owns that, the trash loses data nobody decided to lose and leaves files behind.

**Independent Test**: trash a submission with an uploaded file, move its trash date past the
window, run the daily clean-up; the submission and the file are gone and the activity stream
holds an entry saying the trash expired it. Set the window to "never"; run the clean-up again on
another; it is still in the trash.

**Acceptance Scenarios**:

1. **Given** a trashed submission older than the window, **When** the daily clean-up runs,
   **Then** it is deleted with everything in FR-011, and the activity stream says the trash
   expired it.
2. **Given** the trash view, **When** it is opened, **Then** it states how long a trashed
   submission is kept, and each row shows its own date.
3. **Given** the window is set to "never", **When** any clean-up runs, CoreX's or WordPress's own,
   **Then** no trashed submission is deleted.
4. **Given** a submission is deleted by anything other than CoreX's own actions, **When** it
   goes, **Then** what is tied to it goes with it.

---

### User Story 4 - One way to remove a submission (Priority: P2)

Whichever screen removes a submission, it does the same thing, under the same permissions, and
leaves the same record.

**Why this priority**: there are two other routes today, one of them outside the inbox's access
rules, and neither records anything.

**Independent Test**: use the retention panel's "Move to trash"; each submission's history shows
it and the activity stream has one entry for the run. Open CoreX Data → Form submissions; it
offers nothing it cannot do. Call the Data route that trashed a submission by id as somebody who
may not see that submission; it is refused.

**Acceptance Scenarios**:

1. **Given** the retention panel moves submissions to the trash, **When** it has run, **Then**
   each is in the trash view with the retention run as what trashed it.
2. **Given** the Data screen's Form submissions source, **When** it is opened, **Then** it
   declares only what it performs.
3. **Given** any route that removes a submission, **When** it is called, **Then** the inbox's
   access rules decide whether it may, and the removal is recorded.

---

### User Story 5 - Anonymizing removes the files too (Priority: P3)

Retention's "anonymize" removes everything that says who sent a submission and what they sent,
including a file they uploaded.

**Why this priority**: it is a gap in a neighbouring action, found while listing what a deletion
must remove. The list is the same list.

**Independent Test**: anonymize a submission that has an uploaded file, hidden fields and
campaign data; the file is gone from disk, and no copy of the hidden fields, campaign data or
consent record remains on the submission.

**Acceptance Scenarios**:

1. **Given** a submission with an uploaded file, **When** it is anonymized, **Then** the file is
   removed.
2. **Given** an anonymized submission, **When** its stored data is read, **Then** no answer,
   hidden field, campaign value or consent record is in it, in any copy.

---

### User Story 6 - WordPress's own "Erase personal data" reaches submissions (Priority: P3)

A site that handles removal requests through WordPress's Tools → Erase Personal Data finds
submissions by the person's email address and removes them with the rest of their data.

**Why this priority**: it joins CoreX to the tool a site may already use. The inbox's own
deletion answers the same request without it.

**Independent Test**: run WordPress's erasure for an email address that sent two submissions;
both are deleted as in User Story 2, and the tool's report says two items were removed.

**Acceptance Scenarios**:

1. **Given** an erasure request for an email address, **When** it is carried out, **Then** every
   submission sent from that address is deleted permanently and the tool reports the count.
2. **Given** an export request for an email address, **When** it is carried out, **Then** the
   submissions sent from it are in the exported data.

---

### Edge Cases

- A submission is trashed while somebody else has it open: their next action on it is refused
  with "This submission is in the trash", not with a generic failure.
- A submission is restored after the form it came from was deleted: it is restored and shown
  under the form's name as it was recorded.
- A trashed submission was the target of an "assigned to you" notification: the notification's
  link says the submission is in the trash, or, after deletion, that it no longer exists.
- The same submission is ticked in two browser tabs and deleted from both: the second is told it
  is already gone, and nothing fails.
- A file uploaded with a submission cannot be removed from disk: the deletion says so, the
  submission is not reported as fully deleted, and the entry in the activity stream says what was
  left.
- A site without the email add-on: deletion removes what exists and does not fail for what does
  not.
- A site whose WordPress is configured to skip the trash (`EMPTY_TRASH_DAYS` of 0): moving to the
  trash must not delete on the spot. CoreX's trash is its own promise.
- More submissions are ticked than one request can process: the work is done in steps, with
  progress, as an export is.
- A submission marked as a test: it is trashed and deleted like any other.
- Bulk actions other than restore and delete are not offered in the trash view.

## Requirements *(mandatory)*

### Functional Requirements

**Trash and restore**

- **FR-001**: A person who manages submissions MUST be able to move a submission to the trash
  from its detail pane, and several from the list's bulk actions.
- **FR-002**: Moving to the trash MUST ask for confirmation that states how many submissions are
  affected, and MUST be undoable from the notice that follows it.
- **FR-003**: A trashed submission MUST NOT appear in the inbox, its counts, its exports, the
  Data screen's records, or any count of a form's submissions.
- **FR-004**: The inbox MUST offer a trash view that lists trashed submissions with who trashed
  each, when, and the date it will be deleted for good; and MUST state how long the trash keeps
  them.
- **FR-005**: A trashed submission MUST be restorable, one or several at a time, to the status,
  owner, read state, notes and history it had.
- **FR-006**: A trashed submission MUST be readable and MUST refuse every change except restore
  and permanent delete, saying that it is in the trash.
- **FR-007**: Trashing and restoring MUST each add an entry to the submission's own history, and
  an entry to the activity stream, naming who did it and when.
- **FR-008**: Every trash, restore and delete action MUST apply the inbox's access rules: a
  person acts only on submissions they may see.

**Permanent delete**

- **FR-009**: A submission MUST be deletable permanently only from the trash.
- **FR-010**: Permanent deletion MUST require a permission of its own, separate from managing
  submissions, held by default by administrators and grantable to others. Somebody without it
  MUST see why the action is not available to them.
- **FR-011**: Permanent deletion MUST remove: the submission with its answers, hidden fields,
  campaign data, consent record, team notes and history; every file uploaded with it; the record
  and every captured copy of each email sent about it; and MUST leave the "assigned to you"
  notification pointing at nothing that can be opened. (Corrected 2026-10-08: this said "the mail
  log rows of those emails". A mail log row is tied to no submission and cannot be selected by
  one; removing rows by the submitter's address is FR-025's, where the erasure is by address.)
- **FR-012**: The confirmation MUST list what FR-011 removes in plain words, state that it cannot
  be undone, and require an explicit acknowledgement before it proceeds.
- **FR-013**: Permanent deletion MUST record one entry in the activity stream: who, when, how
  many, which form, and whether a person or the trash's expiry did it. The entry MUST NOT hold
  any submitted value, name or address.
- **FR-014**: Kept export files are not rewritten. Where a kept file may include the submission,
  the confirmation MUST say so, say when such files expire, and lead to where they are deleted.
- **FR-015**: A deletion of several MUST continue past one that fails, and MUST report how many
  were deleted and why each of the others was not.
- **FR-016**: If part of what FR-011 lists cannot be removed, the deletion MUST say which part,
  and MUST NOT report the submission as deleted in full.

**The trash's own clock**

- **FR-017**: A trashed submission MUST be deleted permanently by CoreX after a set number of
  days, 30 unless the site sets another, with everything in FR-011 and the record in FR-013.
- **FR-018**: A site MUST be able to set that number, including "never".
- **FR-019**: WordPress's own trash clean-up MUST NOT delete a submission outside FR-017: not
  sooner, not when the site says "never", and not on the spot when the site's WordPress is set to
  skip the trash.
- **FR-020**: When a submission is deleted by anything, what FR-011 ties to it MUST be removed
  with it.

**One way to remove**

- **FR-021**: The retention panel's "Move to trash" MUST go through FR-001 to FR-008: the
  submissions appear in the trash view, with history and one activity entry for the run.
- **FR-022**: The Data screen's Form submissions source MUST declare only operations it performs.
- **FR-023**: No route MAY remove a submission outside the inbox's access rules or without the
  record in FR-007.

**Neighbouring gaps**

- **FR-024**: Anonymizing a submission MUST remove every file uploaded with it and every stored
  copy of its hidden fields, campaign data and consent record.
- **FR-025**: CoreX MUST take part in WordPress's personal-data erasure and export by email
  address: erasure deletes that address's submissions as FR-011 does, and export includes them.

**The interface**

- **FR-026**: Every new control MUST be reachable and operable by keyboard, announce its result
  to assistive technology, read correctly right to left, and use the admin's existing components
  and tokens.
- **FR-027**: Every new string MUST be translatable, with plural forms where a count is said.

### Key Entities

- **Submission**: what a visitor sent through a form, with its answers and everything recorded
  about handling it. Gains a trashed state, apart from its workflow status, with who trashed it
  and when.
- **Trash**: the trashed submissions a person may see, and the number of days it keeps them.
- **What is tied to a submission**: uploaded files, the records and captured copies of its
  emails, and the "assigned to you" notification. Removed with it, or left leading nowhere.
- **Activity entry**: the record that a submission was trashed, restored or deleted. Outlives the
  submission and holds nothing it said.
- **Kept export file**: a copy of submissions made earlier, with its own expiry. Not altered by a
  deletion.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An administrator removes a test lead from the inbox in under 15 seconds, without
  leaving the inbox.
- **SC-002**: A submission trashed by mistake is back in the inbox, unchanged, within two
  actions.
- **SC-003**: After a permanent deletion, a search of the site's database and its uploads for the
  submitter's email address and for the name of the file they uploaded finds neither, outside
  kept export files the confirmation named.
- **SC-004**: Every submission that leaves the site for good, by a person or by expiry, has an
  entry in the activity stream, and no entry contains a submitted value.
- **SC-005**: No submission is deleted for good without either a person's acknowledged
  confirmation or the trash's stated expiry.
- **SC-006**: Somebody who may see only one team's submissions cannot trash, see in the trash,
  restore or delete another team's.
- **SC-007**: The trash view and its confirmations pass the admin's accessibility checks in both
  directions and both themes, with spacing measured on the rendered page.

## Assumptions

- **What a permanent delete removes was left to CoreX** ("you decide the best for me"). The
  choice: everything that holds what the person sent or says who they are, on the submission and
  tied to it, and an activity entry that holds none of it. Kept export files are named, not
  rewritten: they are copies somebody made on purpose, they expire in 30 days, and they can be
  deleted where they are listed.
- **Trash first, then delete.** Two steps for an action that cannot be undone, as WordPress does
  for posts, and as spec 068 required: personal data is never hard-deleted unless the approved
  workflow explicitly requests and confirms it.
- **The trash keeps a submission 30 days by default**, WordPress's own default, so a site's
  behaviour does not change by surprise; the difference is that CoreX does it, completely, and
  says so.
- **Permanent deletion is an administrator's by default.** Managing an inbox and destroying
  records are different responsibilities; a site can grant the second to whom it likes.
- **"Trash" is not a workflow status.** A submission keeps its status while trashed and has it
  again when restored. The trash is a view of its own beside the status filter.
- **Spam is not trashed automatically.** Marking as spam stays what it is.
- **No "Empty trash" button in the first version.** Ticking a page and deleting it does the same
  with the count in view. It can be added.
- **The email add-on is optional.** Deletion asks it to remove what it holds through a seam it
  fills when present; without it there is nothing of its to remove.
- **Delivery to the person is not undone**: an email already sent to them is theirs.
- **Slices, each its own pull request**: (1) trash and restore, in the pane, the list and a trash
  view; (2) permanent delete, with what is tied to a submission; (3) the trash's own clock, and
  WordPress's clean-up kept out; (4) one way to remove: retention and the Data source; (5)
  anonymize removes files; (6) WordPress's erasure and export.
