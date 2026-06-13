# Research: Kit Apply Must Never Leave a Blank Front Page

Phase 0 decisions. The spec left no `NEEDS CLARIFICATION`; these record the design that resolves the HOW.

## D1 — Three-way classification, kept pure

**Decision**: Replace `KitPagePlanner::toCreate(declared, existingSlugs): list` with
`KitPagePlanner::plan(declared, signals): list<PageDisposition>`, where `signals` is a per-slug map of
`{exists: bool, isEmpty: bool, isKitPlaceholder: bool}`. Each declared page becomes a `PageDisposition` with
action **create** (`!exists`), **adopt** (`exists && (isEmpty || isKitPlaceholder)`), or **skip**
(`exists && hasUserContent`), plus a machine reason for the summary.

**Rationale**: The classification is the whole feature and must be headlessly testable (FR-007/SC-005). Pushing
the WP reads (does the page exist, is it empty, is it a kit placeholder) into the boundary keeps the planner a
pure function of its inputs — every case (create / adopt-empty / adopt-placeholder / skip-user) is a table test
with no WordPress. `existingSlugs` was a lossy input (only "exists yes/no"); a structured signal carries the
emptiness/placeholder facts the adopt decision needs.

**Alternatives considered**:
- *Keep `toCreate` and add a separate `toPopulate`*: two passes over the same data, two places to keep in sync,
  and the front-page decision still needs the merged view. One `plan()` returning all dispositions is simpler.
- *Let the planner read WordPress*: breaks purity/testability (Principle IV) and the constitution's boundary rule.

## D2 — "Empty / adoptable" predicate

**Decision**: A pure `KitPageContent::isBlank(string $content): bool` returns true when the content, after
trimming, is empty, is only whitespace, or is a single empty paragraph block (`<!-- wp:paragraph --><p></p>
<!-- /wp:paragraph -->` with no inner text) or a bare `<p></p>`. Any real block comment (`<!-- wp:… -->` other
than an empty paragraph) or any non-whitespace text marks it as user content → not blank.

**Rationale**: Matches the spec's definition (empty string / whitespace / single empty paragraph = adoptable).
Pure string inspection, fully unit-testable. Conservative by design: when in doubt (any real content) it
treats the page as user-owned and **skips**, so the feature can never overwrite real work (FR-006/SC-003).

**Alternatives considered**:
- *`has_blocks()` / `parse_blocks()`*: WordPress-dependent, pulls the predicate out of the pure layer for no
  gain — the blank shapes are few and recognisable by string. Rejected to keep it headless.

## D3 — `isKitPlaceholder` signal

**Decision**: The boundary marks a page as a kit placeholder when it carries the `_corex_kit_page` meta AND its
content is blank (a page a prior kit run created but never populated). Such a page is adoptable even though it
"exists", because it is Corex's own un-finished page, not user content.

**Rationale**: Lets a re-apply finish a half-seeded page without treating it as user content, and keeps adopt
distinct from "user happened to make an empty page" (both are adoptable here, but the placeholder also stays
*created*-disposed for reset — see D4).

## D4 — Disposition recording + reset interaction

**Decision**: Extend `_corex_kit_page` meta from the literal `'1'` to the disposition string `created` or
`adopted`. `corex_kit_seeded_pages` stays the flat index of all kit-touched page ids. On apply:
- **create** → `wp_insert_post`, meta `created`, add id to the index.
- **adopt** of a *kit placeholder* (was Corex-created) → populate via `wp_update_post`, meta stays `created`
  (Corex still owns it → delete on reset).
- **adopt** of a *user's pre-existing empty page* → populate via `wp_update_post`, meta `adopted`, add id to
  the index (Corex must NOT delete it on reset).
- **skip** → no write; recorded in the outcome only.

`ResetExecutor`, per tracked id, reads the meta: `created` (or legacy `'1'`) → `wp_delete_post` + revert the
front page if it pointed there (today's behavior); `adopted` → `wp_update_post` content `''` + `delete_post_meta`
+ remove the id from the index, and **do not** delete the post or touch a front page that still points at it.

**Rationale**: This is the crux of FR-008 — reset must never delete a page the user owned. The disposition meta
is the single bit that distinguishes "Corex made this page" from "Corex filled in the user's page". Legacy `'1'`
pages (seeded before this feature) are treated as `created`, preserving current reset behavior with no migration.

**Alternatives considered**:
- *A parallel `corex_kit_adopted_pages` option*: two lists to keep consistent; the per-page meta is the natural
  home for a per-page fact and is already read at the page. Rejected.
- *Never adopt user pages (only placeholders)*: would leave the live blank-Home bug unfixed (2511 is a plain WP
  page with no kit meta), which is the entire point. Rejected.

## D5 — Front-page assignment moved out of the create loop

**Decision**: After processing all dispositions, if the declared **home** page (the entry with `front: true`)
was **created** or **adopted**, set `show_on_front=page` + `page_on_front=<that id>`. If the home was **skipped**
(user content), do not change the front-page setting (respect the user's existing home).

**Rationale**: The live bug is precisely that the front-page set sat inside the create-only loop, so an adopted
or pre-existing home never became the front page. Lifting it out and gating on created|adopted guarantees a real
Corex front page after apply (FR-005/SC-001) while never overriding a user's deliberate home (skip case).

## D6 — Apply returns an outcome (reused by the wizard + spec 042)

**Decision**: `BlueprintActivator::apply()` / `seedPages()` returns an `ApplyOutcome` value object — the list of
`PageDisposition`s enriched with the resulting page id and whether it became the front page, plus activated
modules and enabled flags. `SetupWizardScreen` renders it as an admin-notice summary (created/populated/skipped
+ front page + links). Spec 042 reuses the same outcome shape for its prompt preview and "what changed" summary.

**Rationale**: Satisfies FR-009 (visible result) with one representation, and sets up 042's single shared
preview/summary (042-FR-008) without rework. Returning a value object keeps the activator a service (no echo).
