<?php

/**
 * Unit tests for the send orchestrator (spec US1: FR-001, FR-011, SC-005).
 *
 * Delivery goes through the driver and is recorded once; a driver failure is caught and
 * logged as `failed` — the service never throws. Real collaborators (fake driver + fake
 * log store at the boundary); no internal mocks.
 *
 * @package Corex\Tests\Unit\Mail
 */

declare(strict_types=1);

use Corex\Email\Driver\MailDriver;
use Corex\Email\Log\EmailLogStore;
use Corex\Email\MailService;
use Corex\Email\Message\EmailMessage;
use Corex\Support\BootLogger;

function fakeDriver(): MailDriver
{
    return new class implements MailDriver {
        public array $sent = [];
        public bool $result = true;
        public bool $throw = false;

        public function send(EmailMessage $message): bool
        {
            if ($this->throw) {
                throw new RuntimeException('smtp down');
            }
            $this->sent[] = $message;

            return $this->result;
        }
    };
}

function fakeLogStore(): EmailLogStore
{
    return new class implements EmailLogStore {
        public array $records = [];

        public function record(string $status, EmailMessage $message): ?int
        {
            $this->records[] = $status;

            return count($this->records);
        }
    };
}

function message(): EmailMessage
{
    return new EmailMessage(['to@example.com'], [], [], null, 'Hi', '<p>x</p>');
}

it('delivers a message and records it as sent', function () {
    $driver = fakeDriver();
    $log    = fakeLogStore();

    $result = (new MailService($driver, $log, new BootLogger(debug: false)))->deliver(message());

    expect($result->isSent())->toBeTrue()
        ->and($driver->sent)->toHaveCount(1)
        ->and($log->records)->toBe(['sent']);
});

it('records failed when the driver returns false', function () {
    $driver = fakeDriver();
    $driver->result = false;
    $log = fakeLogStore();

    $result = (new MailService($driver, $log, new BootLogger(debug: false)))->deliver(message());

    expect($result->isSent())->toBeFalse()
        ->and($result->status)->toBe('failed')
        ->and($log->records)->toBe(['failed']);
});

it('catches a throwing driver, records failed, and never throws', function () {
    $driver = fakeDriver();
    $driver->throw = true;
    $log = fakeLogStore();

    $result = (new MailService($driver, $log, new BootLogger(debug: false)))->deliver(message());

    expect($result->isSent())->toBeFalse()
        ->and($result->status)->toBe('failed')
        ->and($log->records)->toBe(['failed']);
});
