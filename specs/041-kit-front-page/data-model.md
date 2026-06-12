# Data Model: Kit Apply Must Never Leave a Blank Front Page

No new storage system. These are the in-memory value shapes plus the (extended) existing persistence.

## Declared kit page (input — unchanged shape from spec 031)

From a blueprint's `pages()`:

| Field | Type | Description |
|---|---|---|
| `title` | `string` | Page title. |
| `slug` | `string` | Page slug (post_name). |
| `content` | `string` | Block markup composing the kit's patterns. |
| `front` | `bool` (optional) | Whether this page should become the site front page. |

## Per-slug page signal (input to the pure planner — produced by the WP boundary)

| Field | Type | Description |
|---|---|---|
| `exists` | `bool` | A `page` already exists at this slug. |
| `isEmpty` | `bool` | The existing page's content is blank (`KitPageContent::isBlank`). |
| `isKitPlaceholder` | `bool` | The existing page carries `_corex_kit_page` meta **and** is empty (a Corex page never populated). |

## Page disposition (pure planner output; shared by apply + summary)

| Field | Type | Description |
|---|---|---|
| `slug` | `string` | The declared slug. |
| `title` | `string` | The declared title. |
| `action` | `create` \| `adopt` \| `skip` | The classification. |
| `reason` | `string` (machine code) | e.g. `slug_absent`, `existing_empty`, `kit_placeholder`, `user_content`. |

Classification rule (pure):
- `!exists` → **create** (`slug_absent`)
- `exists && isKitPlaceholder` → **adopt** (`kit_placeholder`)
- `exists && isEmpty` → **adopt** (`existing_empty`)
- `exists && !isEmpty` → **skip** (`user_content`)

## Apply outcome (BlueprintActivator return; reused by spec 042)

| Field | Type | Description |
|---|---|---|
| `pages` | `list<PageResult>` | One per declared page. |
| `modules` | `list<string>` | Modules activated during apply. |
| `flags` | `list<string>` | Feature flags enabled during apply. |
| `frontPageId` | `int\|null` | The page set as the front page, if any. |

**PageResult** = `PageDisposition` + `{ pageId: int|null, isFront: bool, persistedAs: created|adopted|null }`
(`persistedAs` is null for a skipped page; `created` vs `adopted` is what the reset disposition meta records).

## Persistence (extended, not new)

| Store | Before | After |
|---|---|---|
| post meta `_corex_kit_page` | `'1'` on seeded pages | `created` or `adopted` (legacy `'1'` read as `created`) |
| option `corex_kit_seeded_pages` | `list<int>` of seeded page ids | unchanged shape: the index of all kit-touched ids (created + adopted); an id is **removed** when a reset empties an adopted page |
| option `show_on_front` / `page_on_front` | set inside create loop | set after the loop when the declared home was created or adopted; untouched when home was skipped |

## State transitions (a declared home page)

```
slug absent ───apply──► CREATE  → page (meta=created) + front page set
empty existing ─apply─► ADOPT   → page populated (meta=adopted) + front page set
kit placeholder ─apply► ADOPT   → page populated (meta stays created) + front page set
user content ──apply──► SKIP    → page untouched, front page unchanged

created page ──reset──► deleted (front page reverted if it pointed here)
adopted page ─reset───► emptied + untracked (post kept; front page left as-is)
```

## Validation / invariants

- Apply is idempotent: re-running yields the same dispositions (a now-populated page is no longer empty → its
  declared entry classifies as `skip` on the second run, so content present at apply time is never overwritten).
- An adopted page is never deleted by reset; a created page always is.
- The front page after apply is a page that was created or adopted (never left pointing at a blank page) unless
  the declared home was skipped because the user already had real content there.
