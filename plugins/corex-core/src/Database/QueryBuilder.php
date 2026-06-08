<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Database;

defined('ABSPATH') || exit;

use Corex\Models\Model;

/**
 * A fluent, chainable query that produces a safe, capped `WP_Query` args array
 * and (via the executor) a Collection of Models (spec FR-013–FR-017). It is a
 * pure arg-builder — it never instantiates `WP_Query` (that is the executor's
 * single job), which keeps the cap/binding logic unit-testable.
 */
final class QueryBuilder
{
    /**
     * @var array<string, mixed>
     */
    private array $coreArgs = [];

    /**
     * @var list<array{key: string, value: mixed, compare: string}>
     */
    private array $metaQuery = [];

    private ?int $limit = null;

    private ?string $orderByField = null;

    private string $order = 'ASC';

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
            // Declared custom field → meta_query; the value is bound as data (FR-016).
            $this->metaQuery[] = ['key' => $key, 'value' => $value, 'compare' => $compare];
        } else {
            // Core field → a WP_Query argument keyed by its own name.
            $this->coreArgs[$field] = $value;
        }

        return $this;
    }

    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        $this->orderByField = $field;
        $this->order = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        return $this;
    }

    public function limit(int $max): self
    {
        $this->limit = $max;

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
        $args['no_found_rows'] = true;

        if ($this->metaQuery !== []) {
            $args['meta_query'] = $this->metaQuery;
        }

        if ($this->orderByField !== null) {
            $metaKey = ($this->modelClass)::fields()[$this->orderByField] ?? null;

            if ($metaKey !== null) {
                $args['orderby'] = 'meta_value';
                $args['meta_key'] = $metaKey;
            } else {
                $args['orderby'] = $this->orderByField;
            }

            $args['order'] = $this->order;
        }

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
}
