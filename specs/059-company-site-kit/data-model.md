# Phase 1 Data Model: Company Site Kit v1

No new database schema. The kit manifest is pure, read-only data; pages are created at apply time through the existing
`corex-core` provisioning. Entities below define the shapes M4 adds/extends.

## Entities

### Blueprint (extended: `CompanyBlueprint`)

- **Represents**: the company kit manifest — required/recommended modules, templates, parts, patterns, and the page
  set. Pure/read-only (no behavior, no DB).
- **Extended fields/methods**: `pages(level)` returns the full v1 page list for a demo level; optional `demoLevels()`
  enumerates `minimal|standard|full`; each page may carry SEO starter fields.

### Kit page

- **Fields**: `title` (i18n), `slug`, `content` (token-only block markup composing patterns/core blocks),
  `front?` (bool — the static front page), `seo?` (`{title, description, og?}` editable defaults).
- **Invariants**: no raw hex/size/font; correct heading order; composes only real registered patterns/blocks +
  M3 parts; logical CSS only (inherited).

### Demo level

- **Values**: `minimal | standard | full`. Same page set and section order across all levels; only example content
  depth varies. `standard` is the default.

### SEO starter

- **Fields**: per-page `title`, `description`, optional Open Graph defaults — editable post meta / title defaults
  that common SEO plugins read and override. No SEO engine, no plugin dependency.

### Apply plan (reused, not new)

- `ApplyPreview` — the summarized list of creates/changes/skips before mutation.
- `PageDisposition` — `reset | adopt | skip | conflict` behavior for existing slugs.
- `ApplyOutcome` / `KitSummary` — the result of an apply.

## Relationships

- A **Blueprint** has many **Kit pages**; one page may be `front`.
- A **Kit page** has zero or one **SEO starter**.
- Applying a Blueprint at a **Demo level** produces an **Apply plan** (preview) → on confirm, pages created via
  provisioning with the chosen **PageDisposition**.

No state is added to the framework DB by the manifest; provisioning uses core page/option APIs at apply time.
