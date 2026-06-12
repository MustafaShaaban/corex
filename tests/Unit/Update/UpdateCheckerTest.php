<?php

/**
 * Unit tests for the pure update checker (spec 034 US1: FR-001). No WordPress, no network.
 *
 * @package Corex\Tests\Unit\Update
 */

declare(strict_types=1);

use Corex\Update\UpdateChecker;

it('offers an update when the manifest version is newer', function () {
    $update = (new UpdateChecker())->check('0.20.0', ['version' => '0.21.0', 'package' => 'https://x/corex-0.21.0.zip']);

    expect($update)->not->toBeNull()
        ->and($update['new_version'])->toBe('0.21.0')
        ->and($update['package'])->toBe('https://x/corex-0.21.0.zip');
});

it('offers no update when current is equal or newer', function () {
    $checker = new UpdateChecker();

    expect($checker->check('0.21.0', ['version' => '0.21.0']))->toBeNull()
        ->and($checker->check('0.22.0', ['version' => '0.21.0']))->toBeNull();
});

it('is a no-op for a missing or malformed manifest version', function () {
    $checker = new UpdateChecker();

    expect($checker->check('0.20.0', []))->toBeNull()
        ->and($checker->check('0.20.0', ['version' => '']))->toBeNull();
});
