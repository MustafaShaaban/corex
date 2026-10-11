---
title: Personal data in exports
description: Which columns CoreX treats as personal data, who may take them out of the site in a file, and how a table of your own says what it holds.
audience: team
stability: stable
last_verified: 2026-10-11
---

# Personal data in exports

An exported file leaves the site, and what it holds about people leaves with it. CoreX marks
the columns that hold personal data, offers them only to a person allowed to take personal data
out, and asks that person to confirm what the file contains.

## What counts as personal data

Each model on **CoreX → Data** says, column by column, what it holds. The **Models** tab shows
it beside each field as "Personal data:" and a kind.

| Kind | In words on the screen | An example |
|---|---|---|
| `contact` | contact details | a subscriber's email address |
| `identity` | names and identities | an applicant's name, who did something in the activity log |
| `content` | what people wrote | an answer to a form |
| `security` | sign-in and security records | whose login attempt it was |
| `behavioral` | interests and behaviour | the topics a subscriber chose |
| `sensitive` | sensitive details | an applicant's CV |
| `none` | nothing shown | a status, a date, a count |

Two rules have no exception:

- **Every answer of a form submission is personal data.** An address or a phone number is
  contact details; any other answer is what a person wrote. The same holds in the Submissions
  inbox.
- **A column nobody classified is treated as personal data.** It is shown as "data nobody has
  classified yet". A table your site declares is in this state until it says otherwise (below).

## Who may export it

Being allowed to export a model is being allowed to export what in it is not about a person.
The columns that hold personal data need a right of their own, **Export personal data**
(`corex_export_personal_data`).

- An administrator holds it.
- Anybody else is given it on **CoreX → Access & Abilities**, like any other ability.
- Managing data, data models or submissions does not carry it.

A person without the right still exports. The dialog offers them the other columns and says how
many it is not offering, and the route refuses a personal column from them whatever is sent.

A person with the right is asked to confirm before the export starts. The confirmation names
the kinds of personal data in the columns they chose.

The Submissions inbox asks for the same right. A site that already decides this in code keeps
the last word: the filter `corex_submission_export_personal_data` still receives the answer and
may change it.

## A table of your own

A table registered with `ManagedTable` declares its columns' kinds with the `personalData`
argument, using the constants of `Corex\Data\DataField`:

```php
use Corex\Data\DataField;
use Corex\Database\Schema\ManagedTable;

new ManagedTable(
    'enquiries',
    'Enquiries',
    [
        ['id' => 'name', 'label' => 'Name'],
        ['id' => 'email', 'label' => 'Email'],
        ['id' => 'status', 'label' => 'Status'],
        ['id' => 'created_at', 'label' => 'Received'],
    ],
    personalData: [
        'name'       => DataField::PERSONAL_IDENTITY,
        'email'      => DataField::PERSONAL_CONTACT,
        'status'     => DataField::PERSONAL_NONE,
        'created_at' => DataField::PERSONAL_NONE,
    ],
);
```

- Name every column. One you leave out is unclassified, and is treated as personal data.
- `DataField::PERSONAL_NONE` is a statement: that the column holds nothing about a person. A
  wrong one is exported to somebody who was never asked.
- A kind CoreX does not know, or a column the table does not have, is refused when the table is
  constructed.

A table that passes no `personalData` keeps working. Every one of its columns then asks for the
right and the confirmation, which is the safe reading of a table nobody has looked at.

## What is recorded

A finished export is written to the activity log with who made it, which model, which columns
and how many records. No value of any record is written there.
