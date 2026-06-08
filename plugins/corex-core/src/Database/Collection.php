<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Database;

defined('ABSPATH') || exit;

use ArrayIterator;
use Corex\Models\Model;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * An immutable, empty-safe ordered set of Models returned by a query (spec FR-017).
 *
 * @implements IteratorAggregate<int, Model>
 */
final class Collection implements Countable, IteratorAggregate
{
    /**
     * @param list<Model> $models
     */
    public function __construct(private readonly array $models)
    {
    }

    /**
     * @return list<Model>
     */
    public function all(): array
    {
        return $this->models;
    }

    public function first(): ?Model
    {
        return $this->models[0] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->models === [];
    }

    public function count(): int
    {
        return count($this->models);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->models);
    }
}
