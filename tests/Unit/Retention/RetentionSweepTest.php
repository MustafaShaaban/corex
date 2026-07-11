<?php

/**
 * Unit tests for the unified retention sweep (spec 068: T202). No WordPress. Contract: each store
 * is pruned against its own window relative to now; a zero window means keep-forever (skipped);
 * preview never deletes; apply reports per-store and total removed.
 *
 * @package Corex\Tests\Unit\Retention
 */

declare(strict_types=1);

use Corex\Config\Retention\PrunableStore;
use Corex\Config\Retention\RetentionSweep;

/**
 * A recording in-memory prunable store.
 */
function fakeStore(string $key, int $days, int $removes): PrunableStore
{
    return new class ($key, $days, $removes) implements PrunableStore {
        public ?string $prunedCutoff = null;

        public function __construct(
            private readonly string $storeKey,
            private readonly int $days,
            private readonly int $removes,
        ) {
        }

        public function key(): string
        {
            return $this->storeKey;
        }

        public function label(): string
        {
            return ucfirst($this->storeKey);
        }

        public function retentionDays(): int
        {
            return $this->days;
        }

        public function pruneOlderThan(DateTimeImmutable $cutoff): int
        {
            $this->prunedCutoff = $cutoff->format('Y-m-d');

            return $this->removes;
        }
    };
}

it('previews every store with its window and enabled flag, deleting nothing', function () {
    $keep    = fakeStore('activity', 90, 5);
    $forever = fakeStore('consent', 0, 5);

    $plan = (new RetentionSweep([$keep, $forever]))->preview();

    expect($plan)->toBe([
        ['key' => 'activity', 'label' => 'Activity', 'retentionDays' => 90, 'enabled' => true],
        ['key' => 'consent', 'label' => 'Consent', 'retentionDays' => 0, 'enabled' => false],
    ]);
    // Preview must not prune.
    expect($keep->prunedCutoff)->toBeNull();
});

it('prunes each enabled store at its own cutoff and totals the removals', function () {
    $activity = fakeStore('activity', 90, 12);
    $email    = fakeStore('email', 30, 3);
    $consent  = fakeStore('consent', 0, 99); // keep forever → skipped

    $result = (new RetentionSweep([$activity, $email, $consent]))->apply(new DateTimeImmutable('2026-07-09'));

    expect($result['total'])->toBe(15)
        ->and($result['stores'])->toBe([
            ['key' => 'activity', 'label' => 'Activity', 'removed' => 12],
            ['key' => 'email', 'label' => 'Email', 'removed' => 3],
        ])
        // Each store pruned at now minus its own window.
        ->and($activity->prunedCutoff)->toBe('2026-04-10')
        ->and($email->prunedCutoff)->toBe('2026-06-09')
        // The keep-forever store was never pruned.
        ->and($consent->prunedCutoff)->toBeNull();
});
