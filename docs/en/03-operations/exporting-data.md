---
title: Exporting data
description: Export one model or several from CoreX → Data into one file, find past exports, and know when their files are removed.
audience: team
stability: stable
last_verified: 2026-10-11
---

# Exporting data

**CoreX → Data → Export** lists every model you may export. Tick one, or several, and export
them into one file.

An export is a file for a person to read: columns headed by their labels, a list written as
words. It cannot be given back to CoreX. Bringing records in is the **Import** tab.

## Choosing the models

The models are in two groups:

- **Records**: what the site receives or keeps for its work, such as form submissions,
  newsletter subscribers and job applications.
- **Logs**: the site's own history, such as the activity log, login attempts and background
  jobs.

Each model shows how many records it holds. Nothing is ticked for you. **All records** and
**All logs** tick a whole group.

A model you may not export is not in the list. A line under the list says how many are not
shown; the **Models** tab says what each model supports.

## One model

With one model ticked, the button names it (**Export Form submissions…**) and opens the dialog
for that model. You choose its columns and the format, and the export takes every record in it.

To export only the rows you ticked, or only the rows matching the filters you set, open the
**Records** tab and use **Export** there.

## Several models

With two or more ticked, the button says how many (**Export 3 models…**) and opens one dialog
for all of them. Every record of each model is exported.

- Each model is listed with how many records it holds. Open a model to choose which of its
  columns to export; left alone, every column you may export is included.
- A model that holds no records is left out, and the dialog says so before you export.
- One file is made:

| Format | What you get |
|---|---|
| Excel workbook | one workbook, with a sheet for each model |
| CSV | one archive (`.zip`), with a CSV file for each model |
| PDF | one document, with a section for each model, while the records of all the models together are within what a PDF holds |

If a model can no longer be read while the export is being written (its add-on was switched
off, for example), the others are still exported. The history marks that model as left out.

## Records that belong to another screen

Three models hold security records: **Role ability grants** and **Access requests** belong to
Access & Abilities, and **Login attempts** to Operations & Security. "Manage data" alone does not
open them: a person also needs the ability of the screen they belong to (administrators have
both). Without it, the Data screen does not list them, every route that reads them answers `403`
(`data_forbidden`), and each refusal is recorded in the Access & Abilities audit log.

An export of several models that includes one of them is made without it, and says which model
was left out and why. An export of nothing but such models is refused, naming them. An earlier
export that holds one of them is not downloaded by a person who no longer has the ability.

A table a site declares itself can say the same with `ownerAbility:` in its `ManagedTable`.

Columns that hold personal data need a right of their own and a confirmation: see
[Personal data in exports](personal-data-in-exports.md).

## Getting the file

The dialog shows the export's progress and the browser saves the file when it is ready. You can
close the dialog while a large export is still being written; a notification tells you when it
is ready and opens the Export tab.

On a host where scheduled tasks do not run, an export you left unfinished waits. It is listed
under **Export history** as "Not finished", with **Continue**, which goes on from where it
stopped.

## Export history

Every export made from the Data screen is listed once, newest first, with the models it
covered, its format, how many records, the size of its file, who made it and when.

- **Download** saves the file again.
- **Delete** removes the file after asking once more. The entry stays and says who deleted it.

You see your own exports. An administrator sees everybody's.

## When files are removed

An export's file is removed **30 days** after the export was made. The entry stays in the
history and says the file expired. Nothing removed a Data export's file before this was
introduced; files older than 30 days are removed the first time the daily clean-up runs after
the site takes the release.

## What is recorded

Finishing an export and deleting its file are each written to the activity log, with who,
which models and how many records. No value of any record is written there.
