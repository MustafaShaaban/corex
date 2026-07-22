<?php

/**
 * Unit tests for the submission-assignment notification producer (spec 072 US4: FR-013).
 *
 * When a submission is assigned to a person, that person is notified. Team/role assignments and
 * self-assignment raise nothing here.
 *
 * @package Corex\Tests\Unit\Notifications
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Notifications\Producers\SubmissionAssignedNotificationProducer;
use Corex\Config\Submissions\SubmissionAssignedEvent;
use Corex\Events\ListenerProvider;
use Corex\Notifications\NotificationCategory;
use Corex\Notifications\NotificationService;
use Corex\Tests\Support\RecordingNotificationService;

beforeEach(function () {
    Functions\stubTranslationFunctions();
});

function fireAssignment(NotificationService $service, SubmissionAssignedEvent $event): NotificationService
{
    $listeners = new ListenerProvider();
    (new SubmissionAssignedNotificationProducer($service, $listeners))->register();
    foreach ($listeners->listenersFor($event) as $listener) {
        $listener($event);
    }

    return $service;
}

it('notifies the user a submission is assigned to', function () {
    $service = fireAssignment(
        new RecordingNotificationService(),
        new SubmissionAssignedEvent(submissionId: 12, assigneeType: 'user', assigneeKey: '5', actorId: 3),
    );

    expect($service->published)->toHaveCount(1);
    $note = $service->published[0];
    expect($note->type)->toBe('submission.assigned')
        ->and($note->category)->toBe(NotificationCategory::SUBMISSIONS)
        ->and($note->dedupKey)->toBe('submission.assigned:12:5')
        ->and($note->recipient->canBeSeenBy(5, fn (string $a): bool => false))->toBeTrue()
        ->and($note->recipient->canBeSeenBy(6, fn (string $a): bool => false))->toBeFalse();
});

it('does not notify on a team or role assignment', function () {
    $team = fireAssignment(new RecordingNotificationService(), new SubmissionAssignedEvent(12, 'team', 'support', 3));
    $role = fireAssignment(new RecordingNotificationService(), new SubmissionAssignedEvent(12, 'role', 'editor', 3));

    expect($team->published)->toBeEmpty()
        ->and($role->published)->toBeEmpty();
});

it('does not notify when a user assigns a submission to themselves', function () {
    $service = fireAssignment(
        new RecordingNotificationService(),
        new SubmissionAssignedEvent(12, 'user', '3', 3),
    );

    expect($service->published)->toBeEmpty();
});

it('is keyed and available like the other producers', function () {
    $producer = new SubmissionAssignedNotificationProducer(new RecordingNotificationService(), new ListenerProvider());

    expect($producer->key())->toBe('submissions.assignments')
        ->and($producer->isAvailable())->toBe(class_exists(SubmissionAssignedEvent::class));
});
