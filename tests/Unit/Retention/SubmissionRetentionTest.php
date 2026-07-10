<?php

/**
 * Unit tests for the retention prune loop (spec 065): it trashes only the ids given and reports the
 * real count trashed. No WordPress query here — the WP_Query id lookup is a boundary.
 *
 * @package Corex\Tests\Unit\Retention
 */

declare(strict_types=1);

use Corex\Config\Retention\RetentionSettings;
use Corex\Config\Retention\SubmissionRetentionStore;
use Corex\Config\Retention\SubmissionRetention;

it('trashes each given id and returns how many were trashed', function () {
    $trashed = [];
    $reader  = Mockery::mock(SubmissionRetentionStore::class);
    $reader->shouldReceive('trashForRetention')->andReturnUsing(function (int $id) use (&$trashed): bool {
        $trashed[] = $id;

        return $id !== 2; // simulate id 2 failing to trash
    });

    $retention = new SubmissionRetention(new RetentionSettings(), $reader);

    expect($retention->applyIds('trash', [1, 2, 3]))->toBe(2)
        ->and($trashed)->toBe([1, 2, 3]);
});

it('trashes nothing for an empty id list', function () {
    $reader = Mockery::mock(SubmissionRetentionStore::class);
    $reader->shouldNotReceive('trashForRetention');

    $retention = new SubmissionRetention(new RetentionSettings(), $reader);

    expect($retention->applyIds('trash', []))->toBe(0);
});

it('applies archive and anonymize through the retention repository boundary', function () {
    $reader = Mockery::mock(SubmissionRetentionStore::class);
    $reader->shouldReceive('archiveForRetention')->once()->with(4)->andReturnTrue();
    $reader->shouldReceive('anonymizeForRetention')->once()->with(5)->andReturnTrue();
    $retention = new SubmissionRetention(new RetentionSettings(), $reader);

    expect($retention->applyIds('archive', [4]))->toBe(1)
        ->and($retention->applyIds('anonymize', [5]))->toBe(1);
});

it('rejects an unsupported retention action before touching records', function () {
    $reader = Mockery::mock(SubmissionRetentionStore::class);
    $reader->shouldNotReceive('trashForRetention');
    $retention = new SubmissionRetention(new RetentionSettings(), $reader);

    expect(fn () => $retention->applyIds('delete', [1]))
        ->toThrow(InvalidArgumentException::class, 'action');
});
