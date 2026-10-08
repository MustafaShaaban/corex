<?php

/**
 * Unit tests for the retention prune loop (spec 065; spec 105, US4): it acts only on the ids given
 * and reports the real count. No WordPress query here: the WP_Query id lookup is a boundary.
 *
 * @package Corex\Tests\Unit\Retention
 */

declare(strict_types=1);

use Corex\Config\Retention\RetentionSettings;
use Corex\Config\Retention\SubmissionRetention;
use Corex\Config\Retention\SubmissionRetentionStore;
use Corex\Config\Retention\SubmissionRetentionTrash;
use Corex\Config\Submissions\SubmissionAccessScope;

/**
 * A trash that remembers what it was asked to take, and takes all of it but the ids it is told to refuse.
 */
function retentionTrash(int ...$refused): SubmissionRetentionTrash
{
    return new class($refused) implements SubmissionRetentionTrash {
        /** @var list<array{actor:int,ids:list<int>}> */
        public array $runs = [];

        /** @param list<int> $refused */
        public function __construct(private readonly array $refused)
        {
        }

        public function trashForRetention(SubmissionAccessScope $scope, array $ids): int
        {
            $this->runs[] = ['actor' => $scope->actorId, 'ids' => $ids];

            return count(array_diff($ids, $this->refused));
        }
    };
}

/**
 * The trash is the trash service's, and it is asked once for the whole run: one entry in the
 * activity stream is for a run, and a call for each submission would write one each.
 */
it('hands the whole run to the trash once, as the person running it, and reports what it took', function () {
    $trash = retentionTrash(2);
    $reader = Mockery::mock(SubmissionRetentionStore::class);
    $retention = new SubmissionRetention(new RetentionSettings(), $reader, $trash);

    expect($retention->applyIds(new SubmissionAccessScope(7, true), 'trash', [1, 2, 3]))->toBe(2)
        ->and($trash->runs)->toBe([['actor' => 7, 'ids' => [1, 2, 3]]]);
});

it('asks the trash for nothing when nothing is due', function () {
    $trash = retentionTrash();
    $retention = new SubmissionRetention(new RetentionSettings(), Mockery::mock(SubmissionRetentionStore::class), $trash);

    expect($retention->applyIds(new SubmissionAccessScope(7, true), 'trash', []))->toBe(0)
        ->and($trash->runs)->toBe([]);
});

it('applies archive and anonymize through the retention repository boundary', function () {
    $trash = retentionTrash();
    $reader = Mockery::mock(SubmissionRetentionStore::class);
    $reader->shouldReceive('archiveForRetention')->once()->with(4)->andReturnTrue();
    $reader->shouldReceive('anonymizeForRetention')->once()->with(5)->andReturnTrue();
    $retention = new SubmissionRetention(new RetentionSettings(), $reader, $trash);
    $scope = new SubmissionAccessScope(7, true);

    expect($retention->applyIds($scope, 'archive', [4]))->toBe(1)
        ->and($retention->applyIds($scope, 'anonymize', [5]))->toBe(1)
        ->and($trash->runs)->toBe([]);
});

it('rejects an unsupported retention action before touching records', function () {
    $trash = retentionTrash();
    $retention = new SubmissionRetention(new RetentionSettings(), Mockery::mock(SubmissionRetentionStore::class), $trash);

    expect(fn () => $retention->applyIds(new SubmissionAccessScope(7, true), 'delete', [1]))
        ->toThrow(InvalidArgumentException::class, 'action')
        ->and($trash->runs)->toBe([]);
});
