<?php

/**
 * Unit tests for the Operations Mode store (spec 065). Persistence + audit log over an in-memory
 * option backing (no real WordPress).
 *
 * @package Corex\Tests\Unit\Operations
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;

beforeEach(function () {
    Functions\when('__')->returnArg();

    $GLOBALS['corex_test_options'] = [];
    Functions\when('get_option')->alias(static fn (string $key, $default = false) => $GLOBALS['corex_test_options'][$key] ?? $default);
    Functions\when('update_option')->alias(static function (string $key, $value): bool {
        $GLOBALS['corex_test_options'][$key] = $value;

        return true;
    });
    Functions\when('wp_get_environment_type')->justReturn('staging');

    $this->store = new OperationsModeStore(new OperationsMode());
});

it('falls back to the WordPress environment type when no mode is declared', function () {
    expect($this->store->current())->toBe('staging')
        ->and($this->store->isDeclared())->toBeFalse();
});

it('persists a declared mode and reports it as declared', function () {
    $applied = $this->store->set('development', 7);

    expect($applied)->toBe('development')
        ->and($this->store->current())->toBe('development')
        ->and($this->store->isDeclared())->toBeTrue();
});

it('normalises an invalid mode to production on write', function () {
    expect($this->store->set('banana', 1))->toBe('production')
        ->and($this->store->current())->toBe('production');
});

it('records an audit entry per change, newest first', function () {
    $this->store->set('development', 3);
    $this->store->set('production', 3);

    $history = $this->store->history();

    expect($history)->toHaveCount(2)
        ->and($history[0]['to'])->toBe('production')
        ->and($history[0]['from'])->toBe('development')
        ->and($history[0]['user'])->toBe(3)
        ->and($history[1]['to'])->toBe('development');
});

it('caps the audit log at 20 entries', function () {
    for ($i = 0; $i < 25; $i++) {
        $this->store->set($i % 2 === 0 ? 'development' : 'staging', 1);
    }

    expect(count($this->store->history(100)))->toBe(20);
});
