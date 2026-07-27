<?php

/**
 * Re-applying the mode you are already in is not a mode change (spec 077, T002 / FR-009).
 *
 * `OperationsModeStore::set()` wrote the option and appended a history entry unconditionally, so
 * choosing Development while already in Development produced a `development → development` row and
 * a success notice. A log that records non-changes stops being a record of changes — and this is
 * the log an operator reads to answer "when did this site go live, and who did it".
 *
 * @package Corex\Tests\Integration\Operations
 */

declare(strict_types=1);

use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;

beforeEach(function () {
    delete_option('corex_operations_mode');
    delete_option('corex_operations_mode_log');

    $this->store = new OperationsModeStore(new OperationsMode());
});

afterEach(function () {
    delete_option('corex_operations_mode');
    delete_option('corex_operations_mode_log');
});

it('records a real change', function () {
    $this->store->set('staging', 1);

    $history = $this->store->history();

    expect($history)->toHaveCount(1)
        ->and($history[0]['to'])->toBe('staging')
        ->and($this->store->current())->toBe('staging');
});

it('does not record re-applying the mode already in force', function () {
    $this->store->set('staging', 1);
    $this->store->set('staging', 1);

    expect($this->store->history())->toHaveCount(1)
        ->and($this->store->current())->toBe('staging');
});

it('does not record re-applying an inherited mode either', function () {
    // Nothing declared yet: `current()` falls back to the WordPress environment type. Declaring
    // that same value IS a real change — it moves the site from inherited to declared — so this
    // one must still be logged.
    $inherited = $this->store->current();

    $this->store->set($inherited, 1);

    expect($this->store->isDeclared())->toBeTrue()
        ->and($this->store->history())->toHaveCount(1);

    // The second time, it is genuinely nothing.
    $this->store->set($inherited, 1);

    expect($this->store->history())->toHaveCount(1);
});

it('reports the mode in force whether or not it changed anything', function () {
    // The return value is the caller's answer to "what is the mode now", and that is the same
    // question whether or not this call moved it.
    expect($this->store->set('production', 1))->toBe('production')
        ->and($this->store->set('production', 1))->toBe('production');
});

it('keeps logging real changes after a no-op', function () {
    // The guard must not leave the log in a state where the next real change is missed.
    $this->store->set('staging', 1);
    $this->store->set('staging', 1);
    $this->store->set('production', 1);

    $history = $this->store->history();

    expect($history)->toHaveCount(2)
        // Newest first.
        ->and($history[0]['to'])->toBe('production')
        ->and($history[0]['from'])->toBe('staging');
});
