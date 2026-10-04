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

// Spec 101 — the preview link's events share this log (T040, FR-012).

it('records an event with who and when, and no mode on either side', function () {
    $this->store->set('coming-soon', 3);
    $this->store->record(OperationsModeStore::EVENT_PREVIEW_CREATED, 5);

    $timeline = $this->store->timeline();

    expect($timeline)->toHaveCount(2)
        // Newest first, like history().
        ->and($timeline[0]['event'])->toBe('preview_link_created')
        ->and($timeline[0]['user'])->toBe(5)
        ->and($timeline[0]['time'])->toBeGreaterThan(0)
        ->and($timeline[0]['from'])->toBe('')
        ->and($timeline[0]['to'])->toBe('')
        // A mode change is the other kind of row, and says so by having no event.
        ->and($timeline[1]['event'])->toBe('')
        ->and($timeline[1]['to'])->toBe('coming-soon');
});

it('keeps history() answering mode changes only, for the callers that want only those', function () {
    $this->store->set('coming-soon', 3);
    $this->store->record(OperationsModeStore::EVENT_PREVIEW_CREATED, 3);
    $this->store->record(OperationsModeStore::EVENT_PREVIEW_REGENERATED, 3);
    $this->store->record(OperationsModeStore::EVENT_PREVIEW_REVOKED, 3);
    $this->store->set('development', 3);

    $history = $this->store->history();

    expect($history)->toHaveCount(2)
        ->and(array_column($history, 'to'))->toBe(['development', 'coming-soon'])
        ->and($this->store->timeline())->toHaveCount(5);
});

it('refuses to record an event it does not know', function () {
    // The log is read by the screen as a closed vocabulary. A free-text event would be a place
    // for a caller to write anything at all — the secret included.
    $this->store->record('preview_link_created https://example.test/?corex_preview=secret', 3);
    $this->store->record('', 3);

    expect($this->store->timeline())->toBe([]);
});

it('holds events and mode changes to the same cap', function () {
    for ($i = 0; $i < 25; $i++) {
        $this->store->record(OperationsModeStore::EVENT_PREVIEW_REGENERATED, 1);
    }

    expect(count($this->store->timeline(100)))->toBe(20);
});

it('stores nothing in an event row but the event, the user and the time', function () {
    $this->store->record(OperationsModeStore::EVENT_PREVIEW_CREATED, 5);

    $row = $GLOBALS['corex_test_options']['corex_operations_mode_log'][0];

    expect(array_keys($row))->toBe(['time', 'user', 'event']);
});
