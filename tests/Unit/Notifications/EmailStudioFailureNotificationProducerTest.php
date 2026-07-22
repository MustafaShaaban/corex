<?php

/**
 * Unit tests for the Email Studio delivery-failure notification producer (spec 072 US4: FR-013).
 *
 * A failed live/resend delivery becomes a notification for the email managers, keyed by provider so a
 * provider outage reads as one escalating signal. Test sends never notify.
 *
 * @package Corex\Tests\Unit\Notifications
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Access\CorexAbility;
use Corex\Config\Notifications\Producers\EmailStudioFailureNotificationProducer;
use Corex\Email\Studio\EmailStudioDeliveryFailedEvent;
use Corex\Events\ListenerProvider;
use Corex\Notifications\NotificationCategory;
use Corex\Notifications\NotificationService;
use Corex\Tests\Support\RecordingNotificationService;

beforeEach(function () {
    Functions\stubTranslationFunctions();
});

function fireEmailFailure(NotificationService $service, EmailStudioDeliveryFailedEvent $event): NotificationService
{
    $listeners = new ListenerProvider();
    (new EmailStudioFailureNotificationProducer($service, $listeners))->register();
    foreach ($listeners->listenersFor($event) as $listener) {
        $listener($event);
    }

    return $service;
}

it('notifies the email managers when a live delivery fails', function () {
    $service = fireEmailFailure(
        new RecordingNotificationService(),
        new EmailStudioDeliveryFailedEvent('a-1', 'postmark', 'Provider rejected the message.', 'route', true),
    );

    expect($service->published)->toHaveCount(1);
    $note = $service->published[0];
    expect($note->type)->toBe('email.delivery_failed')
        ->and($note->category)->toBe(NotificationCategory::EMAIL)
        ->and($note->dedupKey)->toBe('email.delivery_failed:postmark')
        ->and($note->recipient->canBeSeenBy(9, fn (string $a): bool => $a === CorexAbility::MANAGE_EMAIL))->toBeTrue();
});

it('does not notify for a test send', function () {
    $service = fireEmailFailure(
        new RecordingNotificationService(),
        new EmailStudioDeliveryFailedEvent('a-2', 'postmark', 'Provider rejected the message.', 'test', true),
    );

    expect($service->published)->toBeEmpty();
});

it('is keyed and available like the other producers', function () {
    $producer = new EmailStudioFailureNotificationProducer(new RecordingNotificationService(), new ListenerProvider());

    expect($producer->key())->toBe('email.failures')
        ->and($producer->isAvailable())->toBe(class_exists(EmailStudioDeliveryFailedEvent::class));
});
