<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Cache;

defined('ABSPATH') || exit;

/**
 * One declared cached value (spec 078, FR-001).
 *
 * Every field is required, and that is the design: a cached value that cannot say who owns it, what
 * it is, how long it lives and how it is invalidated is one nobody can reason about later — which
 * is how `corex_throttle_*` came to look like a cache.
 */
final class CacheEntry
{
    /**
     * @param string              $key            The exact key, or the prefix when the family is
     *                                            keyed per request/user/token.
     * @param bool                $isPrefix       Whether `$key` names a family rather than one key.
     * @param string              $owner          The class responsible for writing it.
     * @param CacheClassification $classification What it is, which decides what may remove it.
     * @param string              $lifetime       How long it is meant to live, in words.
     * @param string              $invalidation   What makes it stale, and how it comes back.
     * @param string|null         $group          Object-cache group, when it is not a transient.
     */
    public function __construct(
        public readonly string $key,
        public readonly bool $isPrefix,
        public readonly string $owner,
        public readonly CacheClassification $classification,
        public readonly string $lifetime,
        public readonly string $invalidation,
        public readonly ?string $group = null,
    ) {
    }

    /** May an ordinary clear remove this? */
    public function isRoutinelyClearable(): bool
    {
        return $this->classification->mayBeClearedRoutinely();
    }

    /** Is this stored in an object-cache group rather than as a transient? */
    public function usesObjectCache(): bool
    {
        return $this->group !== null;
    }
}
