<?php

/**
 * Unit tests for notification retention (spec 072 US5: FR-022).
 *
 * The notification store as a {@see PrunableStore}: it describes its window and delegates pruning to
 * the repository, so the shared retention sweep can clean it alongside every other store.
 *
 * @package Corex\Tests\Unit\Notifications
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Retention\NotificationRetention;
use Corex\Notifications\NotificationRepository;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

it('describes its window and delegates pruning to the repository', function () {
    $cutoff = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $repository = Mockery::mock(NotificationRepository::class);
    $repository->shouldReceive('pruneOlderThan')->once()->with($cutoff)->andReturn(7);

    $store = new NotificationRetention($repository);

    expect($store->key())->toBe('notifications')
        ->and($store->label())->toBeString()->not->toBe('')
        ->and($store->retentionDays())->toBeGreaterThan(0)
        ->and($store->pruneOlderThan($cutoff))->toBe(7);
});
