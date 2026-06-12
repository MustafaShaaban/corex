# Research: Kits that build a real site (031)

## R1 — Idempotency by slug
**Decision**: A page is created only if no post with its slug exists. The pure `KitPagePlanner.toCreate()`
filters the declared pages against the existing slugs. **Rationale**: re-applying a kit must not duplicate;
slug is the natural identity for a page. **Alternatives**: a "seeded" flag only (rejected — wouldn't catch a
user-created page of the same slug); always create (rejected — duplicates).

## R2 — Reversibility by marker
**Decision**: Seeded pages get a `_corex_kit_page` meta marker and their ids are appended to a
`corex_kit_seeded_pages` option, so the soft reset removes exactly them. **Rationale**: the reset must remove
only kit content, never user content; an explicit id list + marker is exact. **Alternatives**: delete by slug
at reset (rejected — could delete a user page of the same slug).

## R3 — Page content composes existing patterns
**Decision**: Page content is block markup that references existing `corex/*` patterns
(`<!-- wp:pattern {"slug":"corex/hero"} /-->`, features, cta, contact) — never invented patterns. **Rationale**:
the patterns already exist (spec 009) and the front-page template composes them; a seeded page reuses them so
it renders identically. **Alternatives**: inline raw block markup per page (rejected — duplicates the patterns).

## R4 — One seeder, replacing the single demo home
**Decision**: `BlueprintActivator` gains a general `seedPages()` that supersedes the old single `seedDemoHome`;
the wizard's plan carries the blueprint's `pages()`. **Rationale**: one code path seeds all kit pages
(front + others) consistently and tracks them. **Alternatives**: keep seedDemoHome + a separate path (rejected —
two mechanisms, drift).
