<?php

/**
 * Integration tests: the operations mode is stored where WordPress loads it with the site's
 * options, and a site that declared its mode before that keeps it (DECISIONS #366).
 *
 * The maintenance and coming-soon guards read the mode on every front-end page, and it was saved
 * with autoload off: one query a page. The row of a site that never declared a mode says so with
 * an empty string, which the store reads as it reads no row at all.
 *
 * @package Corex\Tests\Integration\Operations
 */

declare(strict_types=1);

use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;
use Corex\Tests\Support\LoadedOptionsRun;
use Corex\Tests\Support\OptionRow;

beforeEach(function () {
    $this->modeRow = OptionRow::capture(OperationsModeStore::OPTION);
    $this->logRow = OptionRow::capture('corex_operations_mode_log');
    $this->store = new OperationsModeStore(new OperationsMode());
});

afterEach(function () {
    OptionRow::restore(OperationsModeStore::OPTION, $this->modeRow);
    OptionRow::restore('corex_operations_mode_log', $this->logRow);
});

it('keeps a declared mode while the row is unloaded, and once it is turned over', function () {
    OptionRow::storeUnloaded(OperationsModeStore::OPTION, 'staging');
    $unloaded = [$this->store->current(), $this->store->isDeclared()];

    LoadedOptionsRun::onACronRequest();

    expect(OptionRow::autoload(OperationsModeStore::OPTION))->toBe('on')
        ->and($unloaded)->toBe(['staging', true])
        ->and([$this->store->current(), $this->store->isDeclared()])->toBe($unloaded);
});

it('gives a site that never declared a mode a row that says so, and no entry in its history', function () {
    delete_option(OperationsModeStore::OPTION);
    $inherited = $this->store->current();
    $history = $this->store->timeline();

    LoadedOptionsRun::onACronRequest();

    expect(OptionRow::autoload(OperationsModeStore::OPTION))->toBe('on')
        ->and($this->store->isDeclared())->toBeFalse()
        ->and($this->store->current())->toBe($inherited)
        ->and($this->store->timeline())->toBe($history);
});

it('stores a declared mode loaded', function () {
    delete_option(OperationsModeStore::OPTION);

    $this->store->set('staging', 1);

    expect(OptionRow::autoload(OperationsModeStore::OPTION))->toBe('on')
        ->and($this->store->current())->toBe('staging');
});
