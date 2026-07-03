<?php

/**
 * Unit tests for shared mutation outcomes and replay-safe confirmations (spec 068: FR-004, FR-006).
 *
 * @package Corex\Tests\Unit\Operations
 */

declare(strict_types=1);

use Corex\Operations\Confirmation;
use Corex\Operations\OperationResult;

it('represents a completed operation with bounded evidence', function () {
    $started  = new DateTimeImmutable('2026-07-03T10:00:00+00:00');
    $finished = new DateTimeImmutable('2026-07-03T10:00:02+00:00');
    $result   = new OperationResult(
        operationId: '2f09598f-f453-47a1-bf0e-9fcf488cdf1d',
        state: OperationResult::STATE_COMPLETED,
        message: 'Two records were archived.',
        errors: [],
        affectedIds: [12, 14],
        startedAt: $started,
        finishedAt: $finished,
        auditEventId: 91,
    );

    expect($result->succeeded())->toBeTrue()
        ->and($result->terminal())->toBeTrue()
        ->and($result->toArray())->toMatchArray([
            'state'          => 'completed',
            'affected_ids'   => [12, 14],
            'audit_event_id' => 91,
        ])
        ->and(fn () => $result->state = OperationResult::STATE_FAILED)->toThrow(Error::class);
});

it('distinguishes accepted, partial, failed, cancelled, and blocked states', function (string $state, bool $terminal, bool $succeeded) {
    $result = new OperationResult(
        operationId: '7bb7b752-5ae8-47b8-b09a-b5cc26c3bfd9',
        state: $state,
        message: 'State check.',
        errors: [],
        affectedIds: [],
        startedAt: new DateTimeImmutable('2026-07-03T10:00:00+00:00'),
    );

    expect($result->terminal())->toBe($terminal)
        ->and($result->succeeded())->toBe($succeeded);
})->with([
    'accepted'  => ['accepted', false, false],
    'partial'   => ['partial', true, false],
    'failed'    => ['failed', true, false],
    'cancelled' => ['cancelled', true, false],
    'blocked'   => ['blocked', true, false],
]);

it('rejects an invalid result state and inverted timestamps', function () {
    $base = [
        'operationId' => '64d15a02-8cf2-4e47-9ea3-fbbbc22ce22c',
        'state'       => OperationResult::STATE_COMPLETED,
        'message'     => 'Done.',
        'errors'      => [],
        'affectedIds' => [],
        'startedAt'   => new DateTimeImmutable('2026-07-03T10:00:00+00:00'),
        'finishedAt'  => new DateTimeImmutable('2026-07-03T10:00:01+00:00'),
    ];

    expect(fn () => new OperationResult(...[...$base, 'state' => 'unknown']))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => new OperationResult(...[
            ...$base,
            'finishedAt' => new DateTimeImmutable('2026-07-03T09:59:59+00:00'),
        ]))->toThrow(InvalidArgumentException::class);
});

it('verifies a confirmation against operation target actor phrase and expiry', function () {
    $now          = new DateTimeImmutable('2026-07-03T10:00:00+00:00');
    $confirmation = new Confirmation(
        operationKind: 'operations.production.enable',
        targetHash: hash('sha256', 'site:1|production'),
        actorId: 8,
        expiresAt: $now->modify('+5 minutes'),
        requiredPhrase: 'PRODUCTION',
    );

    expect($confirmation->verify(
        operationKind: 'operations.production.enable',
        targetHash: hash('sha256', 'site:1|production'),
        actorId: 8,
        phrase: 'PRODUCTION',
        now: $now,
    ))->toBeTrue()
        ->and($confirmation->verify(
            operationKind: 'operations.production.enable',
            targetHash: hash('sha256', 'site:1|production'),
            actorId: 9,
            phrase: 'PRODUCTION',
            now: $now,
        ))->toBeFalse();
});

it('marks a confirmation used without mutating the original and prevents replay', function () {
    $now      = new DateTimeImmutable('2026-07-03T10:00:00+00:00');
    $original = new Confirmation(
        operationKind: 'data.records.delete',
        targetHash: hash('sha256', 'records:12,14'),
        actorId: 8,
        expiresAt: $now->modify('+5 minutes'),
    );
    $used = $original->use($now);

    expect($original->usedAt)->toBeNull()
        ->and($used->usedAt)->toBe($now)
        ->and($used->verify(
            operationKind: 'data.records.delete',
            targetHash: hash('sha256', 'records:12,14'),
            actorId: 8,
            phrase: null,
            now: $now,
        ))->toBeFalse()
        ->and(fn () => $used->use($now))->toThrow(DomainException::class);
});

it('rejects an expired confirmation and malformed target hash', function () {
    $now = new DateTimeImmutable('2026-07-03T10:00:00+00:00');

    $expired = new Confirmation(
        operationKind: 'data.export',
        targetHash: hash('sha256', 'export:submissions'),
        actorId: 8,
        expiresAt: $now->modify('-1 second'),
    );

    expect($expired->verify(
        operationKind: 'data.export',
        targetHash: hash('sha256', 'export:submissions'),
        actorId: 8,
        phrase: null,
        now: $now,
    ))->toBeFalse()
        ->and(fn () => new Confirmation(
            operationKind: 'data.export',
            targetHash: 'not-a-hash',
            actorId: 8,
            expiresAt: $now->modify('+5 minutes'),
        ))->toThrow(InvalidArgumentException::class);
});
