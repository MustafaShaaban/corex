<?php

/**
 * The two WordPress functions a notification producer needs to name a destination (spec 087).
 *
 * Every producer that carries a call-to-action builds its URL with `add_query_arg(…,
 * admin_url(…))`, so the headless unit suite has to answer both. They live here rather than in each
 * test file because seven files stubbing the same two functions differently is how a test ends up
 * asserting against a URL shape the product never produces — the exact failure spec 087 was written
 * to fix, one level down.
 *
 * Deliberately not a global `beforeEach`: a stub every test gets whether it asked or not hides which
 * tests depend on it, and a producer that stopped calling `admin_url` would keep passing.
 *
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

use Brain\Monkey\Functions;

final class AdminUrlStubs
{
    public const BASE = 'https://example.test/wp-admin/';

    /**
     * `add_query_arg` is stubbed to the real function's argument order and encoding, so a URL built
     * here is the URL a browser would be given.
     */
    public static function install(): void
    {
        Functions\when('admin_url')->alias(
            static fn (string $path = ''): string => self::BASE . ltrim($path, '/'),
        );

        Functions\when('add_query_arg')->alias(
            static function (array $args, string $url): string {
                $separator = str_contains($url, '?') ? '&' : '?';

                return $url . $separator . http_build_query($args);
            },
        );
    }
}
