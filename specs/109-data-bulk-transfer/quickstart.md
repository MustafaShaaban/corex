# Quickstart: showing each slice works

How a slice of [plan.md](./plan.md) is shown to do what [spec.md](./spec.md) asks. It is a
validation guide: the routes are in [contracts/routes.md](./contracts/routes.md) and the
package's format in [contracts/data-package.md](./contracts/data-package.md). Nothing below
exists yet.

## Before any of it

- A WordPress the framework is mapped into, with `corex-core`, `corex-forms`, `corex-config`
  and the newsletter and careers add-ons active (`wp plugin list`), signed in as an
  administrator. The Environment Gate comes first.
- A second person who manages submissions and data and is not an administrator.
- For slices 4 to 6, a second install: the integration tests use a second table prefix in the
  same database; by hand, any second site.
- CI is the authority for the integration and browser suites (`PROGRESS.md`). The commands
  for each suite are in `CONTRIBUTING.md`.

## Slice 1 — personal data is marked, and asked about

1. CoreX → Data → Models: newsletter subscribers shows `email` as contact; no column of job
   applications that holds a name or an address shows as holding none.
2. As the administrator, Export → Newsletter subscribers: the dialog names the kinds of
   personal data and will not start until the box is ticked.
3. As the second person: the same dialog does not offer `email`. Asking the route for it
   anyway answers `personal_data_not_allowed`.
4. Submissions inbox, as the second person: the personal columns are withheld as before.
5. Access & Abilities: "Export personal data" is listed and can be given; after it is, step 3
   offers `email`.

Expected in tests: a table that declares nothing has every column `undeclared`; the activity
log's export asks for the acknowledgement only for the columns it declared as personal.

## Slice 2 — several models in one export

1. Export: every model the person may export is listed with its count, in two groups, none
   ticked. Tick three, one of them empty.
2. The dialog says the empty one is left out, and states the count per model.
3. Excel: one workbook is saved, a sheet per model named for it, saying what was exported, by
   whom and when. The rows per sheet equal the counts stated.
4. CSV: one zip, a file per model. PDF: offered while the total is within 500, and the dialog
   says why when it is not.
5. The history shows the export once with its models, size and expiry; "Download" saves it
   again; "Delete" removes the file and leaves the entry; the activity log has both.
6. Close the dialog mid-export and reopen the tab: the export is listed as unfinished, and
   "Continue" finishes it.
7. Tick one model: the dialog is today's, with its columns and scopes.

Expected in tests: a run stored before this slice reads as one part; an export 31 days old
loses its file to the sweep and keeps its entry.

## Slice 3 — an import knows what is already there, and takes several files

1. Import newsletter subscribers from a CSV of ten addresses, three of them already
   subscribed: the trial run says 7 accepted, 3 already there, and approval adds 7.
2. Import the same file again: 10 already there, and there is nothing to approve.
3. Give two files at once, one for subscribers and one for job applications: each is shown
   with a suggested model that can be changed; one trial run reports both; approve both in one
   action.
4. Give two files for subscribers that share two addresses: the second file reports the two
   as already there, found in the first.
5. A file with a rejected row and a clean file: the clean one can be approved alone.

Expected in tests: `Name@Example.com` and `name@example.com` are one identity; the hash that
binds approval changes when `after_runs` does.

## Slice 4 — a package is made

1. Move → Make a package: form submissions, newsletter subscribers and job applications are
   listed with their counts; the logs are named as not movable, with the reason.
2. Tick all three with files: the screen states the records per model and the number and size
   of the files, then makes the package in steps with its progress.
3. The file is offered, named for the site and the day. Unzipped, it holds the description, a
   records file per model and the files; every hash in the description matches.
4. A subscriber's line holds `status` and `created_at`; a lead's holds its status, its notes
   and its owner as an email address.
5. As the second person without the personal-data right: a package cannot be made, and the
   screen says which right is missing. From slice 6 the same person makes one with the
   personal data replaced.
6. Make the same package twice: every record has the same identifier in both.

Expected in tests: a database copied to a second prefix gives each old record the same
identifier on both; a record created on the copy after its address changed does not share an
identifier with any record of the original.

## Slice 5 — a package is received, and a live install keeps what it has

On an install in Development, empty:

1. Move → Receive a package: give the file. It uploads in parts; cutting the connection and
   giving the file again goes on from where it stopped.
2. The preview states where and when the package was made and, per model, how many are new.
   Confirm: the counts added equal the preview's. A confirmed subscriber is confirmed, with
   the day they subscribed, and no confirmation email is sent.
3. Receive the same package again: every record is "already here"; nothing is added.

On an install in Production holding ten leads, three edited since the package was made and
one deleted for good:

4. The preview counts three as "already here, left as it is" and one as "deleted here, not
   brought back", and offers no way to bring anything up to date.
5. After confirming, the ten are unchanged and the deleted one is still gone.
6. A package made on a Development install is refused for the models born in production; a
   lead anonymised here by retention stays anonymised.
7. A file with one byte changed is refused as damaged before anything is written; so is a
   file that is not a package.
8. The receipts list shows each receiving with its counts; the activity log has it without
   any value.

Measured in this slice and written into the guide: a package of 20,000 records, with and
without files, made and received with PHP held to 128 MB and 30 seconds, the largest request's
time and memory, and the size of the file.

## Slice 6 — personal data replaced

1. Make a package with "Replace personal data": search the unzipped package for any email
   address, phone number or name of the original records. None is found, and no file is
   inside.
2. Two leads from the same address hold the same made-up address.
3. Make a second such package: the made-up address for that person is a different one.
4. Give the package to an install in Production: refused, with the reason.
5. Receive it in Development: the records arrive with their dates, forms and statuses.

## Across all of it

Each tab is walked with the keyboard alone, in light and dark, left-to-right and Arabic, at a
wide and a narrow width, and its spacing is read off the rendered page before it is presented
(the UI/UX gate). Every string is checked to come through `corex`.
