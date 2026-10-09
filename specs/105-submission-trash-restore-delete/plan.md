# Implementation Plan: Trash, restore and permanently delete a submission

**Branch**: one per slice, from `main` · **Date**: 2026-10-08 · **Spec**: [spec.md](spec.md)

## Summary

Six slices, each a pull request (spec, Assumptions). This plan decides what all of them share and
details slice 1. Later slices are planned when they are reached, against the code as it is then.

## Technical context

- PHP 8.3, WordPress 7.0+. A submission is a post of type `corex_submission`, always
  `post_status = private`; its workflow status, owner, notes and history are post meta
  (`WpSubmissionsReader`, `SubmissionRepository`).
- The inbox is React in `plugins/corex-config/src/Submissions`, over `corex/v1/submissions`
  routes declared in `SubmissionsController` and guarded by `SubmissionRestGateway`.
- Tests: Pest unit (fakes for the stores), Pest integration on real WordPress, Jest for state,
  Playwright for the inbox.

## Constitution check

- Thin controller, logic in a service, data access behind a store interface: yes, a new
  `SubmissionTrashService` over a `SubmissionTrashStore`.
- Injected through the container, no `new` of a dependency inside a method: yes.
- Tokens and existing admin components only; logical properties; every string translatable:
  yes, and measured on the rendered page (owner's rule of 2026-10-07).
- No optional plugin as a hard dependency: the email add-on is reached through a seam (slice 2).

## Decisions

### D1. A trashed submission is `post_status = trash`, set by CoreX, without WordPress's trash clock

Every read of submissions today asks for `post_status = private`: the inbox, its counts, exports,
the Data source, retention, insights. A trashed submission that is `trash` leaves all of them at
once, which is FR-003 with no query to change and none to forget.

It is not trashed with `wp_trash_post()`:

- `wp_trash_post()` deletes on the spot when `EMPTY_TRASH_DAYS` is 0 (FR-019, and the edge case).
- It writes `_wp_trash_meta_time`, and WordPress's daily `wp_scheduled_delete()` deletes every
  trashed post whose `_wp_trash_meta_time` is older than `EMPTY_TRASH_DAYS`. It selects by that
  meta key and nothing else (`wp-includes/functions.php`, read 2026-10-08).

So CoreX sets the status itself and writes its own record: `corex_trashed_at`, `corex_trashed_by`
and `corex_trashed_via` (`inbox`, `retention`, later `erasure`). With no `_wp_trash_meta_time`,
WordPress's clean-up never selects it. From slice 1 a submission trashed in the inbox stays until
somebody restores or deletes it; slice 3 gives the trash its own clock and takes over the
submissions the retention panel trashed the old way.

Rejected: a meta flag on a `private` post. Every one of the reads above would need a clause, and
the first one missed shows a trashed lead in an export.

Rejected: a custom post status. Nothing else in WordPress would treat it as removed, and core
already knows `trash` is not public, not searchable and not counted.

### D2. One service removes, and it is the only thing that does

`SubmissionTrashService`: `trash()`, `restore()`, and from slice 2 `delete()`. It applies the
access scope, writes the submission's history, and records the activity entry. The inbox's
routes, the bulk actions, and from slice 4 the retention panel and the Data source all call it.

`SubmissionTrashStore` is its data access: `trash(id, actorId, via)`, `restore(id)`,
`find(id)`. Implemented by a class of its own, not by `WpSubmissionsReader`, which already
implements five interfaces in 692 lines.

### D3. The trash is a view of the inbox, not a status

`SubmissionInboxQuery` gains `trashed` (`view=trash` on the route). The reader lists `trash`
posts for it and `private` posts otherwise, with the same filters, scope and shape, plus
`trashed_at`, `trashed_by` and `trashed_via` on a trashed row. A submission keeps its workflow
status while trashed.

A trashed submission can be opened: `findInbox()` returns it with `trashed: true`. Everything
that changes one goes through `findWorkflow()`, which keeps answering only for `private`, so a
trashed submission refuses every change with no service to teach. The controller says why: a
change asked of a trashed submission is answered 409 "This submission is in the trash." where it
was 404.

### D4. Bulk trash and bulk restore are bulk actions

The two-step preview and apply already bounds a selection, pins each record's version and
refuses a stale one. `trash` and `restore` join `mark_read`, `assign`, `mark_spam`, `archive`.
`restore` previews trashed records, so the bulk service asks the trash store for those.

### D5. Undo is restore

The notice after a trash offers "Undo", which restores what was just trashed: the single route
for one, a bulk preview and apply for several. No second mechanism.

### D6. The activity entry

One entry per action, not per submission: kind `submission.trashed` or `submission.restored`,
area `submissions`, the actor, the count, and the form where all are of one form. Target: the
submission for one, none for several. No submitted value, name or address (FR-013 applies the
same rule to deletion). The submission's own history gets an entry each: `trash` or `restore`,
with the actor.

### D7. Permission

Trash and restore need what managing submissions needs, inside the person's scope (FR-008).
Permanent deletion's own permission (FR-010) arrives with slice 2, as
`SubmissionAccessScope::$canDeletePermanently`, filterable like `$canExportPersonalData`.

## Slice 1: trash and restore

**Server**

1. `SubmissionTrashStore` and `WpSubmissionTrashStore` (D1).
2. `SubmissionTrashService` with `trash()` and `restore()` (D2, D6).
3. `SubmissionInboxQuery::$trashed`; the reader lists and finds trashed submissions (D3).
4. Routes `POST /submissions/{id}/trash` and `POST /submissions/{id}/restore`; `view=trash` on
   the list; 409 for a change to a trashed submission.
5. Bulk actions `trash` and `restore` (D4).

**Interface**

6. "Inbox" and "Trash" as two views above the filters. The trash lists who trashed each
   submission and when.
7. "Move to trash" in the pane and in the bulk actions, each with a confirmation that says how
   many, and a notice with "Undo" (D5).
8. In the trash: "Restore" in the pane and in the bulk actions. The pane is read-only, with a
   line saying it is in the trash, since when and by whom.

**Not in slice 1**: permanent delete; any expiry of the trash; the retention panel and the Data
source (they keep doing what they do today until slice 4); files, captured mail and
notifications (they stay with the trashed submission, as they must for a restore).

## Slice 2: delete for good (planned 2026-10-08, with slice 1 on main)

### D8. Who may

`SubmissionAccessScope::$canDeletePermanently`. The policy grants it to somebody who holds
`CorexAbility::RUN_DANGEROUS_ACTIONS` or `manage_options`, through the filter
`corex_submission_delete_permanently`. That ability exists and nothing in Submissions used it;
it is what the Access screen already lets a site grant. The inbox is told whether the person may,
so it can say why the action is not there (FR-010).

### D9. What is tied to a submission, and who knows it

Read from the code on 2026-10-08:

| Tied to it | Where | How it is found |
|---|---|---|
| Answers, hidden fields, campaign data, consent, notes, history, delivery records | post meta | they go with the post |
| Files uploaded with it | protected attachments, `post_parent` 0 | an answer's value is the attachment's id; the attachment has `_corex_protected` and an upload context beginning `form-` |
| Email attempts and captured copies | the email add-on's own posts | by attempt id: the submission's `corex_email_json` bindings, its headline delivery, and its history's `email` entries |
| "Assigned to you" notification | the notifications table | `source_type = submission`, `source_id` |

The store answers `uploadsOf(id)` and `emailAttemptsOf(id)`; the service removes them.

**Mail log rows are not tied to a submission.** A row of `corex_email_log` holds a recipient and
a subject and no submission or attempt id; it is written for mail sent through the older `Mailer`
path. FR-011 said "the mail log rows of those emails". There is nothing to select them by, and
selecting by the submitter's address would remove rows of other submissions. So this slice
removes attempts and captured copies, which are tied and are where the text of an email is kept,
and the spec's FR-011 is corrected to say what is true. A log row that names the submitter is
left to the log's own retention, and to slice 6, where an erasure is by address.

### D10. The email add-on fills a seam

`Corex\Mail\SubmissionEmailRecords::forget(list<string> $attemptIds): void`, in core. Bound to
a no-op in `corex-config`, as `SubmissionEmailGateway` is bound to "unavailable"; the add-on
binds its own, which deletes the attempt and the captured copy for each id. `EmailStudioStore`
gains `delete(int $id)`: it could create, update and find, and never remove.

### D11. Order, and what a failure leaves

For each submission: its files, then its email records, then the post. If a file cannot be
removed the submission is not deleted: deleting the post would lose the only pointer to the
file. It stays in the trash and the result names it and why (FR-016). One failure does not stop
the others (FR-015).

`delete()` returns what was deleted and what was not, each with its reason. The bulk action
reports the same, where every earlier bulk action was all or nothing.

### D12. The record

One activity entry per action: `submission.deleted`, who, when, how many, the form slugs, and
`by: person`. Slice 3 writes the same entry with `by: expiry`. No submitted value (FR-013). The
submission's own history goes with it, which is why this entry is the only record there is.

### D13. The notification

Left in place, pointing at a submission that is gone: its link opens the inbox, which says the
submission was not found. Deleting another person's notification because of something a third
person did is not this feature's to do, and FR-011 asks only that it lead to nothing that can be
opened.

### D14. The confirmation

A dialog that lists what is removed in plain words, says it cannot be undone, and keeps its
action disabled until a checkbox is ticked (FR-012). It says that export files made earlier may
still hold the submission, that they expire 30 days after they were made, and that they are
deleted under Export, Recent exports (FR-014): shown whenever the person has any kept export
file, which the dialog asks the existing history route.

**Routes**: `DELETE /submissions/{id}` for one; bulk action `delete`, offered in the trash only
and only to somebody who may.

## Slice 3: the trash's own clock (planned and built 2026-10-08, with slice 2 on main)

### D15. The number of days is a setting, saved with the retention policy

`corex_trash_submissions_days`: 30 where the site has said nothing, WordPress's own default, so
no site's trash starts behaving differently unasked; 0 for "until somebody deletes it". The field
stands in the retention panel beside the policy it is a neighbour of, and is saved by the same
form.

### D16. A date is not stored

A trashed submission has `corex_trashed_at`. The day it is deleted is that plus the setting,
worked out by the sweep and by the inbox from the same two numbers. Changing the setting changes
every date at once, which is what somebody changing it means.

### D17. The daily sweep deletes, through the one service

`SubmissionTrashRetention` is a `PrunableStore` in the retention sweep, beside the export files
and the notifications. It hands the trash's old submissions to
`SubmissionTrashService::expire()`: the same removal as a person's, recorded as the trash's own
(`by: expiry`, a cron actor). At most 100 a sweep; the next goes on.

### D18. WordPress's clean-up is kept off a submission three ways

1. A submission CoreX trashes has no `_wp_trash_meta_time` (slice 1).
2. A submission trashed WordPress's way is adopted the moment it is trashed (`trashed_post`):
   given CoreX's date from WordPress's, and WordPress's mark removed. The sweep adopts the ones
   that were trashed before this code existed.
3. WordPress's clean-up is refused a submission it still has a date for (`pre_delete_post`,
   while the `wp_scheduled_delete` action runs), which is the day between an old trashing and
   its adoption.

