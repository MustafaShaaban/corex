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

## What later slices will need from this one

- Slice 2 adds `delete()` to the service and a `SubmissionErasure` that knows what is tied to a
  submission. The email add-on fills a seam for what it holds.
- Slice 3 adds the clock (`corex_trashed_at` plus a setting), a `PrunableStore` for the daily
  sweep, and a `before_delete_post` hook so a deletion by anything else takes the tied data too.
  It also adopts submissions trashed by `wp_trash_post()` before this feature: they have
  `_wp_trash_meta_time` and no `corex_trashed_at`.
- Slice 4 routes the retention panel and the Data source through the service.
