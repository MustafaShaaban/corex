<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Insights;

defined('ABSPATH') || exit;

/**
 * Consolidates the Insights run/history/recommendation workflow over the pure {@see InsightStore}
 * and the {@see InsightRegistry}. It runs a real provider against a URL, records the result in the
 * bounded history, and aggregates the plain-language recommendations from every provider's latest
 * stored result. It takes and returns the stored state array (never touches WordPress options), so
 * it stays unit-testable; the controller owns the option read/write.
 */
final class InsightRunService
{
    public function __construct(
        private readonly InsightRegistry $registry,
        private readonly InsightStore $store,
    ) {
    }

    /**
     * Run a provider against $url and record it. Returns the updated state and the result payload,
     * or null when the provider id is unknown (never a fabricated result).
     *
     * @param array<string,mixed> $state
     *
     * @return array{state:array<string,mixed>,result:array<string,mixed>}|null
     */
    public function run(string $providerId, string $url, array $state): ?array
    {
        $provider = $this->registry->find($providerId);

        if ($provider === null) {
            return null;
        }

        $result = $provider->run($url);

        return [
            'state'  => $this->store->put($state, $result),
            'result' => $result->toArray(),
        ];
    }

    /**
     * @param array<string,mixed> $state
     *
     * @return list<array<string,mixed>>
     */
    public function history(array $state, string $providerId): array
    {
        return $this->store->history($state, $providerId);
    }

    /**
     * @param array<string,mixed> $state
     *
     * @return array<string,mixed>|null
     */
    public function latest(array $state, string $providerId): ?array
    {
        return $this->store->latest($state, $providerId);
    }

    /**
     * The actionable recommendations from every provider's latest stored result. Providers with no
     * run yet, or a clean result with no recommendations, are omitted — never a fabricated action.
     *
     * @param array<string,mixed> $state
     *
     * @return list<array{provider:string,label:string,grade:string,recommendations:list<string>}>
     */
    public function recommendations(array $state): array
    {
        $out = [];

        foreach ($this->registry->all() as $provider) {
            $latest = $this->store->latest($state, $provider->id());

            if ($latest === null) {
                continue;
            }

            $recommendations = is_array($latest['recommendations'] ?? null) ? $latest['recommendations'] : [];

            if ($recommendations === []) {
                continue;
            }

            $out[] = [
                'provider'        => $provider->id(),
                'label'           => (string) ($latest['label'] ?? $provider->label()),
                'grade'           => (string) ($latest['grade'] ?? ''),
                'recommendations' => array_values(array_map('strval', $recommendations)),
            ];
        }

        return $out;
    }
}
