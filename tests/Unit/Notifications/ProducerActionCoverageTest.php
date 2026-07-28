<?php

/**
 * Every producer that names a destination must carry it as a link (spec 087, FR-014).
 *
 * Before this, one producer of eight set an action. The other seven wrote the destination into
 * their body text as prose — "Open the Submission Inbox to read and assign it", "Check the
 * connection in Email Studio", "Open Operations to see what happened" — leaving the reader to find
 * a screen the record already knew the address of.
 *
 * Asserted per producer rather than by walking the registry, because the registry only exposes
 * producers whose module is available, and a test that silently covers five of eight on one machine
 * and eight of eight on another is not coverage.
 *
 * @package Corex\Tests\Unit\Notifications
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Access\AccessRequestedEvent;
use Corex\Access\CorexAbility;
use Corex\Config\DataModels\DataExportJobHandler;
use Corex\Config\Notifications\Producers\AccessRequestNotificationProducer;
use Corex\Config\Notifications\Producers\EmailStudioFailureNotificationProducer;
use Corex\Config\Notifications\Producers\ExportReadyNotificationProducer;
use Corex\Config\Notifications\Producers\JobFailureNotificationProducer;
use Corex\Config\Notifications\Producers\LoginLockoutNotificationProducer;
use Corex\Config\Notifications\Producers\SubmissionAssignedNotificationProducer;
use Corex\Config\Notifications\Producers\SubmissionNotificationProducer;
use Corex\Config\Submissions\SubmissionAssignedEvent;
use Corex\Config\Submissions\SubmissionExportJobHandler;
use Corex\Email\Studio\EmailStudioDeliveryFailedEvent;
use Corex\Events\ListenerProvider;
use Corex\Forms\Submission\NotificationDelivery;
use Corex\Forms\Submission\SubmissionProcessedEvent;
use Corex\Jobs\BoundedJob;
use Corex\Jobs\JobFinishedEvent;
use Corex\Notifications\Notification;
use Corex\Security\LoginLockoutEvent;
use Corex\Tests\Support\AdminUrlStubs;
use Corex\Tests\Support\RecordingNotificationService;

beforeEach(function () {
    Functions\stubTranslationFunctions();
    AdminUrlStubs::install();
});

/**
 * Fire one event through one producer and return everything it published.
 *
 * @param callable(RecordingNotificationService,ListenerProvider):object $build
 *
 * @return list<Notification>
 */
function actionsFrom(callable $build, object $event): array
{
    $service = new RecordingNotificationService();
    $listeners = new ListenerProvider();
    $build($service, $listeners)->register();

    foreach ($listeners->listenersFor($event) as $listener) {
        $listener($event);
    }

    return $service->published;
}

it('sends a new submission to that form’s rows, not to the inbox at large', function () {
    $published = actionsFrom(
        static fn ($s, $l) => new SubmissionNotificationProducer($s, $l),
        new SubmissionProcessedEvent(
            submissionId: 42,
            flowId: 7,
            flowSlug: 'contact',
            ownerId: 3,
            delivery: NotificationDelivery::notAttempted('no_binding', 'none'),
        ),
    );

    $action = $published[0]->action;

    expect($action)->not->toBeNull()
        ->and($action->url)->toContain('page=corex-submissions')
        ->and($action->url)->toContain('corex_form=7')
        ->and($action->ability)->toBe(CorexAbility::MANAGE_SUBMISSIONS)
        ->and($action->label)->not->toBe('');

    // The body no longer tells somebody to go where the link now goes.
    expect($published[0]->rendered['body'])->not->toContain('Open the Submission Inbox');
});

it('sends an assigned submission to the submission itself', function () {
    $action = actionsFrom(
        static fn ($s, $l) => new SubmissionAssignedNotificationProducer($s, $l),
        new SubmissionAssignedEvent(submissionId: 42, assigneeType: 'user', assigneeKey: '5', actorId: 3),
    )[0]->action;

    expect($action)->not->toBeNull()
        ->and($action->url)->toContain('corex_submission=42')
        ->and($action->ability)->toBe(CorexAbility::MANAGE_SUBMISSIONS);
});

it('sends an access request to the screen that decides it', function () {
    $action = actionsFrom(
        static fn ($s, $l) => new AccessRequestNotificationProducer($s, $l),
        new AccessRequestedEvent(
            requestId: 55,
            requesterId: 12,
            requesterName: 'Dana Requester',
            abilityKey: CorexAbility::MANAGE_SUBMISSIONS,
            areaKey: null,
        ),
    )[0]->action;

    expect($action)->not->toBeNull()
        ->and($action->url)->toContain('page=corex-access')
        ->and($action->ability)->toBe(CorexAbility::MANAGE_ACCESS);
});

