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
use Corex\Notifications\NotificationAction;
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
            // Who wants what, in the title, because that is the decision being asked for. "New
            // access request" made every request identical until you opened it.
            rendered: [
                'title' => sprintf(
                    /* translators: %s: the name of the person requesting access. */
                    __('%s is waiting for access', 'corex'),
                    $event->requesterName,
                ),
                'body'  => sprintf(
                    /* translators: %s: the requested ability or area key. */
                    __('They asked for %s. Approve or decline it in Access & Abilities.', 'corex'),
                    $target,
                ),
            ],
            dedupKey: 'access.request:' . $event->requestId,
            recipient: NotificationRecipient::forAbility(CorexAbility::MANAGE_ACCESS),
            occurredAt: new DateTimeImmutable('now'),
            sourceType: 'access_request',
            sourceId: (string) $event->requestId,
            action: NotificationAction::to(
                'notifications.access.request.action',
                add_query_arg(['page' => 'corex-access'], admin_url('admin.php')),
                CorexAbility::MANAGE_ACCESS,
                __('Open Access & Abilities', 'corex'),
            ),
        ));
    }
}
