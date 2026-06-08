<?php

/**
 * Base TestCase for integration tests that need a real WordPress runtime.
 *
 * Boots the local install at ./wp once per process so the framework can be
 * exercised in a genuine WordPress context (boot, contexts, admin notices).
 * Skips gracefully when ./wp is absent so the unit suite is never blocked.
 *
 * @package Corex\Tests\Integration
 */

declare(strict_types=1);

namespace Corex\Tests\Integration;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private static bool $wordPressLoaded = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (self::$wordPressLoaded) {
            return;
        }

        $wpLoad = dirname(__DIR__, 2) . '/wp/wp-load.php';

        if (! is_file($wpLoad)) {
            self::markTestSkipped('WordPress install at ./wp not found; integration tests skipped.');
        }

        require_once $wpLoad;
        self::$wordPressLoaded = true;
    }
}
