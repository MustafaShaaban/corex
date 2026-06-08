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
