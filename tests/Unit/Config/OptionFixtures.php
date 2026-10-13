<?php

/**
 * A site's options, for unit tests of whatever reads a setting's override. Required directly
 * and ignored by Pest as a non-test file.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;

/**
 * Stand in for a site whose options table holds these options and no other, each stored as
 * `update_option()` stores one: loaded with the site's options.
 *
 * @param array<string, mixed> $stored
 */
function siteWithOptions(array $stored): void
{
    Functions\when('wp_installing')->justReturn(false);
    Functions\when('wp_load_alloptions')->justReturn($stored);
    Functions\when('get_option')->alias(
        static fn (string $name, mixed $default = false): mixed => array_key_exists($name, $stored)
            ? $stored[$name]
            : $default,
    );
}
