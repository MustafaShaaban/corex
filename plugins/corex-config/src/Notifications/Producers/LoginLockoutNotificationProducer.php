<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications\Producers;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;
use Corex\Config\Notifications\Lockouts\LoginLockoutNotice;
use Corex\Config\Security\LoginProtection\LoginAccounts;
use Corex\Events\ListenerProvider;
use Corex\Notifications\Notification;
use Corex\Notifications\NotificationAction;
use Corex\Notifications\NotificationCategory;
use Corex\Notifications\NotificationProducer;
use Corex\Notifications\NotificationRecipient;
use Corex\Notifications\NotificationService;
use Corex\Notifications\NotificationSeverity;
use Corex\Security\LoginLockoutEvent;
use DateTimeImmutable;

/**
 * Turns a login lockout into a security notification for the operations/security managers (holders of
 * {@see CorexAbility::MANAGE_OPERATIONS}). Keyed by the locked account, or by the fingerprint of an
 * unknown username, so repeated lockouts merge into one growing notification — a sustained attack
 * reads as one escalating signal, not a flood. Dependency-aware via class_exists (FR-014).
 *
 * The notice names the account, or says "unknown username" with a short fingerprint, and gives the
 * network only as a fingerprint (spec 124, FR-016, FR-020): see {@see LoginLockoutNotice}.
 */
final class LoginLockoutNotificationProducer implements NotificationProducer
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ListenerProvider $listeners,
        private readonly LoginAccounts $accounts,
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
        $notice = LoginLockoutNotice::of(
            $event->userId,
            $event->userId === null ? null : $this->accounts->nameOf($event->userId),
            $event->identityFingerprint,
            $event->networkFingerprint,
            $event->lockedUntil,
        );

        return Notification::create(
            type: LoginLockoutNotice::TYPE,
            category: NotificationCategory::SECURITY,
            severity: NotificationSeverity::WARNING,
            sourceModule: 'security',
            titleKey: 'notifications.security.lockout.title',
            messageKey: 'notifications.security.lockout.body',
            rendered: $notice->rendered,
            dedupKey: $notice->dedupKey,
            recipient: NotificationRecipient::forAbility(CorexAbility::MANAGE_OPERATIONS),
            occurredAt: new DateTimeImmutable('now'),
            sourceType: $notice->sourceType,
            sourceId: $notice->sourceId,
            metadata: $notice->metadata,
            action: NotificationAction::to(
                'notifications.security.lockout.action',
                add_query_arg(['page' => 'corex-operations-security'], admin_url('admin.php')),
                CorexAbility::MANAGE_OPERATIONS,
                __('Review in Operations & Security', 'corex'),
            ),
        );
    }
}
