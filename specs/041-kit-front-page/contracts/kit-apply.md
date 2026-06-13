# Contract: Kit Apply Classification, Population, and Reset Disposition

Internal framework interfaces. No external/REST surface. The pure, domain-neutral pieces live in **corex-core**
under `Corex\Provisioning\` (so spec 042's corex-config consumers reuse them without a core→addon dependency);
`BlueprintActivator` stays in **corex-kit-company** (`Corex\Kit\`).

## `Corex\Provisioning\PagePlanner` (pure, corex-core)

```php
final class PagePlanner
{
    /**
     * Classify each declared page into create / adopt / skip.
     *
     * @param list<array{title:string,slug:string,content:string,front?:bool}> $declared
     * @param array<string,array{exists:bool,isEmpty:bool,isKitPlaceholder:bool}> $signals  keyed by slug
     * @return list<PageDisposition>
     */
    public function plan(array $declared, array $signals): array;
}
```

**Guarantees**: pure (no WordPress); one disposition per declared page; rule per data-model.md; deterministic.

> Replaces the removed `Corex\Kit\KitPagePlanner::toCreate(array $declared, array $existingSlugs)`; callers
> (`BlueprintActivator`) are updated to the core `PagePlanner::plan()`.

## `Corex\Provisioning\PageContent` (pure, corex-core)

```php
final class PageContent
{
    /** True when content is empty, whitespace-only, or a single empty paragraph (adoptable). */
    public function isBlank(string $content): bool;
}
```

## `Corex\Provisioning\PageDisposition` / `Corex\Provisioning\ApplyOutcome` (value objects, corex-core)

```php
final class PageDisposition
{
    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly string $action,   // 'create' | 'adopt' | 'skip'
        public readonly string $reason,   // 'slug_absent' | 'existing_empty' | 'kit_placeholder' | 'user_content'
    ) {}
}

final class ApplyOutcome
{
    /** @param list<array{disposition:PageDisposition,pageId:?int,isFront:bool,persistedAs:?string}> $pages
     *  @param list<string> $modules  @param list<string> $flags */
    public function __construct(array $pages, array $modules, array $flags, public readonly ?int $frontPageId) {}
    /** @return list<...> */ public function pages(): array;
    public function created(): array;   public function populated(): array;   public function skipped(): array;
}
```

## `Corex\Kit\BlueprintActivator` (WP boundary) — changed behavior

```php
public function apply(array $plan): ApplyOutcome;        // enable flags + activate modules + seedPages, returns outcome
public function seedPages(array $pages): ApplyOutcome;   // classify → create/populate/skip → set front page → record
```

**Guarantees**
- Builds per-slug signals from WordPress (`get_page_by_path`, content read, `_corex_kit_page` meta + `isBlank`)
  and delegates the decision to `KitPagePlanner::plan()`.
- **create** → `wp_insert_post`, meta `_corex_kit_page = created`, id added to `corex_kit_seeded_pages`.
- **adopt** → `wp_update_post(['ID'=>$id,'post_content'=>$content])`; meta `adopted` for a user page,
  meta stays `created` for a kit placeholder; id present in the index.
- **skip** → no write.
- Front page set **after** the loop iff the declared home was created or adopted (FR-005).
- Never overwrites content present at apply time (a non-blank page is always `skip`).
- Returns an `ApplyOutcome`; performs no output/echo.

## `Corex\Cli\Reset\ResetExecutor` (WP boundary) — changed behavior

For each id in `corex_kit_seeded_pages`, read `_corex_kit_page`:
- `created` (or legacy `'1'`) → `wp_delete_post($id, true)`; revert front page if it pointed at `$id` (today).
- `adopted` → `wp_update_post(['ID'=>$id,'post_content'=>''])` + `delete_post_meta($id,'_corex_kit_page')` +
  remove `$id` from the index; do **not** delete the post; leave a front page that still points at it.

## Test contract (Pest)

- `KitPagePlannerTest` (headless): slug_absent→create; existing_empty→adopt; kit_placeholder→adopt;
  user_content→skip; mixed set classified independently.
- `KitPageContentTest` (headless): ''/' '/empty-paragraph → blank; real paragraph/any wp block → not blank.
- `BlueprintActivatorApplyTest` (Brain Monkey): adopts + populates an empty home and sets the front page;
  skips a user-content home and leaves the front page; created/adopted meta written; idempotent re-apply (no
  overwrite, no duplicate); returns a correct `ApplyOutcome`.
- `ResetDispositionTest` (Brain Monkey): created page deleted + front reverted; adopted page emptied + meta
  removed + untracked + post retained.
