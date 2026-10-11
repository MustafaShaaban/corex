<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications\Producers;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;
use Corex\Email\Design\EmailDesignFailedEvent;
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
 * Tells the email managers when the site's own email design could not be used and an email went
 * out in CoreX's standard one instead (spec 106, FR-017).
 *
 * Keyed by the design, so a template that fails on every reply is one notification that says so,
 * not one for each reply. Available only where the email add-on is present.
 */
final class EmailDesignFailureNotificationProducer implements NotificationProducer
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ListenerProvider $listeners,
    ) {
    }

    public function key(): string
    {
        return 'email.design_failures';
    }

    public function isAvailable(): bool
    {
        return class_exists(EmailDesignFailedEvent::class);
    }

    public function register(): void
    {
        $this->listeners->listen(EmailDesignFailedEvent::class, function (object $event): void {
            if ($event instanceof EmailDesignFailedEvent) {
                $this->notifications->publish($this->failure($event));
            }
        });
    }

    private function failure(EmailDesignFailedEvent $event): Notification
    {
        return Notification::create(
            type: 'email.design_failed',
            category: NotificationCategory::EMAIL,
            severity: NotificationSeverity::WARNING,
            sourceModule: 'email',
            titleKey: 'notifications.email.design_failed.title',
            messageKey: 'notifications.email.design_failed.body',
            rendered: [
                'title' => __('This site\'s email design could not be used', 'corex'),
                'body'  => sprintf(
                    /* translators: 1: what went wrong with the design. 2: the PHP class of the design. */
                    __('%1$s The email was sent in the standard CoreX design instead. The design is %2$s.', 'corex'),
                    $this->said($event),
                    $event->design,
                ),
            ],
            dedupKey: 'email.design_failed:' . $event->design,
            recipient: NotificationRecipient::forAbility(CorexAbility::MANAGE_EMAIL),
            occurredAt: new DateTimeImmutable('now'),
            sourceType: 'email_design',
            sourceId: $event->design,
            metadata: ['reason' => $event->reason],
            action: NotificationAction::to(
                'notifications.email.design_failed.action',
                add_query_arg(['page' => 'corex-email-studio'], admin_url('admin.php')),
                CorexAbility::MANAGE_EMAIL,
                __('Open Email Studio', 'corex'),
            ),
        );
    }

    /** What went wrong, for somebody who did not write the design and has to ask who did. */
    private function said(EmailDesignFailedEvent $event): string
    {
        return match ($event->reason) {
            EmailDesignFailedEvent::THREW => sprintf(
                /* translators: %s: the error message the design's code raised. */
                __('It stopped with an error: %s', 'corex'),
                rtrim($event->detail, '.') . '.',
            ),
            EmailDesignFailedEvent::EMPTY => __('It answered with an empty email.', 'corex'),
            EmailDesignFailedEvent::WITHOUT_MESSAGE => __('It answered with an email that did not hold the message.', 'corex'),
            default => __('What the site supplied is not an email design.', 'corex'),
        };
    }
}
