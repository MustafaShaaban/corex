# Data model: several data models exported, imported and moved together

The entities of [spec.md](./spec.md), as [plan.md](./plan.md) stores them. Field names are the
intent; tasks fix the exact names against the code they join.

## A model's declaration (D1, D2)

Not stored: declared in code, on `ManagedTable` for a table and by the source for submissions.

| Statement | Values | Default | Read by |
|---|---|---|---|
| `personalData` | per column, one of `none`, `contact`, `identity`, `content`, `security`, `behavioral`, `sensitive` | every column `undeclared` | every export, a package, the replacer |
| `identity` | a list of columns | none | the import's trial run, receiving |
| `transfer` | `born_in_production`, `built_in_development` | not movable | making and receiving a package |
| `transferFiles` | the columns that hold an uploaded file | none | making and receiving a package |
| `transferPeople` | the columns that hold an account's number | none | making and receiving a package |
| `category` | `records`, `log` | `records` when the table is writable or movable, else `log` | the checklist's two groups |

Rules:

- `undeclared` is a class of its own. Every check treats it as personal data.
- `identity` columns must be columns of the table. A model with `transfer` and no `identity`
  is recognised on another install by its record identifier alone.
- `built_in_development` is declared by no model CoreX ships (the owner's second decision).

What CoreX's own tables declare, to be confirmed column by column in slice 1:

| Model | Personal data | Identity | Transfer |
|---|---|---|---|
| Form submissions | every answer: `contact` when it reads as an email address or a phone number, else `content`; the summary `content` | none | born in production; files |
| Newsletter subscribers | `email` contact, `topics` behavioral | `email` | born in production |
| Job applications | `name` identity, `email` contact, `cv_attachment` sensitive | none | born in production; files: `cv_attachment` |
| Access requests | `requester_id` identity | none | no |
| Activity events | `actor_label` identity, `target_label` content | none | no |
| Login attempts | `user_id` security | none | no |
| Notification user state | `user_id` identity | none | no |
| Role ability grants, bounded jobs, notifications, reading events | every column `none` | none | no |

## Export run (D4, D5)

A private post of type `corex_data_export`, as today. Its payload gains `parts`.

| Field | Meaning |
|---|---|
| `actor_id`, `job_id`, `created_at`, `state`, `input_hash` | as today |
| `format`, `separator` | as today; one for the whole run |
| `parts[]` | `source_key`, `columns`, `record_count`, `exported_rows`, `personal_data_classes` |
| `scope`, `selected_ids`, `query` | only when there is one part |
| `file` | name, extension, content type, bytes |
| `file_removed` | none, or why and by whom: expired, or deleted |

- `state`: `queued` → `completed`. A failure is the job's, as today.
- A stored run with `source_key` and `columns` and no `parts` reads as one part.
- Expiry is not stored: 30 days from `created_at`, as the Submissions export counts it.
- A run with several parts takes every record of each part's source.

## Record origin (D6, D7)

The table `corex_record_origins`, one per site. Not listed on the Data screen.

| Column | Meaning |
|---|---|
| `source_key` | the model |
| `record_uuid` | the record's identifier, the same on every install |
| `local_id` | the record's number here; empty while it is being created and when it was deleted |
| `born_install` | the identifier of the install the record was born on |
| `receipt_id` | the receipt that brought it here; empty for a record born here |
| `state` | `claiming`, `here`, `deleted` |
| `updated_at` | when the row last changed |

- Unique: `source_key` with `record_uuid`. Indexed: `source_key` with `local_id`.
- `claiming` → `here` when the record exists; a `claiming` row older than ten minutes is taken
  over by the next run of the same package.
- `here` → `deleted` when the record is deleted for good: `local_id` is emptied and nothing
  else of the record was ever in the row.
- A row is never removed. A `deleted` row is the note FR-036 asks for.

## Install identity (D6)

One option per site.

| Field | Meaning |
|---|---|
| `id` | a UUID made on first use |
| `address` | the site address it was made for |
| `before[]` | earlier identifiers, each with the moment this install stopped being that one |

A record's identifier is derived from the identifier in force when the record was created,
the model, the record's number and its creation moment.

## Data package run (D9)

A private post of type `corex_data_package`.

| Field | Meaning |
|---|---|
| `actor_id`, `job_id`, `created_at`, `input_hash` | as an export run |
| `models[]` | `source_key`, `record_count`, `written` |
| `replace_personal_data` | forced on for a person without the right of D3 |
| `include_files`, `file_count`, `file_bytes` | what was asked and what was stated before the start |
| `state` | `queued`, `completed` |
| `file` | name, bytes, SHA-256 |
| `file_removed` | none, or expired or deleted, and by whom |

Expiry: 7 days from `created_at`.

## Data package (D8)

The file. Its format is [contracts/data-package.md](./contracts/data-package.md).

## Receipt (D11, D18)

A private post of type `corex_data_receipt`.

| Field | Meaning |
|---|---|
| `actor_id`, `created_at`, `job_id` | who gave the package, when, the job that writes it |
| `package` | SHA-256, file name, bytes, format version |
| `from` | the manifest's install: identifier, address, mode, live or not; when and by whom it was made; whether personal data was replaced |
| `here_live` | what this install was taken to be when the preview was made |
| `models[]` | `source_key`, `verdict` (taken or refused, with the reason), `fields_left_out`, `update_differing`, and the counts: `new`, `same`, `different`, `deleted_here`, `refused`, then `added`, `updated`, `arrived_since`, `failed` |
| `state` | `inspecting`, `previewed`, `receiving`, `received`, `refused`, `expired` |
| `confirm_until` | 30 minutes after the preview |

- `inspecting` → `previewed` or `refused`; `previewed` → `receiving` on confirmation by the
  same person, or `expired`; `receiving` → `received`.
- The identifiers to add and to bring up to date are a working file beside the package, named
  for the receipt, removed with the package.
- A receipt is kept after its package is gone. It holds counts and no value of any record.

## Import run (D15)

As today, with:

| Field | Meaning |
|---|---|
| `duplicate_rows[]` | line, identity, and where it was found: in the model, earlier in this file, or in a named earlier run |
| `after_runs[]` | earlier runs of the same model and person whose accepted rows count as present |

The run's hash covers both, so approval is still bound to exactly what was shown.
