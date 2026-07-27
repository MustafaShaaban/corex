<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Cache;

defined('ABSPATH') || exit;

/**
 * How CoreX reads and writes cached values (spec 078, FR-006).
 *
 * The contract is deliberately small. Every method has one behaviour on a site with a persistent
 * object cache and the same behaviour on shared hosting with none — the difference is speed, never
 * correctness, which is what lets Principle IX hold here.
 *
 * **A failing cache is a miss.** No method throws. A cache that raises an exception turns a
 * performance layer into a source of outages, and the caller almost never has anything useful to do
 * about it besides carry on and recompute.
 */
interface CacheStore
{
    /**
     * @param mixed $default Returned on a miss, an expired value, or any failure.
     */
    public function get(string $namespace, string $key, mixed $default = null): mixed;

    /**
     * @param int $ttl Seconds. Zero means "as long as the backing store will keep it".
     * @return bool Whether the value was stored. False is informational, never fatal.
     */
    public function put(string $namespace, string $key, mixed $value, int $ttl = 0): bool;

    public function has(string $namespace, string $key): bool;

    public function forget(string $namespace, string $key): bool;

    /**
     * Read through, computing and storing on a miss.
     *
     * Takes a best-effort lock so two simultaneous misses do not both run `$compute`. Best-effort
     * is stated rather than implied: without a persistent object cache the lock is weaker, and a
     * caller whose computation must run exactly once needs a real lock, not this.
     *
     * @template T
     * @param callable():T $compute
     * @return T
     */
    public function remember(string $namespace, string $key, int $ttl, callable $compute): mixed;

    /**
     * Invalidate everything in a namespace at once, without enumerating its keys.
     *
     * Implemented by moving the namespace's version rather than by deleting: enumeration is not
     * possible on every backing store, and a version bump is atomic where a loop is not.
     */
    public function forgetNamespace(string $namespace): bool;

    /**
     * Whether values survive the request.
     *
     * Reported rather than assumed, because it changes what a cache is worth: on shared hosting
     * transients survive and the in-memory object cache does not, and the status screen has to be
     * able to say which the site actually has.
     */
    public function isPersistent(): bool;

    /** A short, secret-free description of what is backing this store, for diagnostics. */
    public function describe(): string;
}
