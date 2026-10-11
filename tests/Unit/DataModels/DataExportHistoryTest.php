<?php

/**
 * What became of a Data export's file (spec 109, slice 2, D5; FR-012 to FR-014).
 *
 * A Data export's file was never removed by anything and could not be deleted by anybody; the
 * history said a scope, a format and a count. It now says whose it is, how large, what state its
 * file is in and when it expires, as the Submissions export's history does.
 *
 * @package Corex\Tests\Unit\DataModels
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Activity\ActivityService;
use Corex\Config\DataModels\DataExportHistory;
use Corex\Config\DataModels\DataExportRetention;
use Corex\Config\DataModels\DataExportRun;
use Corex\Tests\Support\InMemoryDataExportStore;
use Corex\Tests\Support\RecordingActivityRepository;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

/**
 * A finished export of person 7's, made on a given day, with a real file behind it.
 *
 * @return array{0:InMemoryDataExportStore,1:DataExportRun,2:string} The store, the run, the file's path.
 */
function finishedDataExport(string $madeAt = '2026-10-01T09:00:00+00:00', int $actorId = 7): array
{
    $store = new InMemoryDataExportStore();
    $run = $store->create(DataExportRun::from([
        'actor_id' => $actorId, 'source_key' => 'contacts', 'scope' => 'all', 'columns' => ['name'],
        'format' => 'csv', 'record_count' => 3, 'state' => 'queued', 'input_hash' => str_repeat('b', 64),
        'created_at' => $madeAt,
    ]));
    $path = tempnam(sys_get_temp_dir(), 'corex-history-test-');
    file_put_contents($path, "Name\r\nGrace\r\n");
    $store->saveFile($run->id, ['path' => $path, 'extension' => 'csv', 'content_type' => 'text/csv']);
    $store->finish($run->id, 3);

    return [$store, $store->find($run->id), $path];
}

/** @return array{0:DataExportHistory,1:RecordingActivityRepository,2:DataExportRetention} */
function dataExportHistory(InMemoryDataExportStore $store, string $now = '2026-10-11T09:00:00+00:00'): array
{
    $retention = new DataExportRetention($store, static fn (): DateTimeImmutable => new DateTimeImmutable($now));
    $activity = new RecordingActivityRepository();
    $names = static fn (int $userId): string => [7 => 'Salma Adel', 4 => 'Omar Said'][$userId] ?? '';

    return [new DataExportHistory($store, $retention, new ActivityService($activity), $names), $activity, $retention];
}

it('lists an export with who made it, how large it is, that it is ready and when it expires', function () {
    [$store, $run, $path] = finishedDataExport();
    [$history] = dataExportHistory($store);

    $entry = $history->entries(7, false)[0];

    expect($entry['id'])->toBe($run->id)
        ->and($entry['actor_name'])->toBe('Salma Adel')
        ->and($entry['file_bytes'])->toBe(filesize($path))
        ->and($entry['file_state'])->toBe('ready')
        ->and($entry['expires_at'])->toBe('2026-10-31T09:00:00+00:00');
});

it('shows a person their own exports, and everybody’s to a person who may manage them all', function () {
    [$store] = finishedDataExport(actorId: 7);
    [$history] = dataExportHistory($store);

    expect($history->entries(4, false))->toBe([])
        ->and($history->entries(4, true))->toHaveCount(1);
});

it('says an export is not finished while it has no file yet', function () {
    $store = new InMemoryDataExportStore();
    $store->create(DataExportRun::from([
        'actor_id' => 7, 'source_key' => 'contacts', 'scope' => 'all', 'columns' => ['name'], 'format' => 'csv',
        'record_count' => 3, 'state' => 'queued', 'input_hash' => str_repeat('c', 64), 'created_at' => '2026-10-11T08:59:00+00:00',
    ]));
    [$history] = dataExportHistory($store);

    expect($history->entries(7, false)[0]['file_state'])->toBe('pending');
});

it('deletes an export’s file for the person who made it, keeps the entry, and records it without any value', function () {
    [$store, $run, $path] = finishedDataExport();
    [$history, $activity] = dataExportHistory($store);

    $history->delete(7, false, $run->id);
    $entry = $history->entries(7, false)[0];

    expect(is_file($path))->toBeFalse()
        ->and($entry['file_state'])->toBe('deleted')
        ->and($entry['removed_by_name'])->toBe('Salma Adel')
        // How large it was is still said, once it is gone.
        ->and($entry['file_bytes'])->toBeGreaterThan(0)
        ->and($activity->events)->toHaveCount(1)
        ->and($activity->events[0]->kind)->toBe('data.export.deleted')
        ->and(json_encode($activity->events[0]->context))->not->toContain('Grace');
});

it('answers as if an export were not there to a person who neither made it nor may manage them all', function (string $action) {
    [$store, $run] = finishedDataExport(actorId: 7);
    [$history] = dataExportHistory($store);

    expect(fn () => $history->{$action}(4, false, $run->id))->toThrow(DomainException::class, 'unavailable')
        ->and($store->file($run->id))->not->toBeNull();
})->with(['delete', 'downloadable']);

it('lets a person who may manage them all delete another person’s export, and says who did', function () {
    [$store, $run] = finishedDataExport(actorId: 7);
    [$history] = dataExportHistory($store);

    $history->delete(4, true, $run->id);

    expect($history->entries(7, false)[0]['removed_by_name'])->toBe('Omar Said');
});

it('refuses to download or delete an export whose file is gone, and says which way it went', function () {
    [$store, $run] = finishedDataExport();
    [$history] = dataExportHistory($store);
    $history->delete(7, false, $run->id);

    expect(fn () => $history->downloadable(7, false, $run->id))->toThrow(DomainException::class, 'deleted')
        ->and(fn () => $history->delete(7, false, $run->id))->toThrow(DomainException::class, 'no file');
});

it('counts an export as expired thirty days after it was made, whether or not the sweep has been by', function () {
    [$store, $run] = finishedDataExport('2026-09-01T09:00:00+00:00');
    [$history] = dataExportHistory($store, '2026-10-02T09:00:00+00:00');

    expect($history->entries(7, false)[0]['file_state'])->toBe('expired')
        ->and(fn () => $history->downloadable(7, false, $run->id))->toThrow(DomainException::class, 'expired');
});

it('removes the files of exports past their thirty days and keeps their entries', function () {
    [$store, $old, $oldPath] = finishedDataExport('2026-09-01T09:00:00+00:00');
    [, , $retention] = dataExportHistory($store, '2026-10-11T09:00:00+00:00');

    $removed = $retention->pruneOlderThan(new DateTimeImmutable('2026-09-11T09:00:00+00:00'));

    expect($retention->retentionDays())->toBe(30)
        ->and($removed)->toBe(1)
        ->and(is_file($oldPath))->toBeFalse()
        ->and($store->find($old->id)->removedReason)->toBe(DataExportRun::REMOVED_EXPIRED)
        ->and($store->find($old->id)->removedBy)->toBe(0);
});

it('leaves an export made inside its thirty days alone', function () {
    [$store, $recent, $path] = finishedDataExport('2026-10-05T09:00:00+00:00');
    [, , $retention] = dataExportHistory($store);

    expect($retention->pruneOlderThan(new DateTimeImmutable('2026-09-11T09:00:00+00:00')))->toBe(0)
        ->and(is_file($path))->toBeTrue()
        ->and($store->find($recent->id)->removedReason)->toBe('');

    unlink($path);
});
