<?php

/**
 * Unit tests for the export-ready notification producer (spec 072 US4: FR-013).
 *
 * A completed export job becomes a personal "your export is ready" notification for the person who
 * ran it. Non-export jobs and failed exports raise nothing here (failures are a separate producer).
 *
 * @package Corex\Tests\Unit\Notifications
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Notifications\Producers\ExportReadyNotificationProducer;
use Corex\Events\ListenerProvider;
use Corex\Jobs\BoundedJob;
use Corex\Jobs\JobFinishedEvent;
use Corex\Notifications\Notification;
use Corex\Notifications\NotificationCategory;
use Corex\Notifications\NotificationQuery;
use Corex\Notifications\NotificationService;

beforeEach(function () {
    Functions\stubTranslationFunctions();
});

/** A NotificationService that records every published notification. */
function exportRecordingService(): NotificationService
{
    return new class implements NotificationService {
        /** @var list<Notification> */
        public array $published = [];

        public function publish(Notification $notification): Notification
        {
            $this->published[] = $notification;

            return $notification->withId(count($this->published));
        }

        public function resolve(string $dedupKey, string $reason): int
        {
            return 0;
        }

        public function forCurrentActor(NotificationQuery $query): array
        {
            return ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 20];
        }

        public function unreadCountForCurrentActor(): int
        {
            return 0;
        }
    };
}

function fireExportEvent(NotificationService $service, JobFinishedEvent $event): NotificationService
{
    $listeners = new ListenerProvider();
    (new ExportReadyNotificationProducer($service, $listeners))->register();
    foreach ($listeners->listenersFor($event) as $listener) {
        $listener($event);
    }

    return $service;
}

it('notifies the actor when their export completes', function () {
    $service = fireExportEvent(
        exportRecordingService(),
        new JobFinishedEvent(jobId: 90, kind: 'submissions.export', actorId: 6, state: BoundedJob::STATE_COMPLETED),
    );

    expect($service->published)->toHaveCount(1);
    $note = $service->published[0];
    expect($note->type)->toBe('export.ready')
        ->and($note->category)->toBe(NotificationCategory::IMPORTS_EXPORTS)
        ->and($note->dedupKey)->toBe('export.ready:90')
        // Visible to the actor (user 6), not to an arbitrary other user.
        ->and($note->recipient->canBeSeenBy(6, fn (string $a): bool => false))->toBeTrue()
        ->and($note->recipient->canBeSeenBy(7, fn (string $a): bool => false))->toBeFalse();
});

it('ignores a completed job that is not an export', function () {
    $service = fireExportEvent(
        exportRecordingService(),
        new JobFinishedEvent(90, 'data.import', 6, BoundedJob::STATE_COMPLETED),
    );

    expect($service->published)->toBeEmpty();
});

it('ignores a failed export (that is the failure producer’s job)', function () {
    $service = fireExportEvent(
        exportRecordingService(),
        new JobFinishedEvent(90, 'submissions.export', 6, BoundedJob::STATE_FAILED),
    );

    expect($service->published)->toBeEmpty();
});

it('is keyed and available like the other producers', function () {
    $producer = new ExportReadyNotificationProducer(exportRecordingService(), new ListenerProvider());

    expect($producer->key())->toBe('jobs.exports')
        ->and($producer->isAvailable())->toBe(class_exists(JobFinishedEvent::class));
});
