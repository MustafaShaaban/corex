<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Cache;

use Throwable;

defined('ABSPATH') || exit;

/**
 * The cache CoreX actually uses (spec 078, FR-007/008/009).
 *
 * Two backings, one behaviour: the object cache when WordPress is using a persistent one, transients
 * otherwise. **Both are WordPress mechanisms, and neither is a fallback** — the overwhelming
 * majority of CoreX installs are shared hosting with transients, so that is the normal path and it
 * has to be first-class.
 *
 * Namespaces are versioned rather than enumerated: `forgetNamespace()` moves a counter, so every key
 * under it misses at once. Enumerating keys is impossible on some object caches and slow on the
 * options table, and a version bump is atomic where a delete loop is not.
 *
 * Nothing here throws. A cache that raises turns a speed layer into an outage.
 */
final class WordPressCacheStore implements CacheStore
{
    /** All CoreX values share one group so a host inspecting the cache sees one owner. */
    private const GROUP = 'corex';

    /** Where a namespace's version counter lives. */
    private const VERSION_PREFIX = 'corex_ns_';

    /** How long a `remember()` lock is held before another process may proceed anyway. */
    private const LOCK_TTL = 30;

    public function get(string $namespace, string $key, mixed $default = null): mixed
    {
        try {
            $found = false;
            $value = $this->read($this->qualify($namespace, $key), $found);

            return $found ? $value : $default;
        } catch (Throwable) {
            return $default;
        }
    }

    public function put(string $namespace, string $key, mixed $value, int $ttl = 0): bool
    {
        try {
            return $this->write($this->qualify($namespace, $key), $value, $ttl);
        } catch (Throwable) {
            return false;
        }
    }

    public function has(string $namespace, string $key): bool
    {
        $missing = new \stdClass();

        return $this->get($namespace, $key, $missing) !== $missing;
    }

    public function forget(string $namespace, string $key): bool
    {
        try {
            $qualified = $this->qualify($namespace, $key);

            return $this->usesObjectCache()
                ? wp_cache_delete($qualified, self::GROUP)
                : delete_transient($qualified);
        } catch (Throwable) {
            return false;
        }
    }

    public function remember(string $namespace, string $key, int $ttl, callable $compute): mixed
    {
        $missing = new \stdClass();
        $hit     = $this->get($namespace, $key, $missing);

        if ($hit !== $missing) {
            return $hit;
        }

        // Best-effort: if another process holds the lock, wait briefly for its result rather than
        // computing the same thing twice. If it never arrives, compute anyway — a slow duplicate is
        // better than a request that returns nothing.
        $lock = $this->qualify($namespace, $key) . '__lock';

        if (! $this->acquire($lock)) {
            $waited = $this->get($namespace, $key, $missing);

            if ($waited !== $missing) {
                return $waited;
            }
        }

        try {
            $value = $compute();
            $this->put($namespace, $key, $value, $ttl);

            return $value;
        } finally {
            $this->release($lock);
        }
    }

    public function forgetNamespace(string $namespace): bool
    {
        try {
            $key     = self::VERSION_PREFIX . $this->slug($namespace);
            $current = (int) get_option($key, 1);

            return update_option($key, $current + 1, false);
        } catch (Throwable) {
            return false;
        }
    }

    public function isPersistent(): bool
    {
        // Transients persist too — in the options table — so the honest answer is yes on both
        // paths. What differs is speed and whether the value survives a cache restart, which the
        // status report describes separately rather than collapsing into this flag.
        return true;
    }

    public function describe(): string
    {
        return $this->usesObjectCache()
            ? __('Persistent object cache', 'corex')
            : __('WordPress transients', 'corex');
    }

    private function usesObjectCache(): bool
    {
        return function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache();
    }

    private function read(string $qualified, bool &$found): mixed
    {
        if ($this->usesObjectCache()) {
            $value = wp_cache_get($qualified, self::GROUP, false, $found);

            return $value;
        }

        $value = get_transient($qualified);
        $found = $value !== false;

        return $value;
    }

    private function write(string $qualified, mixed $value, int $ttl): bool
    {
        return $this->usesObjectCache()
            ? wp_cache_set($qualified, $value, self::GROUP, $ttl)
            : set_transient($qualified, $value, $ttl);
    }

    /**
     * The stored key: namespace, its current version, and the caller's key.
     *
     * Hashed at the end because a transient key is limited to 172 characters and a caller's key can
     * be anything. The prefix stays readable so a host looking at the options table can tell whose
     * rows these are.
     */
    private function qualify(string $namespace, string $key): string
    {
        $slug    = $this->slug($namespace);
        $version = (int) get_option(self::VERSION_PREFIX . $slug, 1);

        return 'corex_' . $slug . '_' . $version . '_' . md5($key);
    }

    private function slug(string $namespace): string
    {
        return (string) preg_replace('/[^a-z0-9_]/', '', strtolower($namespace));
    }

    private function acquire(string $lock): bool
    {
        if ($this->usesObjectCache()) {
            return (bool) wp_cache_add($lock, 1, self::GROUP, self::LOCK_TTL);
        }

        if (get_transient($lock) !== false) {
            return false;
        }

        return (bool) set_transient($lock, 1, self::LOCK_TTL);
    }

    private function release(string $lock): void
    {
        if ($this->usesObjectCache()) {
            wp_cache_delete($lock, self::GROUP);

            return;
        }

        delete_transient($lock);
    }
}
