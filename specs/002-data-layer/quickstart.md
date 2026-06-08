# Quickstart & Validation: Data Layer

Runnable scenarios proving the data layer. Types/contracts live in
[contracts/data-layer-contracts.md](./contracts/data-layer-contracts.md) and
[data-model.md](./data-model.md).

## Prerequisites

- corex-core foundation active (spec 001); WordPress at `./wp`.
- `composer install`; for `wp db …` prepend the MySQL client to PATH (PROGRESS quick reference).

## Run the tests

```bash
composer test                 # headless unit (Pest + Brain Monkey): Model, FieldDrivers, QueryBuilder args, Collection
composer test:integration     # against ./wp: real CRUD, real query + relation query-count
```

## Define a Model + Repository (illustrative)

```php
final class Job extends Corex\Models\Model {
    public static function postType(): string { return 'job'; }
    public static function fields(): array   { return ['salary' => 'job_salary']; }
    public static function casts(): array    { return ['salary' => 'int']; }
    public static function relations(): array {
        return ['company' => ['type' => 'belongsTo', 'model' => Company::class, 'foreignKey' => 'company_id']];
    }
}
final class JobRepository extends Corex\Repositories\PostRepository { /* bound to Job + 'job' */ }
```

## Scenario 1 — Read an entity as a typed Model (US1, SC-001)

```php
$jobs = Corex\Boot::app()->container()->make(JobRepository::class);
$job  = $jobs->find($id);          // ?Job ; null if absent (FR-005)
$job->get('salary');               // int (cast) ; salary read via the field driver
```
**Expected**: a typed `Job`; `find()` on a missing id returns `null`, never a fatal.

## Scenario 2 — Field access works with and without ACF (US2, SC-002, SC-003)

```php
// Identical code in both environments:
$job->get('salary');               // ACF active → get_field('job_salary', $id)
                                   // ACF absent → get_post_meta($id, 'job_salary', true)
$jobs->update($id, ['salary' => 90000]);   // writes via the active driver
```
**Expected**: same value resolved both ways; with ACF/Woo/Polylang uninstalled, the whole unit suite
passes (`composer test`).

## Scenario 3 — Fluent query with discipline (US3, SC-005, SC-006)

```php
$open = $jobs->query()
    ->where('salary', 80000, '>=')
    ->orderBy('post_date', 'DESC')
    ->limit(20)
    ->get();                        // Collection<Job>

$jobs->query()->toArgs();           // assert: posts_per_page <= 500, never -1; value in meta_query
$jobs->query()->where('salary', "80000' OR 1=1")->toArgs();  // value bound literally, not query syntax
```
**Expected**: filtered/ordered collection; an unbounded request caps at 500; an injection-style value
matches literally (zero unintended rows).

## Scenario 4 — Eager-load a relation, no N+1 (US4, SC-004)

```php
$list = $jobs->query()->with('company')->limit(50)->get();
foreach ($list->all() as $job) { $job->get('company'); }   // populated, no per-iteration query
```
**Expected**: every `Job` has its `company` populated; the data-source query count is the same for
50 jobs as for 2 (belongs-to = 2 queries). A job with no `company_id` reports an empty relation.

## Acceptance → scenario map

| Success criterion | Scenario |
|---|---|
| SC-001 typed Model in ≤5 lines | 1 |
| SC-002 field access ACF/none | 2 |
| SC-003 suite passes, no optional plugins | 2 (`composer test`) |
| SC-004 eager load bounded query count | 4 |
| SC-005 unbounded capped | 3 |
| SC-006 filter value bound literally | 3 |
| SC-007 headless coverage | `composer test` |
