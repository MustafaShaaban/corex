<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Support\Config;

defined('ABSPATH') || exit;

/**
 * Resolves a key against an ordered list of sources, highest precedence first
 * (`.env` → WP options → defaults). The first source that holds the key wins
 * (spec FR-011); a missing key returns the caller default (FR-012).
 */
final class Repository implements ConfigInterface
{
    /**
     * @param list<Source> $sources Highest precedence first.
     */
    public function __construct(private readonly array $sources)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $source = $this->resolveSource($key);

        // Not `?? $default`: a source may legitimately hold null.
        return $source !== null ? $source->get($key) : $default;
    }

    public function has(string $key): bool
    {
        return $this->resolveSource($key) !== null;
    }

    private function resolveSource(string $key): ?Source
    {
        foreach ($this->sources as $source) {
            if ($source->has($key)) {
                return $source;
            }
        }

        return null;
    }
}
