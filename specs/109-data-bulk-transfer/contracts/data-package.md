# Contract: the data package

One zip, made by CoreX for CoreX (plan D8). Format version 1. Proposed here; nothing writes or
reads it yet.

## Name

`corex-data-<site>-<yyyy-mm-dd>-<8 hex>.zip`, where `<site>` is the site's name folded to
letters, digits and hyphens. An upload with any other name is refused before it is opened.

## Members

```text
corex-data-package.json          the description; read first, and nothing else until it checks
models/<source-key>.ndjson       one record per line, UTF-8
files/<record-uuid>/<n>-<name>   an uploaded file a record points to; only when files were asked for
```

No other member is read. A member whose name holds `..`, a leading `/` or a drive letter
refuses the package.

## The description

```json
{
  "format": 1,
  "made_at": "2026-10-11T09:30:00+00:00",
  "made_by": "A person's display name",
  "corex": "0.45.0",
  "install": {
    "id": "0b6f6a1e-…",
    "address": "https://example.com",
    "mode": "production",
    "live": true
  },
  "personal_data": "original",
  "files": true,
  "models": [
    {
      "source": "submissions",
      "label": "Form submissions",
      "kind": "born_in_production",
      "identity": [],
      "fields": [
        { "key": "status", "type": "text", "personal": "none", "required": false }
      ],
      "records": 412,
      "member": "models/submissions.ndjson",
      "bytes": 1830021,
      "sha256": "…"
    }
  ],
  "file_count": 37,
  "file_bytes": 48211004
}
```

- `personal_data` is `original` or `replaced`.
- `fields` is every field a record's `fields` may hold, with the class the model declared.
- `sha256` is of the member as stored. A member that hashes differently refuses the package.
- A `format` larger than the reader knows refuses the package and names both numbers.

## A record

One line of `models/<source-key>.ndjson`:

```json
{
  "uuid": "5c0f…",
  "born": "0b6f6a1e-…",
  "created_at": "2026-09-30T14:02:11+00:00",
  "updated_at": "2026-10-02T08:15:40+00:00",
  "hash": "…",
  "fields": { "status": "contacted", "…": "…" },
  "people": { "owner": "name@example.com" },
  "files": [
    { "field": "cv", "name": "cv.pdf", "member": "files/5c0f…/1-cv.pdf", "bytes": 80211, "sha256": "…", "type": "application/pdf" }
  ]
}
```

- `uuid` is the record's identifier (plan D6). `born` is the install it was born on, which is
  not always the install that made the package.
- `hash` is of `fields` in a fixed order, and is what "here and the same" compares.
- `fields` holds every field the model keeps, with the values as stored, not as displayed.
- `people` names an account by email address, for a field that holds an account's number on
  the install that made the package. It is personal data and is replaced with the rest.
- `files` is present only when the package holds files. A field in `fields` that points to a
  file holds nothing an install could mistake for its own attachment's number.

## What a reader does with it

1. Checks the name, then the description, then every member's hash.
2. Compares each model's `fields` with the model here: a field not known here is left out and
   said; a required field missing refuses that model.
3. Validates every value against the field's type and sanitises it as input. A record that
   fails is counted as "cannot be taken", with the reason, and the rest go on.
4. Never runs anything, never unserialises anything, never follows a path from the package
   outside the place the package was unpacked to.

## What it never holds

A password, a key, a token, a setting, an account, an email log, a job, or a record of a model
that did not declare itself movable.
