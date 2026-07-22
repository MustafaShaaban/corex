<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications\Producers;

defined('ABSPATH') || exit;

use Corex\Access\AccessRequestedEvent;
use Corex\Access\CorexAbility;
use Corex\Events\ListenerProvider;
use Corex\Notifications\Notification;
use Corex\Notifications\NotificationCategory;
use Corex\Notifications\NotificationProducer;
use Corex\Notifications\NotificationRecipient;
use Corex\Notifications\NotificationService;
use Corex\Notifications\NotificationSeverity;
use DateTimeImmutable;

/**
 * Turns a pending access request into an actionable notification for the access managers (holders of
 * {@see CorexAbility::MANAGE_ACCESS}). Each request is individually decided, so its dedup key is
 * unique — two requests are two notifications, never merged.
 *
 * Dependency-aware: produces nothing unless the access request event exists (FR-014).
 */
final class AccessRequestNotificationProducer implements NotificationProducer
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ListenerProvider $listeners,
    ) {
    }

    public function key(): string
    {
        return 'access.requests';
    }

    public function isAvailable(): bool
    {
        return class_exists(AccessRequestedEvent::class);
    }

    public function register(): void
    {
        $this->listeners->listen(AccessRequestedEvent::class, function (object $event): void {
            if ($event instanceof AccessRequestedEvent) {
                $this->handle($event);
            }
        });
    }

    private function handle(AccessRequestedEvent $event): void
    {
        $target = $event->abilityKey ?? $event->areaKey ?? '';
        $this->notifications->publish(Notification::create(
            type: 'access.request',
            category: NotificationCategory::ACCESS,
            severity: NotificationSeverity::ACTION,
            sourceModule: 'access',
            titleKey: 'notifications.access.request.title',
            messageKey: 'notifications.access.request.body',
            rendered: [
                'title' => __('New access request', 'corex'),
                'body'  => sprintf(
                    /* translators: 1: requester name, 2: requested ability or area key. */
                    __('%1$s requested access to %2$s.', 'corex'),
                    $event->requesterName,
                    $target,
                ),
            ],
            dedupKey: 'access.request:' . $event->requestId,
            recipient: NotificationRecipient::forAbility(CorexAbility::MANAGE_ACCESS),
            occurredAt: new DateTimeImmutable('now'),
            sourceType: 'access_request',
            sourceId: (string) $event->requestId,
        ));
    }
}
