<?php

/**
 * Base TestCase for headless unit tests.
 *
 * Sets up Brain Monkey so WordPress functions (add_action, get_option, …) can be
 * stubbed without loading WordPress — keeping the unit suite fast and runnable with
 * no optional plugins present (spec FR-022, SC-007).
 *
 * @package Corex\Tests\Unit
 */

declare(strict_types=1);

namespace Corex\Tests\Unit;

use Brain\Monkey;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }
}
