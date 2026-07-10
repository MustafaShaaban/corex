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

Exports support the current filter, selected rows, or all accessible rows. Choose explicit columns and acknowledge
personal-data fields. CoreX queues a bounded private artifact and exposes completed downloads through the authorized
REST endpoint.

For query parameters, mutation previews, CSV imports, exports, migrations, and writing a custom source adapter, see
[Data management and adapters](/guides/data-management/).
