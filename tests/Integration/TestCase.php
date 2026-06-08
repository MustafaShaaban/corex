<?php

/**
 * Base TestCase for integration tests. WordPress is loaded by the integration
 * bootstrap (tests/bootstrap-integration.php); tests are skipped if the ./wp
 * install was not available so the suite is never falsely red.
 *
 * @package Corex\Tests\Integration
 */

declare(strict_types=1);

namespace Corex\Tests\Integration;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('did_action')) {
            self::markTestSkipped('WordPress (./wp) not loaded; integration test skipped.');
        }
    }
}
