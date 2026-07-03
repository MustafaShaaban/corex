<?php

/**
 * Unit tests for the retention prune loop (spec 065): it trashes only the ids given and reports the
 * real count trashed. No WordPress query here — the WP_Query id lookup is a boundary.
 *
 * @package Corex\Tests\Unit\Retention
 */

declare(strict_types=1);

use Corex\Config\Data\SubmissionsReader;
use Corex\Config\Retention\RetentionSettings;
use Corex\Config\Retention\SubmissionRetention;

it('trashes each given id and returns how many were trashed', function () {
    $trashed = [];
    $reader  = Mockery::mock(SubmissionsReader::class);
    $reader->shouldReceive('trash')->andReturnUsing(function (int $id) use (&$trashed): bool {
        $trashed[] = $id;

        return $id !== 2; // simulate id 2 failing to trash
    });

    $retention = new SubmissionRetention(new RetentionSettings(), $reader);

    expect($retention->pruneIds([1, 2, 3]))->toBe(2)
        ->and($trashed)->toBe([1, 2, 3]);
});

it('trashes nothing for an empty id list', function () {
    $reader = Mockery::mock(SubmissionsReader::class);
    $reader->shouldNotReceive('trash');

    $retention = new SubmissionRetention(new RetentionSettings(), $reader);

    expect($retention->pruneIds([]))->toBe(0);
});
