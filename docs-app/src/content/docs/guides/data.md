---
title: Manage submission data
description: Find and manage form submissions through the shared CoreX Data source contract.
---

**CoreX → Data** includes the registered submissions source alongside other CoreX models. Use its search, declared
filters, sortable fields, pagination, detail view, selected-row actions, and explicit-column exports. Ordinary records
exclude marked flow tests where the submissions source declares that policy.

The generic Data workspace is useful for schema-level record operations. For assignment, status, notes, email,
timeline, personal-data classes, and retention, use the dedicated [Submissions Inbox](/guides/submissions/).

## Source behavior

The submissions source implements the same actor-scoped query and detail contracts as other models. List counts and
direct IDs pass through its access policy. An operation is visible only when the source declares it, supplies the
required adapter, and maps it to an ability the current actor has.

**Export records** on the Records tab opens the export dialog over the model in view. Under **What to export** it
offers the rows you ticked, the current filters in words, or everything, each with how many records it holds. Choose
the columns and acknowledge personal-data fields. The dialog shows the export's progress and saves the file when it is
ready; there is nothing to refresh. The **Export** tab opens the same dialog for a whole model, and lists what was
exported before with a **Download** for each file that is ready. The file is a CSV, an Excel workbook where the model
offers one, or a PDF, headed by each field's label. A value is written as what its field is declared to be: a number
as a number, a date and time as one, a switch as Yes or No, a list as its items. A PDF holds up to 500 records and is
laid out and signed as [the Submissions PDF](/guides/submissions/#export-personal-data) is; it is offered only where
the server can write one. An export of no records is refused. CoreX writes the file in a bounded job, keeps it in the private uploads
directory, and exposes completed downloads through the authorized REST endpoint.

For query parameters, mutation previews, CSV imports, exports, migrations, and writing a custom source adapter, see
[Data management and adapters](/guides/data-management/).
