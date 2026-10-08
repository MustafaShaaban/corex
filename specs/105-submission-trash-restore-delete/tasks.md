# Tasks: Trash, restore and permanently delete a submission

Tests are written first and seen to fail. One slice per pull request.

## Slice 1: trash and restore (US1; FR-001 to FR-008, FR-026, FR-027)

**Server**

- [x] T001 Pest integration: a submission trashed by CoreX is `trash`, has `corex_trashed_at`,
      `corex_trashed_by`, `corex_trashed_via` and no `_wp_trash_meta_time`; WordPress's
      `wp_scheduled_delete()` leaves it; it is trashed, not deleted, with `EMPTY_TRASH_DAYS` 0
- [x] T002 `SubmissionTrashStore`, `WpSubmissionTrashStore`
- [x] T003 Pest unit: `SubmissionTrashService` trashes and restores inside the scope, refuses
      outside it, refuses a stale version, writes history and one activity entry with no
      submitted value
- [x] T004 `SubmissionTrashService`; container bindings
- [x] T005 Pest integration: the inbox, its count and an export do not hold a trashed
      submission; `view=trash` lists it with who and when; restoring returns it with status,
      owner, notes and history unchanged
- [x] T006 `SubmissionInboxQuery::$trashed`; the reader lists and finds trashed submissions
- [x] T007 Pest integration: the two routes are registered and guarded; a change to a trashed
      submission is answered 409 with "in the trash"
- [x] T008 Routes `…/{id}/trash`, `…/{id}/restore`; `view=trash`; the 409
- [x] T009 Pest unit: bulk `trash` and bulk `restore` preview and apply; `restore` previews
      trashed records
- [x] T010 Bulk actions `trash` and `restore`

**Interface**

- [x] T011 Jest: the inbox state has a view; the URL carries it; the actions offered depend on it
- [x] T012 The Inbox and Trash views; the trash's "trashed by, on" column
- [x] T013 "Move to trash" in the pane and the bulk actions, with confirmation and "Undo"
- [x] T014 "Restore" in the trash; the pane read-only for a trashed submission
- [x] T015 Playwright: trash one from the pane and undo; trash several, open the trash, restore
      one; measured spacing of the view switch and the trash line, both directions
- [x] T016 Guards; the guide; CHANGELOG with Client impact; PROGRESS; DECISIONS

## Slice 2: delete permanently (US2; FR-009 to FR-016)

Planned when slice 1 is in.

## Slice 3: the trash's own clock (US3; FR-017 to FR-020)

## Slice 4: one way to remove (US4; FR-021 to FR-023)

## Slice 5: anonymize removes the files (US5; FR-024)

## Slice 6: WordPress's erasure and export (US6; FR-025)
