<?php

/**
 * Shared PHPUnit/Pest bootstrap for the Corex test suite.
 *
 * Loads the monorepo's single authoritative Composer autoloader. Per-suite setup
 * (Brain Monkey for Unit, WordPress for Integration) lives in the base TestCase
 * classes wired through tests/Pest.php — PHPUnit allows only one global bootstrap.
 *
 * @package Corex\Tests
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Corex src class files carry a `defined('ABSPATH') || exit;` direct-access guard
// (WooCommerce convention). Define it here so PSR-4 classes load in the headless
// suite without a WordPress runtime. See DECISIONS #20.
if (! defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

// Minimal WP_Post stub so boundary code that type-checks `instanceof \WP_Post` (e.g. the kit page
// adopt path, spec 041) stays WP-idiomatic yet runnable headlessly. Only the fields the suite reads.
if (! class_exists('WP_Post')) {
    class WP_Post
    {
        public int $ID = 0;

        /** @param array<string,mixed> $fields */
        public function __construct(array $fields = [])
        {
            foreach ($fields as $key => $value) {
                $this->{$key} = $value;
            }
        }
    }
}
