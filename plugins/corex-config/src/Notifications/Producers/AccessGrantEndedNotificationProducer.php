<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications\Producers;

defined('ABSPATH') || exit;

use Corex\Access\AccessGrantEndedEvent;
use Corex\Access\AccessRequestStore;
use Corex\Access\AccessUserDirectory;
use Corex\Events\ListenerProvider;
use Corex\Notifications\Notification;
use Corex\Notifications\NotificationCategory;
use Corex\Notifications\NotificationProducer;
use Corex\Notifications\NotificationRecipient;
use Corex\Notifications\NotificationService;
use Corex\Notifications\NotificationSeverity;
use DateTimeImmutable;

/**
 * Tells the person and the approver when a grant ends by its expiry (spec 112, FR-006). A revoked
 * grant is told nobody: the administrator who revoked it is the one who acted. An approver whose
 * account is gone is skipped; the person is told all the same.
 */
final class AccessGrantEndedNotificationProducer implements NotificationProducer
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ListenerProvider $listeners,
        private readonly AccessUserDirectory $users,
    ) {
    }

    public function key(): string
    {
        return 'access.grants';
    }

    public function isAvailable(): bool
    {
        return class_exists(AccessGrantEndedEvent::class);
    }

    public function register(): void
    {
        $this->listeners->listen(AccessGrantEndedEvent::class, function (object $event): void {
            if ($event instanceof AccessGrantEndedEvent && $event->reason === AccessRequestStore::ENDED_EXPIRED) {
                $this->handle($event);
            }
        });
    }

    private function handle(AccessGrantEndedEvent $event): void
    {
        $abilities = implode(', ', $event->abilityKeys);

        $this->publish($event, $event->personId, 'person', sprintf(
            /* translators: %s: the CoreX abilities the grant gave, comma-separated keys. */
            __('Your access to %s has ended, as approved. Ask again from the screen if you still need it.', 'corex'),
            $abilities,
        ));

        if ($event->approverId !== null && $this->users->exists($event->approverId)) {
            $this->publish($event, $event->approverId, 'approver', sprintf(
                /* translators: 1: the person's name, 2: the CoreX abilities the grant gave. */
                __('The access you approved for %1$s (%2$s) has ended.', 'corex'),
                $this->users->displayName($event->personId),
                $abilities,
            ));
        }
    }

    private function publish(AccessGrantEndedEvent $event, int $userId, string $who, string $body): void
    {
        $this->notifications->publish(Notification::create(
            type: 'access.grant.expired',
            category: NotificationCategory::ACCESS,
            severity: NotificationSeverity::INFORMATION,
            sourceModule: 'access',
            titleKey: 'notifications.access.grant_expired.title',
            messageKey: 'notifications.access.grant_expired.body',
            rendered: [
                'title' => __('Access has ended', 'corex'),
                'body'  => $body,
            ],
            dedupKey: sprintf('access.grant.expired:%d:%s', $event->requestId, $who),
            recipient: NotificationRecipient::forUser($userId),
            occurredAt: new DateTimeImmutable('now'),
            sourceType: 'access_request',
            sourceId: (string) $event->requestId,
        ));
    }
}
