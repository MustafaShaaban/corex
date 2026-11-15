# Domain Docs

How the engineering skills read and write CoreX's domain documentation. CoreX keeps one glossary and one
decision record, both older than these skills, so the skills use them instead of creating their own.

| The skills expect | In CoreX it is |
|---|---|
| `GLOSSARY.md` at the root | **`docs/_glossary.md`** |
| `docs/adr/0001-slug.md`, one file per decision | **`DECISIONS.md`**, one `## #NNN -- Title` entry per decision |

Never create `GLOSSARY.md`, `GLOSSARY-MAP.md` or `docs/adr/`. This is a single-context repository.

## Before exploring, read these

- **`docs/_glossary.md`**: the terms and what they mean.
- **`DECISIONS.md`**: it is long (over 600 entries), so search it for the area you are working in
  (`grep -n "<term>" DECISIONS.md`) and read the entries that come up, rather than reading it whole.
- **The active spec** in `specs/`, which names the decisions it relies on.

## Use the glossary's vocabulary

When your output names a domain concept (in an issue title, a refactor proposal, a hypothesis, a test name),
use the term as defined in `docs/_glossary.md`. Don't drift to synonyms the glossary avoids.

If the concept you need isn't in the glossary yet, that's a signal: either you're inventing language the
project doesn't use (reconsider) or there's a real gap (add it, below).

## Flag decision conflicts

If your output contradicts an existing decision, surface it explicitly rather than silently overriding:

> _Contradicts DECISIONS #299 (add-on tables declared to the migration runner), but worth reopening because…_

## Writing them

- **A term**: add a row to the table in `docs/_glossary.md`, in the form the existing rows use
  (`| **Term** | Plain-English definition | _TODO_ |`; the last column is the Arabic, filled in later).
- **A decision**: append an entry to `DECISIONS.md` with the next free number. Read `main` **and** the open
  pull requests first (`gh pr diff <n> | grep -oE '^\+## #[0-9]+'`), because several sessions take numbers
  at once. The entry carries **Date**, **Branch**, **Mode**, then **Why**, **Decided** and **Not chosen**.
- Write the term or decision as soon as it is settled, not at the end of the session.
