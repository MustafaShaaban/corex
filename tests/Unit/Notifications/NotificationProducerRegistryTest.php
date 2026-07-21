<?php

/**
 * Unit tests for the notification producer registry (spec 072 US4: FR-014).
 *
 * The registry is the dependency-aware boot seam: it registers a producer only when its module is
 * available, and never registers the same producer twice however often boot runs.
 *
 * @package Corex\Tests\Unit\Notifications
 */

declare(strict_types=1);

use Corex\Notifications\NotificationProducer;
use Corex\Notifications\NotificationProducerRegistry;

/** A test double recording whether its register() was invoked. */
function fakeProducer(string $key, bool $available, callable $onRegister): NotificationProducer
{
    return new class ($key, $available, Closure::fromCallable($onRegister)) implements NotificationProducer {
        public function __construct(
            private string $producerKey,
            private bool $available,
            private Closure $onRegister,
        ) {
        }

        public function key(): string
        {
            return $this->producerKey;
        }

        public function isAvailable(): bool
        {
            return $this->available;
        }

        public function register(): void
        {
            ($this->onRegister)();
        }
    };
}

it('registers only producers whose module is available (FR-014)', function () {
    $registered = [];
    $registry = new NotificationProducerRegistry();
    $registry->add(fakeProducer('forms.submissions', true, function () use (&$registered): void {
        $registered[] = 'forms.submissions';
    }));
    $registry->add(fakeProducer('email.studio', false, function () use (&$registered): void {
        $registered[] = 'email.studio';   // absent module — must never fire
    }));

    $registry->register();

    expect($registered)->toBe(['forms.submissions'])
        ->and($registry->registeredKeys())->toBe(['forms.submissions']);
});

it('registers each available producer exactly once even if boot runs twice', function () {
    $registered = [];
    $registry = new NotificationProducerRegistry();
    $registry->add(fakeProducer('jobs', true, function () use (&$registered): void {
        $registered[] = 'jobs';
    }));

    $registry->register();
    $registry->register();

    expect($registered)->toBe(['jobs'])
        ->and($registry->registeredKeys())->toBe(['jobs']);
});
