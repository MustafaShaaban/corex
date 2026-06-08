<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Foundation;

defined('ABSPATH') || exit;

use Corex\Container\Container;
use Corex\Container\ContainerInterface;
use Corex\Support\BootLogger;

/**
 * The composition root: builds the container and runs the provider lifecycle once.
 *
 * As the composition root, this is the one place permitted to construct framework
 * primitives directly — the container cannot be injected into the object that
 * creates it. Everything beyond the root is resolved through the container.
 */
final class Application
{
    private readonly Container $container;

    private bool $booted = false;

    /**
     * @param list<class-string<ServiceProvider>> $providers
     */
    public function __construct(
        private readonly bool $debug = false,
        private readonly array $providers = [],
    ) {
        $this->container = new Container();
    }

    public function container(): ContainerInterface
    {
        return $this->container;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        $logger = new BootLogger($this->debug);
        $this->container->instance(ContainerInterface::class, $this->container);
        $this->container->instance(BootLogger::class, $logger);

        $repository = new ProviderRepository($this->container, $logger);
        $repository->load($this->providers);
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }
}
