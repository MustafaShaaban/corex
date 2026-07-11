<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Retention;

use DateTimeImmutable;

defined('ABSPATH') || exit;

/**
 * The unified retention sweep (spec 068 T202): given the registered {@see PrunableStore}s (activity,
 * captured email, consent, export logs, …), it previews or applies pruning for each. Each store's
 * cutoff is derived from its own configured window; a window of 0 means keep-forever and the store
 * is skipped, never pruned. `preview()` reports the plan without deleting; `apply()` performs the
 * deletions and reports what was removed. The store list is injected, so this is unit-testable with
 * in-memory fakes.
 */
final class RetentionSweep
{
    /**
     * @param list<PrunableStore> $stores
     */
    public function __construct(private readonly array $stores)
    {
    }

    /**
     * A non-destructive plan: for each store, its label, window, and whether it is enabled (window > 0).
     *
     * @return list<array{key:string,label:string,retentionDays:int,enabled:bool}>
     */
    public function preview(): array
    {
        $plan = [];

        foreach ($this->stores as $store) {
            $days = max(0, $store->retentionDays());
            $plan[] = [
                'key'           => $store->key(),
                'label'         => $store->label(),
                'retentionDays' => $days,
                'enabled'       => $days > 0,
            ];
        }

        return $plan;
    }

    /**
     * Prune every enabled store older than its own window relative to $now, returning the removed
     * count per store and the total. Stores with a zero window are skipped (never pruned).
     *
     * @return array{stores:list<array{key:string,label:string,removed:int}>,total:int}
     */
    public function apply(DateTimeImmutable $now): array
    {
        $results = [];
        $total   = 0;

        foreach ($this->stores as $store) {
            $days = max(0, $store->retentionDays());
            if ($days === 0) {
                continue;
            }

            $removed = max(0, $store->pruneOlderThan($now->modify('-' . $days . ' days')));
            $total  += $removed;

            $results[] = ['key' => $store->key(), 'label' => $store->label(), 'removed' => $removed];
        }

        return ['stores' => $results, 'total' => $total];
    }
}