Not covered here, and slice 4's: on a site with `EMPTY_TRASH_DAYS` of 0 `wp_trash_post()` deletes
before any of these can act. The one caller left is the retention panel, which slice 4 routes
through the service.

### D19. A submission deleted by anything takes what is tied to it

`before_delete_post`: files and email copies go with a submission whoever deletes it. It cannot
be refused without breaking the caller, so it is not the place a file that will not go keeps its
submission; that stays the service's rule for deletions the inbox makes.

## What later slices will need from this one

- Slice 2 adds `delete()` to the service and a `SubmissionErasure` that knows what is tied to a
  submission. The email add-on fills a seam for what it holds.
- Slice 3 adds the clock (`corex_trashed_at` plus a setting), a `PrunableStore` for the daily
  sweep, and a `before_delete_post` hook so a deletion by anything else takes the tied data too.
  It also adopts submissions trashed by `wp_trash_post()` before this feature: they have
  `_wp_trash_meta_time` and no `corex_trashed_at`.
- Slice 4 routes the retention panel and the Data source through the service.

## Slice 4: one way to remove (planned 2026-10-09, with slice 3 on main)

### D20. Retention asks the trash service, once for the run

`SubmissionRetention` finds what is due, as before. For "Move to trash" it hands the ids and the
person to `SubmissionRetentionTrash`, an interface in the retention package that
`SubmissionTrashService` implements. The service moves each one the person may act on, writes each
one's history, and records the run once. A due submission that is not theirs is left and the rest
are moved: the person chose "everything that is due", not rows, so there is no selection to refuse
as a whole and no version to compare.

`RetentionController` asks `SubmissionAccessPolicy` for the person's scope. Nobody without one
reaches the retention service.

### D21. The reader cannot trash

`WpSubmissionsReader::trash()` and `trashForRetention()` are removed, with their lines in
`SubmissionsReader` and `SubmissionRetentionStore`. They were the last calls to
`wp_trash_post()`. A test reads the source for another.

### D22. The Data source declares no delete

`SubmissionsSource` declared `delete: true`. The Data screen drew a Delete button on a
submission from that, and the route behind the button refuses a source that has no write adapter,
which this one never had. The only code that performed the delete was `DataController`, which is
bound in the container and not registered. The source declares `delete: false`, and its
`delete()` answers the interface's "not permitted".

**Not in slice 4**: Archive and Anonymize from the panel still act on every due submission,
whoever runs them. Anonymize is slice 5.
