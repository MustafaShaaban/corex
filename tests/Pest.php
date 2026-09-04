<?php

/**
 * Pest configuration.
 *
 * Binds each test directory to its base TestCase: Unit tests get Brain Monkey
 * (headless WP function stubbing); Integration tests get a booted WordPress.
 *
 * @package Corex\Tests
 */

declare(strict_types=1);

uses(\Corex\Tests\Unit\TestCase::class)->in('Unit');
uses(\Corex\Tests\Integration\TestCase::class)->in('Integration');

// A sibling of Integration, not a child of it, and that is load-bearing. Pest binds a directory to
// exactly one test case, and `->in('Integration')` claims everything beneath it — so while these
// lived in `tests/Integration/Multisite` the two bindings fought over the same folder and Pest
// refused whichever was written second, in either order:
//
//   ERROR  Test case `...\Multisite\TestCase` can not be used. The folder
//          `tests\Integration\Multisite\BootLoggerTest.php` already uses `...\Integration\TestCase`
//
// The multisite suite did not start at all. Moving it out also stops the integration run from
// collecting tests that need a network it does not have. Multisite\TestCase still extends
// Integration\TestCase, so these files keep the booted WordPress and add the network guard.
uses(\Corex\Tests\Multisite\TestCase::class)->in('Multisite');
