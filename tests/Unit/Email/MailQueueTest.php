<?php

/**
 * Unit tests for the mail queue: the queue/immediate decision (never a hard dependency
 * on Action Scheduler), the QueuedMailer decorator routing, and the MailRequest
 * (de)serialization round-trip.
 *
 * @package Corex\Tests\Unit\Email
 */

declare(strict_types=1);

use Corex\Email\Queue\ActionSchedulerDispatcher;
use Corex\Email\Queue\MailQueueDispatcher;
use Corex\Email\Queue\MailQueueGate;
use Corex\Email\Queue\QueuedMailer;
use Corex\Mail\Mailer;
use Corex\Mail\MailRequest;
use Corex\Support\Config\ConfigInterface;
use Corex\Support\Config\FeatureFlags;

function queueFlags(bool $on): FeatureFlags
{
    $config = new class($on) implements ConfigInterface {
        public function __construct(private bool $on)
        {
        }

        public function get(string $key, mixed $default = null): mixed
        {
            return $key === 'features.mail_queue' ? $this->on : $default;
        }

        public function has(string $key): bool
        {
            return $key === 'features.mail_queue';
        }
    };

    return new FeatureFlags($config);
}

/** A spy Mailer + dispatcher to observe routing without WordPress. */
function spyMailer(): Mailer
{
    return new class implements Mailer {
        public int $sent = 0;

        public function send(MailRequest $request): void
        {
            $this->sent++;
        }
    };
}

function spyDispatcher(bool $available): MailQueueDispatcher
{
    return new class($available) implements MailQueueDispatcher {
        public int $enqueued = 0;

        public function __construct(private bool $available)
        {
        }

        public function available(): bool
        {
            return $this->available;
        }

        public function enqueue(MailRequest $request): void
        {
            $this->enqueued++;
        }
    };
}

it('queues only when the backend is available and the flag is on', function () {
    $on = new MailQueueGate(queueFlags(true));
    $off = new MailQueueGate(queueFlags(false));

    expect($on->shouldQueue(true))->toBeTrue();
    expect($on->shouldQueue(false))->toBeFalse();   // no backend → inline (never a hard dep)
    expect($off->shouldQueue(true))->toBeFalse();    // flag off → inline
});

it('enqueues instead of sending when the gate says queue', function () {
    $inner = spyMailer();
    $dispatcher = spyDispatcher(available: true);

    $mailer = new QueuedMailer($inner, new MailQueueGate(queueFlags(true)), $dispatcher);
    $mailer->send(new MailRequest(['a@b.test']));

    expect($dispatcher->enqueued)->toBe(1);
    expect($inner->sent)->toBe(0);
});

it('sends inline when the gate says do not queue', function () {
    $inner = spyMailer();
    $dispatcher = spyDispatcher(available: false); // no backend

    $mailer = new QueuedMailer($inner, new MailQueueGate(queueFlags(true)), $dispatcher);
    $mailer->send(new MailRequest(['a@b.test']));

    expect($inner->sent)->toBe(1);
    expect($dispatcher->enqueued)->toBe(0);
});

it('round-trips a MailRequest through the queue payload', function () {
    $request = new MailRequest(
        to: ['x@y.test', 'p@q.test'],
        templateName: 'welcome',
        context: ['name' => 'Sam', 'n' => 3],
        subject: 'Hi',
        body: null,
        replyTo: 'noreply@y.test',
    );

    $restored = ActionSchedulerDispatcher::fromArray(ActionSchedulerDispatcher::toArray($request));

    expect($restored->to)->toBe(['x@y.test', 'p@q.test']);
    expect($restored->templateName)->toBe('welcome');
    expect($restored->context)->toBe(['name' => 'Sam', 'n' => 3]);
    expect($restored->subject)->toBe('Hi');
    expect($restored->body)->toBeNull();
    expect($restored->replyTo)->toBe('noreply@y.test');
});
