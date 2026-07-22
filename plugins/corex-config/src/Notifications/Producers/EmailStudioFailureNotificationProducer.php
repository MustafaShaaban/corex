<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications\Producers;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;
use Corex\Email\Studio\EmailStudioDeliveryFailedEvent;
use Corex\Events\ListenerProvider;
use Corex\Notifications\Notification;
use Corex\Notifications\NotificationCategory;
use Corex\Notifications\NotificationProducer;
use Corex\Notifications\NotificationRecipient;
use Corex\Notifications\NotificationService;
use Corex\Notifications\NotificationSeverity;
use DateTimeImmutable;

/**
 * Turns an Email Studio delivery failure into a notification for the email managers (holders of
 * {@see CorexAbility::MANAGE_EMAIL}). Keyed by provider, so a provider outage merges into one
 * escalating signal rather than a flood. Test sends never notify — the admin runs those and sees the
 * result inline. Available only when the Email Studio addon is present (FR-014), via class_exists.
 */
final class EmailStudioFailureNotificationProducer implements NotificationProducer
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ListenerProvider $listeners,
    ) {
    }

    public function key(): string
    {
        return 'email.failures';
    }

    public function isAvailable(): bool
    {
        return class_exists(EmailStudioDeliveryFailedEvent::class);
    }

    public function register(): void
    {
        $this->listeners->listen(EmailStudioDeliveryFailedEvent::class, function (object $event): void {
            if ($event instanceof EmailStudioDeliveryFailedEvent && $event->source !== 'test') {
                $this->notifications->publish($this->failure($event));
            }
        });
    }

    private function failure(EmailStudioDeliveryFailedEvent $event): Notification
    {
        return Notification::create(
            type: 'email.delivery_failed',
            category: NotificationCategory::EMAIL,
            severity: NotificationSeverity::ERROR,
            sourceModule: 'email',
            titleKey: 'notifications.email.delivery_failed.title',
            messageKey: 'notifications.email.delivery_failed.body',
            rendered: [
                'title' => __('Email delivery failed', 'corex'),
                'body'  => sprintf(
                    /* translators: 1: the delivery provider, 2: a short, secret-free reason. */
                    __('An email through %1$s could not be delivered: %2$s', 'corex'),
                    $event->provider,
                    $event->safeReason,
                ),
            ],
            dedupKey: 'email.delivery_failed:' . $event->provider,
            recipient: NotificationRecipient::forAbility(CorexAbility::MANAGE_EMAIL),
            occurredAt: new DateTimeImmutable('now'),
            sourceType: 'email_attempt',
            sourceId: $event->attemptId,
            metadata: ['provider' => $event->provider, 'retryable' => $event->retryable],
        );
    }
}
