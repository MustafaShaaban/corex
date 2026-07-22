<?php

/**
 * Unit tests for the job-failure notification producer (spec 072 US4: FR-013).
 *
 * A background job reaching a failed terminal state becomes an operational notification. Successful
 * or still-running jobs raise nothing — routine completion is not a notification (FR-007).
 *
 * @package Corex\Tests\Unit\Notifications
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Access\CorexAbility;
use Corex\Config\Notifications\Producers\JobFailureNotificationProducer;
use Corex\Events\ListenerProvider;
use Corex\Jobs\BoundedJob;
use Corex\Jobs\JobFinishedEvent;
use Corex\Notifications\NotificationCategory;
use Corex\Notifications\NotificationService;
use Corex\Tests\Support\RecordingNotificationService;

beforeEach(function () {
    Functions\stubTranslationFunctions();
});

/** A NotificationService that records every published notification. */
function jobRecordingService(): NotificationService
{
    return new RecordingNotificationService();
}

function fireJobFinished(NotificationService $service, JobFinishedEvent $event): NotificationService
{
    $listeners = new ListenerProvider();
    (new JobFailureNotificationProducer($service, $listeners))->register();
    foreach ($listeners->listenersFor($event) as $listener) {
        $listener($event);
    }

    return $service;
}

it('publishes a job-failure notification to the operations managers', function () {
    $service = fireJobFinished(
        jobRecordingService(),
        new JobFinishedEvent(jobId: 88, kind: 'submissions.export', actorId: 4, state: BoundedJob::STATE_FAILED),
    );

    expect($service->published)->toHaveCount(1);
    $note = $service->published[0];
    expect($note->type)->toBe('job.failed')
        ->and($note->category)->toBe(NotificationCategory::JOBS)
        ->and($note->dedupKey)->toBe('job.failed:88')
        ->and($note->recipient->canBeSeenBy(9, fn (string $a): bool => $a === CorexAbility::MANAGE_OPERATIONS))->toBeTrue();
});

it('does not carry the raw error summary into the notification', function () {
    $service = fireJobFinished(
        jobRecordingService(),
        new JobFinishedEvent(88, 'data.import', 4, BoundedJob::STATE_FAILED, 'DB error: password=hunter2 at host db-1'),
    );

    $encoded = json_encode($service->published[0]->toArray());
    expect($encoded)->not->toContain('hunter2')
        ->and($encoded)->not->toContain('db-1');
});

it('raises nothing for a completed job', function () {
    $service = fireJobFinished(
        jobRecordingService(),
        new JobFinishedEvent(88, 'submissions.export', 4, BoundedJob::STATE_COMPLETED),
    );

    expect($service->published)->toBeEmpty();
});