it('sends a lockout to Operations, where the attempts are', function () {
    $action = actionsFrom(
        static fn ($s, $l) => new LoginLockoutNotificationProducer($s, $l),
        new LoginLockoutEvent('editor@example.com', '203.0.113.9', new DateTimeImmutable('2026-07-21T10:00:00Z')),
    )[0]->action;

    expect($action)->not->toBeNull()
        ->and($action->url)->toContain('page=corex-operations-security')
        ->and($action->ability)->toBe(CorexAbility::MANAGE_OPERATIONS);
});

/**
 * The body deliberately withholds the failure reason — it has been seen to carry credentials — and
 * points at Operations instead. That pointer is a link now, gated on the ability the screen itself
 * enforces, so the record does not offer a door it knows will be shut.
 */
it('sends a failed job to Operations without carrying the reason', function () {
    $published = actionsFrom(
        static fn ($s, $l) => new JobFailureNotificationProducer($s, $l),
        new JobFinishedEvent(88, 'data.import', 4, BoundedJob::STATE_FAILED, 'DB error: password=hunter2 at host db-1'),
    );

    $action = $published[0]->action;

    expect($action)->not->toBeNull()
        ->and($action->url)->toContain('page=corex-operations-security')
        ->and($action->ability)->toBe(CorexAbility::MANAGE_OPERATIONS)
        ->and($action->url)->not->toContain('hunter2')
        ->and($published[0]->rendered['body'])->not->toContain('hunter2');
});

/**
 * The destination depends on which export it was, because the two screens that produce one are
 * gated by different abilities — so this asserts the mapping rather than one "exports live here"
 * guess.
 */
it('sends a ready submissions export to the inbox', function () {
    $action = actionsFrom(
        static fn ($s, $l) => new ExportReadyNotificationProducer($s, $l),
        new JobFinishedEvent(90, SubmissionExportJobHandler::KIND, 6, BoundedJob::STATE_COMPLETED),
    )[0]->action;

    expect($action)->not->toBeNull()
        ->and($action->url)->toContain('page=corex-submissions')
        ->and($action->ability)->toBe(CorexAbility::MANAGE_SUBMISSIONS);
});

it('sends a ready data export to the models screen', function () {
    $action = actionsFrom(
        static fn ($s, $l) => new ExportReadyNotificationProducer($s, $l),
        new JobFinishedEvent(90, DataExportJobHandler::KIND, 6, BoundedJob::STATE_COMPLETED),
    )[0]->action;

    expect($action)->not->toBeNull()
        ->and($action->url)->toContain('page=corex-data-models')
        ->and($action->ability)->toBe(CorexAbility::MANAGE_DATA);
});

/**
 * An export kind nobody has placed gets no link at all. A link to the wrong screen is worse than
 * the notification's own text, which already says the file is ready.
 */
it('offers no link for an export kind it cannot place', function () {
    $action = actionsFrom(
        static fn ($s, $l) => new ExportReadyNotificationProducer($s, $l),
        new JobFinishedEvent(90, 'something.new.export', 6, BoundedJob::STATE_COMPLETED),
    )[0]->action;

    expect($action)->toBeNull();
});

it('sends an Email Studio delivery failure to Email Studio', function () {
    $action = actionsFrom(
        static fn ($s, $l) => new EmailStudioFailureNotificationProducer($s, $l),
        new EmailStudioDeliveryFailedEvent('a-1', 'postmark', 'Provider rejected the message.', 'route', true),
    )[0]->action;

    expect($action)->not->toBeNull()
        ->and($action->url)->toContain('page=corex-email-studio')
        ->and($action->ability)->toBe(CorexAbility::MANAGE_EMAIL);
});

/**
 * Gated on MANAGE_EMAIL rather than on the MANAGE_SUBMISSIONS this notification is addressed to: a
 * submissions manager who cannot open Email Studio is told the mail failed, and is not offered a
 * door that would refuse them.
 */
it('sends an undelivered submission email to Email Studio, gated on the email ability', function () {
    $published = actionsFrom(
        static fn ($s, $l) => new SubmissionNotificationProducer($s, $l),
        new SubmissionProcessedEvent(
            submissionId: 42,
            flowId: 7,
            flowSlug: 'contact',
            ownerId: 3,
            delivery: NotificationDelivery::wpMail(false, '11111111-1111-4111-8111-111111111111', 'refused'),
        ),
    );

    // Two notifications: the submission itself, then the email failure.
    $action = $published[1]->action;

    expect($published)->toHaveCount(2)
        ->and($action)->not->toBeNull()
        ->and($action->url)->toContain('page=corex-email-studio')
        ->and($action->ability)->toBe(CorexAbility::MANAGE_EMAIL);
});
