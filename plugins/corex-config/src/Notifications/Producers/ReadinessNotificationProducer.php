<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications\Producers;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;
use Corex\Config\Operations\ReadinessEvaluatedEvent;
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
 * Reconciles readiness notifications against an evaluation. Readiness is a condition, not an event:
 * each blocking check raises one notification keyed by the check (so recurrence merges and reopens),
 * and a check that now passes resolves its notification (FR-010). Reconciliation is idempotent — it
 * runs the full snapshot every time, so appear and clear both fall out of one pass.
 *
 * Dependency-aware via class_exists (FR-014). Notifications go to the operations managers.
 */
final class ReadinessNotificationProducer implements NotificationProducer
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ListenerProvider $listeners,
    ) {
    }

    public function key(): string
    {
        return 'operations.readiness';
    }

    public function isAvailable(): bool
    {
        return class_exists(ReadinessEvaluatedEvent::class);
    }

    public function register(): void
    {
        $this->listeners->listen(ReadinessEvaluatedEvent::class, function (object $event): void {
            if ($event instanceof ReadinessEvaluatedEvent) {
                $this->reconcile($event);
            }
        });
    }

    private function reconcile(ReadinessEvaluatedEvent $event): void
    {
        foreach ($event->snapshot->checks() as $check) {
            $dedupKey = 'readiness.blocker:' . $check['key'];

            if ($check['state'] === 'blocking') {
                $this->notifications->publish($this->blocker($check, $dedupKey));
            } else {
                $this->notifications->resolve($dedupKey, __('This readiness check now passes.', 'corex'));
            }
        }
    }

    /**
     * @param array{key:string,label:string,state:string,summary:string,resolution_url:string,checked_at:string,evidence_hash:string} $check
     */
    private function blocker(array $check, string $dedupKey): Notification
    {
        return Notification::create(
            type: 'readiness.blocker',
            category: NotificationCategory::READINESS,
            severity: NotificationSeverity::WARNING,
            sourceModule: 'operations',
            titleKey: 'notifications.readiness.blocker.title',
            messageKey: 'notifications.readiness.blocker.body',
            rendered: [
                'title' => sprintf(
                    /* translators: %s: the readiness check label. */
                    __('Readiness blocker: %s', 'corex'),
                    $check['label'],
                ),
                'body' => $check['summary'],
            ],
            dedupKey: $dedupKey,
            recipient: NotificationRecipient::forAbility(CorexAbility::MANAGE_OPERATIONS),
            occurredAt: new DateTimeImmutable('now'),
            sourceType: 'readiness_check',
            sourceId: $check['key'],
            action: $this->resolutionAction($check['resolution_url']),
        );
    }

    private function resolutionAction(string $url): ?NotificationAction
    {
        return $url === '' ? null : NotificationAction::to('notifications.readiness.resolve', $url);
    }
}
