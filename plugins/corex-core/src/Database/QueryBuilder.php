<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Database;

defined('ABSPATH') || exit;

use Corex\Models\Model;

/**
 * A fluent, chainable query that produces a safe, capped `WP_Query` args array and
 * (via the executor) a Collection of Models. It is a pure arg-builder — it never
 * instantiates `WP_Query` (that is the executor's single job), which keeps the
 * cap/binding logic unit-testable.
 *
 * Covers the complex cases a real app needs: declared-field meta queries with AND/OR
 * relation, taxonomy queries, date ranges, numeric/string ordering by meta, search,
 * pagination, and eager-loaded belongs-to relations (no N+1, in the executor). Every
 * variable value is passed as data into the relevant `*_query` clause — never
 * interpolated — so WP_Query prepares it.
 */
final class QueryBuilder
{
    /**
     * @var array<string, mixed>
     */
    private array $coreArgs = [];

    /**
     * @var list<array<string, mixed>>
     */
    private array $metaQuery = [];

    private string $metaRelation = 'AND';

    /**
     * @var list<array<string, mixed>>
     */
    private array $taxQuery = [];

    private string $taxRelation = 'AND';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $dateQuery = null;

    private ?int $limit = null;

    private ?int $page = null;

    private bool $countTotal = false;

    private ?string $orderByField = null;

    private string $order = 'ASC';

    private bool $orderNumeric = false;

    /**
     * @var list<string>
     */
    private array $relations = [];

    /**
     * @param class-string<Model> $modelClass
     */
    public function __construct(
        private readonly string $modelClass,
        private readonly QueryExecutor $executor,
        private readonly int $cap,
    ) {
    }

    public function where(string $field, mixed $value, string $compare = '='): self
    {
        $key = ($this->modelClass)::fields()[$field] ?? null;

        if ($key !== null) {
            // Declared custom field → meta_query; the value is bound as data.
            $this->metaQuery[] = ['key' => $key, 'value' => $value, 'compare' => $compare];
        } else {
            // Core field → a WP_Query argument keyed by its own name.
            $this->coreArgs[$field] = $value;
        }

        return $this;
    }

    /**
     * Add a declared-field meta condition under an OR relation with the others.
     */
    public function orWhere(string $field, mixed $value, string $compare = '='): self
    {
        $this->metaRelation = 'OR';

        return $this->where($field, $value, $compare);
    }

    /**
     * A meta condition on a raw meta key (not a declared model field), with an explicit
     * comparison and SQL type (CHAR/NUMERIC/DATE/…). For nested/advanced meta_query.
     */
    public function whereMeta(string $key, mixed $value, string $compare = '=', string $type = 'CHAR'): self
    {
        $this->metaQuery[] = ['key' => $key, 'value' => $value, 'compare' => $compare, 'type' => $type];

        return $this;
    }

    /**
     * A declared-field numeric range (inclusive), e.g. salary between 50k and 90k.
     */
    public function whereBetween(string $field, int|float $min, int|float $max): self
    {
        $key = ($this->modelClass)::fields()[$field] ?? $field;

        $this->metaQuery[] = [
            'key'     => $key,
            'value'   => [$min, $max],
            'compare' => 'BETWEEN',
            'type'    => 'NUMERIC',
        ];

        return $this;
    }

    public function metaRelation(string $relation): self
    {
        $this->metaRelation = strtoupper($relation) === 'OR' ? 'OR' : 'AND';

        return $this;
    }

    /**
     * A taxonomy condition. Terms may be a single term or a list; matched by `term_id`
     * (default) or another field (`slug`, `name`).
     *
     * @param int|string|list<int|string> $terms
     */
    public function whereTax(string $taxonomy, int|string|array $terms, string $field = 'term_id', string $operator = 'IN'): self
    {
        $this->taxQuery[] = [
            'taxonomy' => $taxonomy,
            'field'    => $field,
            'terms'    => $terms,
            'operator' => $operator,
        ];

        return $this;
    }

    public function taxRelation(string $relation): self
    {
        $this->taxRelation = strtoupper($relation) === 'OR' ? 'OR' : 'AND';

        return $this;
    }

