<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

/**
 * The registered data sources behind the Corex → Data screen, keyed by source key. The
 * framework registers the submissions source; add-ons register their custom-table sources
 * (spec 030).
 */
final class DataRegistry
{
    /** @var array<string,DataSource> */
    private array $sources = [];

    public function register(DataSource $source): void
    {
        $this->sources[$source->key()] = $source;
    }

    /**
     * @return list<DataSource>
     */
    public function all(): array
    {
        return array_values($this->sources);
    }

    public function find(string $key): ?DataSource
    {
        return $this->sources[$key] ?? null;
    }
}
