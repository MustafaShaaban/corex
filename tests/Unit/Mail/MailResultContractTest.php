<?php

/**
 * Unit tests for backward-compatible result-bearing mail contracts (spec 068: FR-009, FR-111–FR-122).
 *
 * @package Corex\Tests\Unit\Mail
 */

declare(strict_types=1);

use Corex\Mail\MailRequest;
use Corex\Mail\MailResult;

it('gives every mail request a stable attempt correlation id without breaking old arguments', function () {
    $request = new MailRequest(['person@example.com'], subject: 'Hello', body: '<p>Hello</p>');

    expect($request->requestId)->toMatch('/^[0-9a-f-]{36}$/')
        ->and($request->parentAttemptId)->toBeNull()
        ->and($request->to)->toBe(['person@example.com'])
        ->and($request->subject)->toBe('Hello');
});
it('preserves explicit correlation and retry lineage', function () {
    $request = new MailRequest(
        to: ['person@example.com'],
        templateName: 'welcome',
        requestId: '2f09598f-f453-47a1-bf0e-9fcf488cdf1d',
        parentAttemptId: '64d15a02-8cf2-4e47-9ea3-fbbbc22ce22c',
    );

    expect($request->requestId)->toBe('2f09598f-f453-47a1-bf0e-9fcf488cdf1d')
        ->and($request->parentAttemptId)->toBe('64d15a02-8cf2-4e47-9ea3-fbbbc22ce22c');
});

it('represents sent captured queued failed rejected and legacy-accepted outcomes truthfully', function (string $state, bool $successful, bool $terminal) {
    $result = new MailResult(
        attemptId: 'f6773ddc-2d63-40cc-b408-35c0a81c084b',
        requestId: '2f09598f-f453-47a1-bf0e-9fcf488cdf1d',
        state: $state,
        provider: 'corex-mail',
        message: 'Result.',
        occurredAt: new DateTimeImmutable('2026-07-03T10:00:00+00:00'),
        retryable: $state === 'failed',
        logId: 41,
    );

    expect($result->successful())->toBe($successful)
        ->and($result->terminal())->toBe($terminal)
        ->and($result->toArray())->not->toHaveKeys(['to', 'subject', 'body']);
})->with([
    'sent'     => ['sent', true, true],
    'captured' => ['captured', true, true],
    'queued'   => ['queued', true, false],
    'failed'   => ['failed', false, true],
    'rejected' => ['rejected', false, true],
    'accepted' => ['accepted', true, false],
]);

it('rejects unknown states malformed ids and invalid log ids', function () {
    $base = [
        'attemptId' => 'f6773ddc-2d63-40cc-b408-35c0a81c084b',
        'requestId' => '2f09598f-f453-47a1-bf0e-9fcf488cdf1d',
        'state' => MailResult::STATE_SENT,
        'provider' => 'corex-mail',
        'message' => 'Sent.',
        'occurredAt' => new DateTimeImmutable('2026-07-03T10:00:00+00:00'),
        'retryable' => false,
    ];

    expect(fn () => new MailResult(...[...$base, 'state' => 'maybe']))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => new MailResult(...[...$base, 'attemptId' => 'bad']))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => new MailResult(...[...$base, 'logId' => 0]))
        ->toThrow(InvalidArgumentException::class);
});
