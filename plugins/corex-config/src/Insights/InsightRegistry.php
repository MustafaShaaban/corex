<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Insights;

defined('ABSPATH') || exit;

/**
 * The set of configured insight providers, keyed by id. The service provider seeds it (Performance
 * + Readiness); the controller looks providers up to run them and the screen lists them.
 */
final class InsightRegistry
{
    /** @var array<string,InsightProvider> */
    private array $providers = [];

    public function register(InsightProvider $provider): void
    {
        $this->providers[$provider->id()] = $provider;
    }

    public function find(string $id): ?InsightProvider
    {
        return $this->providers[$id] ?? null;
    }

    /**
     * @return list<InsightProvider>
     */
    public function all(): array
    {
        return array_values($this->providers);
    }
}
