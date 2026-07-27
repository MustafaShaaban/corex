<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Cache;

defined('ABSPATH') || exit;

/**
 * A cache that lives for one request, for tests and for anything that must not depend on WordPress.
 *
 * Deterministic on purpose: expiry is evaluated against an injectable clock, so a test for "this
 * expires after a minute" is a fact rather than a `sleep(61)`.
 */
final class ArrayCacheStore implements CacheStore
{
    /** @var array<string, array{value: mixed, expires: int|null}> */
    private array $values = [];

    /** @var array<string, int> */
    private array $versions = [];

    /** @var array<string, true> */
    private array $locks = [];

    /** @param callable():int $clock Seconds since the epoch. Injectable so expiry is testable. */
    public function __construct(private $clock = null)
    {
        $this->clock ??= static fn (): int => time();
    }

    public function get(string $namespace, string $key, mixed $default = null): mixed
    {
        $qualified = $this->qualify($namespace, $key);
        $entry     = $this->values[$qualified] ?? null;

        if ($entry === null) {
            return $default;
        }

        if ($entry['expires'] !== null && $entry['expires'] <= ($this->clock)()) {
            unset($this->values[$qualified]);

            return $default;
        }

        return $entry['value'];
    }

    public function put(string $namespace, string $key, mixed $value, int $ttl = 0): bool
    {
        $this->values[$this->qualify($namespace, $key)] = [
            'value'   => $value,
            'expires' => $ttl > 0 ? ($this->clock)() + $ttl : null,
        ];

        return true;
    }

    public function has(string $namespace, string $key): bool
    {
        $missing = new \stdClass();

        return $this->get($namespace, $key, $missing) !== $missing;
    }

    public function forget(string $namespace, string $key): bool
    {
        unset($this->values[$this->qualify($namespace, $key)]);

        return true;
    }

    public function remember(string $namespace, string $key, int $ttl, callable $compute): mixed
    {
        $missing = new \stdClass();
        $hit     = $this->get($namespace, $key, $missing);

        if ($hit !== $missing) {
            return $hit;
        }

        $lock = $this->qualify($namespace, $key) . '__lock';

        // Held rather than merely noted: a test asserting "computed once" needs the second caller
        // to see the lock, and an in-memory store is where that is easiest to get wrong.
        if (isset($this->locks[$lock])) {
            return $this->get($namespace, $key, $compute());
        }

        $this->locks[$lock] = true;

        try {
            $value = $compute();
            $this->put($namespace, $key, $value, $ttl);

            return $value;
        } finally {
            unset($this->locks[$lock]);
        }
    }

    public function forgetNamespace(string $namespace): bool
    {
        $this->versions[$namespace] = ($this->versions[$namespace] ?? 1) + 1;

        return true;
    }

    public function isPersistent(): bool
    {
        return false;
    }

    public function describe(): string
    {
        return 'in-memory (request only)';
    }

    private function qualify(string $namespace, string $key): string
    {
        return $namespace . ':' . ($this->versions[$namespace] ?? 1) . ':' . $key;
    }
}
