# Contract: what a model declares

For whoever writes a model: CoreX, an add-on, or a client site (plan D1, D2). Proposed here;
`ManagedTable` takes none of these arguments yet.

## A managed table

Optional named arguments beside the ones `ManagedTable` has. A table that passes none
behaves as it does today, with one difference: its columns count as personal data until it
says otherwise.

```php
new ManagedTable(
    self::NAME,
    'Newsletter subscribers',
    [/* columns, as today */],
    'corex',
    [/* writable fields, as today */],
    ManagedTable::UNKNOWN_REJECT,
    [/* migrations, as today */],
    ['status' => 'pending'],
    personalData: [
        'email'      => DataField::PERSONAL_CONTACT,
        'topics'     => DataField::PERSONAL_BEHAVIORAL,
        'status'     => DataField::PERSONAL_NONE,
        'consent'    => DataField::PERSONAL_NONE,
        'created_at' => DataField::PERSONAL_NONE,
    ],
    identity: ['email'],
    transfer: ManagedTable::BORN_IN_PRODUCTION,
);
```

| Argument | Says | When left out |
|---|---|---|
| `personalData` | each column's class. A column not named is `undeclared` | every column is `undeclared`, which every export treats as personal data |
| `identity` | the columns that make a record the same record | an import cannot tell a row that is already there; a move knows a record by its identifier alone |
| `transfer` | that the records can be moved, and which way | the model is not offered for a move |
| `transferFiles` | the columns holding an uploaded file | a move carries no file for this model |
| `transferPeople` | the columns holding an account's number | a move carries such a column as it is, which means nothing on another install |
| `category` | `records` or `log`, for the two groups of the checklist | `records` if the table is writable or movable, else `log` |

What is refused when the table is constructed, as a wrong writable field is today:

- a class that is not one of `DataField`'s;
- an `identity`, `personalData`, `transferFiles` or `transferPeople` column that is not a
  column of the table;
- `transferFiles` or `transferPeople` without `transfer`.

## What a declaration promises

- **`personalData`**: that a column classed `none` holds nothing about a person. A wrong
  `none` is exported to somebody never asked.
- **`identity`**: that two rows with the same values in those columns are one record. A wrong
  one makes an import or a move drop a record it took for a duplicate.
- **`transfer`**: that a record can be created on another install from every column the table
  has, and means the same there. A column holding another table's number, or an account's,
  does not, unless it is declared as a person (below).

A table whose rows point to accounts declares those columns:

```php
transferPeople: ['user_id'],
```

A move then carries the account's email address in place of the number and looks the account
up on the other side; with no match the column is left empty. A table that needs that column
to hold somebody is not movable.

## A source that is not a table

It implements `TransferableDataSource` itself, as the submissions source does:

| Method | Answers |
|---|---|
| `transferDeclaration()` | the kind, the fields with their classes, the identity |
| `transferCount()` | how many records a package would hold |
| `transferRecords(cursor, limit)` | the next whole records, and the cursor after them |
| `receive(record)` | creates one record from a whole record; answers its number here |
| `bringUpToDate(localId, record)` | writes a whole record over the one here; never called on a live install |
| `holds(identities)` | which of these identities the model holds |

## Testing a declaration

Before a model is shipped as movable: make a package of it, receive it on an empty install,
and compare every field of three records; then receive the same package again and see none
added. `tests/Integration/Transfer/` will hold that round trip for CoreX's three, as the
pattern to copy.
