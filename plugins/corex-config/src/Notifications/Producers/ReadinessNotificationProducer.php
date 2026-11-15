<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications\Producers;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;
use Corex\Config\Operations\ReadinessEvaluatedEvent;
use Corex\Config\Operations\ReadinessSnapshot;
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
 * a check that becomes blocking raises one notification keyed by the check, and a raised check that
 * passes again resolves it (FR-010).
 *
 * Only a change of state is acted on (spec 118, US-OVR-01). Every screen that shows the launch
 * checklist evaluates it, and publishing on each evaluation added an occurrence to the same notice
 * each time a page was opened. {@see RaisedReadinessChecks} remembers which checks are raised; a
 * check that cannot be read keeps whatever it had. A raised check evaluated again is only reworded
 * in the evaluating request's language, which writes nothing when the words are already the same. On the first evaluation after the upgrade nothing
 * is remembered yet, so blockers are raised once and every other check is resolved, as before.
 *
 * Dependency-aware via class_exists (FR-014). Notifications go to the operations managers.
 */
final class ReadinessNotificationProducer implements NotificationProducer
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ListenerProvider $listeners,
        private readonly RaisedReadinessChecks $raised = new RaisedReadinessChecks(),
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
        $known  = $this->raised->known();
        $before = $this->raised->keys();
        $raised = $before;

        foreach ($event->snapshot->checks() as $check) {
            $dedupKey = 'readiness.blocker:' . $check['key'];
            $wasRaised = in_array($check['key'], $before, true);

            if ($check['state'] === ReadinessSnapshot::BLOCKING) {
                $wasRaised
                    ? $this->notifications->reword($this->blocker($check, $dedupKey))
                    : $this->notifications->publish($this->blocker($check, $dedupKey));
                $raised[] = $check['key'];

                continue;
            }

            if ($check['state'] !== ReadinessSnapshot::UNAVAILABLE && ($wasRaised || ! $known)) {
                $this->notifications->resolve($dedupKey, __('This readiness check now passes.', 'corex'));
                $raised = array_values(array_diff($raised, [$check['key']]));
            }
        }

        $raised = array_values(array_unique($raised));
        if (! $known || $raised !== $before) {
            $this->raised->remember($raised);
        }
    }

    /**
     * @param array{key:string,group:string,label:string,state:string,summary:string,resolution_url:string,checked_at:string,evidence_hash:string} $check
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
            // A check's label states the condition that passes — "File editing disabled", "No
            // default "admin" account" — so the title says that condition is not met. "<label> is
            // not ready for production" read as the opposite of the fault for five of the six
            // checks. What to do about it is the body.
            rendered: [
                'title' => sprintf(
                    /* translators: %s: the readiness check label, which states the passing condition, e.g. "File editing disabled". */
                    __('Readiness check not met: %s', 'corex'),
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
        // The label is passed as a string, not only as a key: nothing resolves keys, so this action
        // rendered as a generic "Open" from the day it was written (spec 087, FR-010).
        return $url === '' ? null : NotificationAction::to(
            'notifications.readiness.resolve',
            $url,
            CorexAbility::MANAGE_OPERATIONS,
            __('Fix this check', 'corex'),
        );
    }
}
