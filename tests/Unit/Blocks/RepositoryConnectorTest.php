<?php

/**
 * Unit tests for the Repository-backed connector + registry (spec US4: FR-011–FR-013, FR-019).
 *
 * @package Corex\Tests\Unit\Blocks
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Blocks\Connectors\ConnectorRegistry;
use Corex\Tests\Fixtures\Blocks\FakeEntity;
use Corex\Tests\Fixtures\Blocks\FakeRepository;
use Corex\Tests\Fixtures\Blocks\TestConnector;

require_once __DIR__ . '/BlockFixtures.php';

it('returns the escaped repository field value for a bound field', function () {
    Functions\when('esc_html')->returnArg();
    $connector = new TestConnector(new FakeRepository(new FakeEntity(['id' => 5, 'title' => 'Hello'])));

    expect($connector->value('title', ['id' => 5]))->toBe('Hello');
});

it('returns a safe empty fallback for a missing record or field', function () {
    Functions\when('esc_html')->returnArg();
    $missingRecord = new TestConnector(new FakeRepository(null));
    $missingField = new TestConnector(new FakeRepository(new FakeEntity(['id' => 5])));

    expect($missingRecord->value('title', ['id' => 5]))->toBe('')
        ->and($missingField->value('title', ['id' => 5]))->toBe('');
});

it('registers each connector as a block-bindings source', function () {
    Functions\expect('register_block_bindings_source')->once()->with('corex/test', \Mockery::type('array'));

    (new ConnectorRegistry())->register(new TestConnector(new FakeRepository(null)));
});
