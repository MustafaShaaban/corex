<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Foundation;

defined('ABSPATH') || exit;

use Corex\Container\ContainerInterface;

/**
 * The single extension seam for Corex modules and add-ons.
 *
 * A provider binds services in `register()` (no side effects) and wires behavior
 * in `boot()`, which runs only after every provider has registered — so a provider
 * may depend on another's bindings during boot.
 */
abstract class ServiceProvider
{
    public function __construct(protected readonly ContainerInterface $container)
    {
    }

    /**
     * Bind services into the container. MUST NOT trigger side effects.
     */
    abstract public function register(): void;

    /**
     * Run after all providers are registered. Override to wire hooks or read config.
     */
    public function boot(): void
    {
    }
}
