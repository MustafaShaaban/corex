<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications\Producers;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;
use Corex\Events\ListenerProvider;
use Corex\Notifications\Notification;
use Corex\Notifications\NotificationCategory;
use Corex\Notifications\NotificationProducer;
use Corex\Notifications\NotificationRecipient;
use Corex\Notifications\NotificationService;
use Corex\Notifications\NotificationSeverity;
use Corex\Security\LoginLockoutEvent;
use DateTimeImmutable;

/**
 * Turns a login lockout into a security notification for the operations/security managers (holders of
 * {@see CorexAbility::MANAGE_OPERATIONS}). Keyed by the locked identity, so repeated lockouts of the
 * same account merge into one growing notification — a sustained attack reads as one escalating
 * signal, not a flood. Dependency-aware via class_exists (FR-014).
 */
final class LoginLockoutNotificationProducer implements NotificationProducer
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ListenerProvider $listeners,
    ) {
    }

    public function key(): string
    {
        return 'security.lockouts';
    }

    public function isAvailable(): bool
    {
        return class_exists(LoginLockoutEvent::class);
    }

    public function register(): void
    {
        $this->listeners->listen(LoginLockoutEvent::class, function (object $event): void {
            if ($event instanceof LoginLockoutEvent) {
                $this->notifications->publish($this->lockout($event));
            }
        });
    }

    private function lockout(LoginLockoutEvent $event): Notification
    {
        return Notification::create(
            type: 'security.lockout',
            category: NotificationCategory::SECURITY,
            severity: NotificationSeverity::WARNING,
            sourceModule: 'security',
            titleKey: 'notifications.security.lockout.title',
            messageKey: 'notifications.security.lockout.body',
            rendered: [
                'title' => __('Sign-in lockout triggered', 'corex'),
                'body'  => sprintf(
                    /* translators: 1: the locked account identity, 2: the client IP address. */
                    __('Repeated failed sign-ins locked out “%1$s” from %2$s.', 'corex'),
                    $event->identity,
                    $event->clientIp,
                ),
            ],
            dedupKey: 'security.lockout:' . $event->identity,
            recipient: NotificationRecipient::forAbility(CorexAbility::MANAGE_OPERATIONS),
            occurredAt: new DateTimeImmutable('now'),
            sourceType: 'login_identity',
            sourceId: $event->identity,
            metadata: ['client_ip' => $event->clientIp, 'locked_until' => $event->lockedUntil->format(DATE_ATOM)],
        );
    }
}
