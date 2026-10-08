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

**Server**

- [x] T017 Pest unit: the scope carries `canDeletePermanently`; without it `delete()` refuses and
      deletes nothing
- [x] T018 The scope, the policy and its filter (D8)
- [x] T019 Pest integration: a submission with an uploaded file is deleted with the file; the
      store finds its uploads and its email attempt ids; only a trashed one can be deleted
- [x] T020 `SubmissionTrashStore::uploadsOf()`, `emailAttemptsOf()`, `delete()`
- [x] T021 Pest unit: `delete()` removes files, then email records, then the submission; a file
      that cannot be removed leaves the submission in the trash and is reported; one failure
      does not stop the others; one activity entry with no submitted value
- [x] T022 `SubmissionTrashService::delete()`; `SubmissionEmailRecords` and its no-op (D10, D11, D12)
- [x] T023 Pest unit and integration: the add-on forgets an attempt and its captured copy by
      attempt id; `EmailStudioStore::delete()`
- [x] T024 The add-on's `SubmissionEmailRecords`; `EmailStudioStore::delete()`
- [x] T025 Pest integration: `DELETE /submissions/{id}` is registered and guarded, deletes a
      trashed submission, and refuses one in the inbox and a person who may not
- [x] T026 The route; bulk action `delete` with a result that says what was not deleted

**Interface**

- [x] T027 Jest: the trash offers "Delete permanently" only to somebody who may; the
      confirmation's words; the result's words when some were not deleted
- [x] T028 "Delete permanently" in a trashed submission's pane and in the trash's bulk actions;
      the confirmation with its list, its acknowledgement and the export files line (D14)
- [x] T029 What somebody who may not delete is told (FR-010)
- [x] T030 Playwright: delete one from its pane and several in bulk; the acknowledgement gates
      the action; measured spacing of the confirmation, both directions
- [x] T031 Guards; the guide; spec FR-011 corrected (D9); CHANGELOG with Client impact; PROGRESS;
      DECISIONS

## Slice 3: the trash's own clock (US3; FR-017 to FR-020)

- [x] T032 Pest unit: the number of days (30 by default, 0 for never, sanitised); the sweep
      deletes what went in before the cutoff and adopts first; `expire()` records `by: expiry`
- [x] T033 `SubmissionTrashRetention`; `SubmissionTrashService::expire()` and `forgetTiedData()`;
      the sweep takes the new store
- [x] T034 Pest integration: the sweep deletes an old trashed submission with its file and
      leaves a recent one; "never" deletes nothing; WordPress's clean-up is kept off; a
      submission trashed WordPress's way is adopted at once; one deleted by something else takes
      its file
- [x] T035 `SubmissionTrashStore::trashedBefore()` and `adoptWordPressTrash()`;
      `SubmissionDeletionGuard`
- [x] T036 The setting in the retention panel; `trash_days` in the list's answer
- [x] T037 Jest: the line that says how long; the day a submission is deleted
- [x] T038 The line above the trash; each row's date
- [x] T039 Playwright: the setting is saved, the trash says it, a row has its date; measured
- [x] T040 Guards; the guide; CHANGELOG with Client impact; PROGRESS; DECISIONS

## Slice 4: one way to remove (US4; FR-021 to FR-023)

- [x] T041 Read every caller of `wp_trash_post()` and of a data source's `delete()`: two, the
      retention panel and the Data screen's source; the route that reached the second is not
      registered, and the registered one cannot perform it
- [x] T042 Pest unit: `trashForRetention()` moves what is due and the person's, as the retention
      run, with history and one activity entry; leaves what is not theirs; records nothing for
      nothing
- [x] T043 `SubmissionTrashStore::VIA_RETENTION`; `SubmissionRetentionTrash`;
      `SubmissionTrashService::trashForRetention()`
- [x] T044 Pest unit: the retention loop hands the run to the trash once, as the person
- [x] T045 `SubmissionRetention` takes the person and the trash; `RetentionController` asks the
      inbox's access policy who they are; `trashForRetention()` and `trash()` gone from the reader
- [x] T046 Pest integration: a run on real WordPress leaves each submission in CoreX's trash
      with no WordPress clock, a history entry and one record; a submission outside the person's
      view stays
- [x] T047 The Data source declares no delete and performs none; Pest unit
- [x] T048 Pest unit: nothing in CoreX calls `wp_trash_post()`
- [x] T049 Guards; the guide; CHANGELOG with Client impact; PROGRESS; DECISIONS

## Slice 5: anonymize removes the files (US5; FR-024)

## Slice 6: WordPress's erasure and export (US6; FR-025)
