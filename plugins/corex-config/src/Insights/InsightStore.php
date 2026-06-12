<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Insights;

defined('ABSPATH') || exit;

/**
 * The pure cache + history for insight results. It transforms the decoded option state
 * (`provider => {latest, history[]}`) — adding a run, reading the latest, or mapping every
 * provider to its latest for the dashboard. The controller owns the actual option read/write, so
 * this stays headless-testable and free of WordPress.
 */
final class InsightStore
{
    private const DEFAULT_HISTORY = 5;

    /**
     * Record a result, returning the new state. History is newest-first and bounded.
     *
     * @param array<string,mixed> $state
     *
     * @return array<string,mixed>
     */
    public function put(array $state, InsightResult $result, int $historyLimit = self::DEFAULT_HISTORY): array
    {
        $entry   = $result->toArray();
        $history = $this->history($state, $result->providerId);

        array_unshift($history, $entry);

        $state[$result->providerId] = [
            'latest'  => $entry,
            'history' => array_slice($history, 0, max(1, $historyLimit)),
        ];

        return $state;
    }

    /**
     * @param array<string,mixed> $state
     *
     * @return array<string,mixed>|null
     */
    public function latest(array $state, string $providerId): ?array
    {
        $entry = $state[$providerId]['latest'] ?? null;

        return is_array($entry) ? $entry : null;
    }

    /**
     * @param array<string,mixed> $state
     *
     * @return list<array<string,mixed>>
     */
    public function history(array $state, string $providerId): array
    {
        $history = $state[$providerId]['history'] ?? [];

        return is_array($history) ? array_values($history) : [];
    }

    /**
     * @param array<string,mixed> $state
     *
     * @return array<string,array<string,mixed>>
     */
    public function all(array $state): array
    {
        $all = [];

        foreach ($state as $providerId => $_entry) {
            $latest = $this->latest($state, (string) $providerId);

            if ($latest !== null) {
                $all[$providerId] = $latest;
            }
        }

        return $all;
    }
}