    /**
     * Restrict by the post date. Either bound may be null; dates are any strtotime-able
     * string (e.g. '2026-01-01') or a WP date array.
     *
     * @param array<string,mixed>|string|null $after
     * @param array<string,mixed>|string|null $before
     */
    public function whereDate(array|string|null $after, array|string|null $before = null, bool $inclusive = true): self
    {
        $clause = ['inclusive' => $inclusive];

        if ($after !== null) {
            $clause['after'] = $after;
        }

        if ($before !== null) {
            $clause['before'] = $before;
        }

        $this->dateQuery = $clause;

        return $this;
    }

    /**
     * Free-text search across post title/content (`WP_Query` `s`).
     */
    public function search(string $term): self
    {
        $this->coreArgs['s'] = $term;

        return $this;
    }

    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        return $this->setOrder($field, $direction, false);
    }

    /**
     * Order by a declared meta field numerically (meta_value_num), e.g. price or salary,
     * so "100" sorts after "20" instead of lexically before it.
     */
    public function orderByNumeric(string $field, string $direction = 'ASC'): self
    {
        return $this->setOrder($field, $direction, true);
    }

    private function setOrder(string $field, string $direction, bool $numeric): self
    {
        $this->orderByField = $field;
        $this->order = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderNumeric = $numeric;

        return $this;
    }

    public function limit(int $max): self
    {
        $this->limit = $max;

        return $this;
    }

    /**
     * Page the results: sets the per-page size (capped) and the page, and enables the
     * found-rows count so total/pages are available. Opt-in — unpaged queries keep
     * `no_found_rows` true for speed.
     */
    public function paginate(int $perPage, int $page = 1): self
    {
        $this->limit = $perPage;
        $this->page = max(1, $page);
        $this->countTotal = true;

        return $this;
    }

    public function with(string $relation): self
    {
        $this->relations[] = $relation;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArgs(): array
    {
        $args = $this->coreArgs;
        $args['post_type'] = ($this->modelClass)::postType();
        $args['posts_per_page'] = min($this->limit ?? $this->cap, $this->cap);
        $args['no_found_rows'] = ! $this->countTotal;

        if ($this->page !== null) {
            $args['paged'] = $this->page;
        }

        $this->applyMetaQuery($args);
        $this->applyTaxQuery($args);

        if ($this->dateQuery !== null) {
            $args['date_query'] = [$this->dateQuery];
        }

        $this->applyOrder($args);

        return $args;
    }

    public function get(): Collection
    {
        return $this->executor->run($this->toArgs(), $this->modelClass, $this->relations);
    }

    public function first(): ?Model
    {
        return $this->limit(1)->get()->first();
    }

    /**
     * @param array<string, mixed> $args
     */
    private function applyMetaQuery(array &$args): void
    {
        if ($this->metaQuery === []) {
            return;
        }

        // Keep the single-clause AND case as a bare list (no relation noise); add the
        // relation only when it actually matters (>1 clause or an explicit OR).
        if (count($this->metaQuery) > 1 || $this->metaRelation === 'OR') {
            $args['meta_query'] = array_merge(['relation' => $this->metaRelation], $this->metaQuery);

            return;
        }

        $args['meta_query'] = $this->metaQuery;
    }

    /**
     * @param array<string, mixed> $args
     */
    private function applyTaxQuery(array &$args): void
    {
        if ($this->taxQuery === []) {
            return;
        }

        if (count($this->taxQuery) > 1 || $this->taxRelation === 'OR') {
            $args['tax_query'] = array_merge(['relation' => $this->taxRelation], $this->taxQuery);

            return;
        }

        $args['tax_query'] = $this->taxQuery;
    }

    /**
     * @param array<string, mixed> $args
     */
    private function applyOrder(array &$args): void
    {
        if ($this->orderByField === null) {
            return;
        }

        $metaKey = ($this->modelClass)::fields()[$this->orderByField] ?? null;

        if ($metaKey !== null) {
            $args['orderby'] = $this->orderNumeric ? 'meta_value_num' : 'meta_value';
            $args['meta_key'] = $metaKey;
        } else {
            $args['orderby'] = $this->orderByField;
        }

        $args['order'] = $this->order;
    }
}
