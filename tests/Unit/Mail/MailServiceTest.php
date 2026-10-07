<?php

/**
 * Unit tests for the send orchestrator (spec US1+US2: FR-001, FR-006, FR-007, FR-011,
 * SC-002, SC-004, SC-005).
 *
 * Header injection and invalid recipients are blocked before delivery; a driver failure
 * is caught and logged. Real collaborators (fake driver + fake log at the boundary, a real
 * HeaderGuard); no internal mocks. The service never throws.
 *
 * @package Corex\Tests\Unit\Mail
 */

declare(strict_types=1);

use Corex\Email\Driver\MailDriver;
use Corex\Email\Log\EmailLogStore;
use Corex\Email\MailService;
use Corex\Email\Message\EmailMessage;
use Corex\Email\Security\HeaderGuard;
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

function mailService(MailDriver $driver, EmailLogStore $log): MailService
{
    return new MailService($driver, $log, new HeaderGuard(), new BootLogger(debug: false));
}

/**
 * @param list<string> $to
 */
function message(array $to = ['to@example.com'], string $subject = 'Hi'): EmailMessage
{
    return new EmailMessage($to, [], [], null, $subject, '<p>x</p>');
}

it('delivers a valid message and records it as sent', function () {
    $driver = fakeDriver();
    $log    = fakeLogStore();

    $result = mailService($driver, $log)->deliver(message());

    expect($result->isSent())->toBeTrue()
        ->and($driver->sent)->toHaveCount(1)
        ->and($log->records)->toBe(['sent']);
});

it('records failed when the driver returns false', function () {
    $driver = fakeDriver();
    $driver->result = false;
    $log = fakeLogStore();

    $result = mailService($driver, $log)->deliver(message());

    expect($result->status)->toBe('failed')
        ->and($log->records)->toBe(['failed']);
});

it('catches a throwing driver, records failed, and never throws', function () {
    $driver = fakeDriver();
    $driver->throw = true;
    $log = fakeLogStore();

    $result = mailService($driver, $log)->deliver(message());

    expect($result->status)->toBe('failed')
        ->and($log->records)->toBe(['failed']);
});

it('rejects a header-injected subject without sending', function () {
    $driver = fakeDriver();
    $log    = fakeLogStore();

    $result = mailService($driver, $log)->deliver(message(subject: "Hi\r\nBcc: victim@example.com"));

    expect($result->status)->toBe('rejected')
        ->and($driver->sent)->toBe([])     // nothing delivered
        ->and($log->records)->toBe(['rejected']);
});

it('drops invalid recipients but still delivers to the valid ones', function () {
    $driver = fakeDriver();
    $log    = fakeLogStore();

    $result = mailService($driver, $log)->deliver(message(['good@example.com', 'not-an-email']));

    expect($result->isSent())->toBeTrue()
        ->and($driver->sent[0]->to)->toBe(['good@example.com']); // invalid dropped
});

/*
 * What reaches the driver (#150).
 *
 * deliver() rebuilds the message, because it has to drop the invalid recipients. Up to v0.43.3 it
 * rebuilt it from the seven fields the class had when the service was written, so the sender
 * (spec 085) and the attachments (spec 081) were each threaded from the request to the driver and
 * lost here, in the one place every send through the Mailer seam passes. Each had tests on both
 * sides of this method and none across it.
 */
it('hands the driver every field the message carried, the sender and the attachments among them', function () {
    $fields = [
        'to'          => ['to@example.com'],
        'cc'          => ['cc@example.com'],
        'bcc'         => ['bcc@example.com'],
        'replyTo'     => 'reply@example.com',
        'subject'     => 'Hi',
        'body'        => '<p>x</p>',
        'headers'     => ['X-Campaign' => 'spring'],
        'from'        => 'info@example.com',
        'attachments' => [42, 43],
    ];
    $parameters = array_map(
        static fn (ReflectionParameter $parameter): string => $parameter->getName(),
        (new ReflectionClass(EmailMessage::class))->getConstructor()->getParameters(),
    );
    $driver  = fakeDriver();
    $message = new EmailMessage(...$fields);

    mailService($driver, fakeLogStore())->deliver($message);

    // A field added to the message and not to this list fails here first, so the next one cannot
    // be dropped the way these two were.
    expect(array_keys($fields))->toBe($parameters)
        ->and($driver->sent[0]->from)->toBe('info@example.com')
        ->and($driver->sent[0]->attachments)->toBe([42, 43])
        ->and($driver->sent[0])->toEqual($message);
});

it('rejects a header-injected sender without sending', function () {
    $driver = fakeDriver();
    $log    = fakeLogStore();

    $result = mailService($driver, $log)->deliver(new EmailMessage(
        ['to@example.com'],
        [],
        [],
        null,
        'Hi',
        '<p>x</p>',
        from: "info@example.com\r\nBcc: victim@example.com",
    ));

    expect($result->status)->toBe('rejected')
        ->and($driver->sent)->toBe([])
        ->and($log->records)->toBe(['rejected']);
});

it('fails without sending when every recipient is invalid', function () {
    $driver = fakeDriver();
    $log    = fakeLogStore();

    $result = mailService($driver, $log)->deliver(message(['nope', 'also-bad']));

    expect($result->status)->toBe('failed')
        ->and($driver->sent)->toBe([])
        ->and($log->records)->toBe(['failed']);
});
