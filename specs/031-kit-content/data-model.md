# Data Model: Kits that build a real site (031)

## Kit page (declared by a Blueprint)
| Field | Type | Notes |
|---|---|---|
| `title` | string | page title |
| `slug` | string | post_name; identity for idempotency |
| `content` | string | block markup composing corex/* patterns |
| `front` | bool (optional) | when true, set as the static front page |

## KitPagePlanner (pure)
`toCreate(list<page> $declared, list<string> $existingSlugs): list<page>` — returns the declared pages whose
slug is not in `$existingSlugs` (idempotent).

## Seeded tracking
- Each created page: `update_post_meta($id, '_corex_kit_page', '1')`.
- `corex_kit_seeded_pages` option: a list of created page ids (appended, de-duped).

## CompanyBlueprint::pages()
- `home` (front): hero + features + cta + contact patterns.
- `about`: a heading + paragraph.
- `contact`: the contact pattern (composes corex/form).

## PortfolioBlueprint::pages()
- `home` (front): hero + a projects intro.
- `projects`: a heading + the corex/projects block.
