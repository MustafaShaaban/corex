<?php

/**
 * Base TestCase for integration tests. WordPress is loaded by the integration
 * bootstrap (tests/bootstrap-integration.php); tests are skipped if the ./wp
 * install was not available so the suite is never falsely red.
 *
 * It also fails a test that leaves a post on the install. The suite runs against a developer's
 * real install, and much of what a test writes there is written by a listener the test never
 * calls: a logged email, a stored submission. A test deletes what it names, and what it does not
 * know it writes it does not name, so rows were left by tests that looked careful (four rows
 * in one run on 2026-10-13, found by counting the posts table before and after). Nothing is
 * rolled back for this: a developer's tables may be MyISAM, where a rollback undoes nothing.
 *
 * @package Corex\Tests\Integration
 */

declare(strict_types=1);

namespace Corex\Tests\Integration;

use Corex\Tests\Support\CreatedPosts;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private ?CreatedPosts $postsInserted = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('did_action')) {
            self::markTestSkipped('WordPress (./wp) not loaded; integration test skipped.');
        }

        $this->postsInserted = CreatedPosts::watchAll();
    }

    /**
     * Runs after the file's own `afterEach`, so what is still stored here is what the test left.
     *
     * The rows are deleted before the test is failed: a red run costs the install nothing, and
     * the next run does not start from this one's leftovers.
     */
    protected function tearDown(): void
    {
        $left = $this->postsInserted?->stillStored() ?? [];
        $this->postsInserted?->delete();
        $this->postsInserted = null;

        parent::tearDown();

        if ($left !== []) {
            self::fail(
                "This test left posts on the install, which have now been deleted:\n  " . implode("\n  ", $left)
                . "\nDelete them in the test: CreatedPosts::watch('<post type>') in beforeEach, ->delete() in afterEach.",
            );
        }
    }
}
