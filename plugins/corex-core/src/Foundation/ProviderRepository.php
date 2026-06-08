<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Foundation;

defined('ABSPATH') || exit;

use Corex\Container\ContainerInterface;
use Corex\Support\BootLogger;
use Throwable;

/**
 * Runs the two-pass provider lifecycle: register every provider, then boot every
 * provider. De-duplicates by class-string and isolates failures so one broken
 * provider can never abort boot (spec FR-002, FR-023).
 */
final class ProviderRepository
{
    /**
     * @var array<class-string, true>
     */
    private array $loaded = [];

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly BootLogger $logger,
    ) {
    }

    /**
     * @param list<class-string<ServiceProvider>> $providerClasses
     */
    public function load(array $providerClasses): void
    {
        $registered = [];

        foreach ($providerClasses as $class) {
            if (isset($this->loaded[$class])) {
                continue;
            }

            $this->loaded[$class] = true;

            $provider = $this->registerProvider($class);

            if ($provider !== null) {
                $registered[] = $provider;
            }
        }

        foreach ($registered as $provider) {
            $this->bootProvider($provider);
        }
    }

    /**
     * @param class-string<ServiceProvider> $class
     */
    private function registerProvider(string $class): ?ServiceProvider
    {
        try {
            $provider = $this->container->make($class);
            $provider->register();

            return $provider;
        } catch (Throwable $e) {
            // Resilient boot: a broken provider is logged, never fatal (FR-023).
            $this->logger->error(sprintf('Provider [%s] failed to register: %s', $class, $e->getMessage()));

            return null;
        }
    }

    private function bootProvider(ServiceProvider $provider): void
    {
        try {
            $provider->boot();
        } catch (Throwable $e) {
            // Resilient boot: a broken provider is logged, never fatal (FR-023).
            $this->logger->error(sprintf('Provider [%s] failed to boot: %s', $provider::class, $e->getMessage()));
        }
    }
}
