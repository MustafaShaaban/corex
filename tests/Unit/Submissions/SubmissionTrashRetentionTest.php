<?php

/**
 * How long the trash keeps a submission, and its emptying (spec 105, US3; FR-017 and FR-018).
 *
 * @package Corex\Tests\Unit\Submissions
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Activity\ActivityService;
use Corex\Config\Retention\RetentionSettings;
use Corex\Config\Submissions\SubmissionTimelineStore;
use Corex\Config\Submissions\SubmissionTrashRetention;
use Corex\Config\Submissions\SubmissionTrashService;
use Corex\Config\Submissions\SubmissionWorkflowStore;
use Corex\Tests\Support\InMemorySubmissionTrash;
use Corex\Tests\Support\RecordingActivityRepository;
use Corex\Tests\Support\RecordingSubmissionEmailRecords;

/**
 * The trash's clock over a trash that is a list.
 *
 * @return object{trash:InMemorySubmissionTrash,activity:RecordingActivityRepository,retention:SubmissionTrashRetention}
 */
function trashClock(): object
{
    $inbox = new class () {
        /** @var array<int,array<string,mixed>> */
        public array $records = [];
    };
    $trash = new InMemorySubmissionTrash($inbox);
    $activity = new RecordingActivityRepository();
    $service = new SubmissionTrashService(
        Mockery::mock(SubmissionWorkflowStore::class),
        $trash,
        Mockery::mock(SubmissionTimelineStore::class),
        new ActivityService($activity),
        new RecordingSubmissionEmailRecords(),
    );

    return (object) [
        'trash' => $trash,
        'activity' => $activity,
        'retention' => new SubmissionTrashRetention($trash, $service, new RetentionSettings()),
    ];
}

it('keeps a trashed submission 30 days unless the site says otherwise', function (mixed $stored, int $days) {
    Functions\when('get_option')->alias(static fn (string $option, mixed $default = false): mixed => $stored ?? $default);

    expect(trashClock()->retention->retentionDays())->toBe($days);
})->with([
    'nothing set' => [null, 30],
    'the site set 7' => [7, 7],
    'the site said never' => [0, 0],
    'a negative number is never' => [-5, 0],
    'a number too large is the most there is' => [99999, RetentionSettings::MAX_DAYS],
]);

it('deletes what went into the trash before the cutoff, and leaves the rest', function () {
    Functions\when('__')->returnArg();
    $clock = trashClock();
    $clock->trash->records = [
        10 => ['id' => 10, 'form' => 'contact'],
        11 => ['id' => 11, 'form' => 'contact'],
    ];
    $clock->trash->trashedAt = [10 => '2026-08-01T10:00:00+00:00', 11 => '2026-10-01T10:00:00+00:00'];

    $removed = $clock->retention->pruneOlderThan(new DateTimeImmutable('2026-09-08T00:00:00+00:00'));

    expect($removed)->toBe(1)
        ->and($clock->trash->deleted)->toBe([10])
        ->and(array_keys($clock->trash->records))->toBe([11])
        ->and($clock->activity->events[0]->context['by'])->toBe('expiry');
});

it('takes what WordPress trashed onto its own clock before it looks for what is due', function () {
    $clock = trashClock();

    $clock->retention->pruneOlderThan(new DateTimeImmutable('2026-09-08T00:00:00+00:00'));

    expect($clock->trash->adoptions)->toBe(1);
});
