<?php

/**
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

/**
 * A `$wpdb` for unit tests that answers MySQL's named locks and nothing else: `GET_LOCK` grants or
 * refuses as told, and the test reads which lock was taken, whether it was let go, and what ran
 * while it was held.
 */
final class NamedLockWpdb
{
    public string $dbname = 'corex_test';

    public string $options = 'wp_options';

    /** Whether `GET_LOCK` is granted. */
    public bool $grants = true;

    /** The lock held now, or null. */
    public ?string $held = null;

    /** @var list<string> Every lock statement, in order. */
    public array $statements = [];

    public function prepare(string $query, mixed ...$args): string
    {
        return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), $args);
    }

    public function get_var(string $query): ?string
    {
        $this->statements[] = $query;

        if (preg_match("/GET_LOCK\('([^']+)'/", $query, $found) === 1) {
            if (! $this->grants) {
                return '0';
            }
            $this->held = $found[1];

            return '1';
        }

        return null;
    }

    public function query(string $query): int
    {
        $this->statements[] = $query;

        if (str_contains($query, 'RELEASE_LOCK')) {
            $this->held = null;
        }

        return 1;
    }
}
