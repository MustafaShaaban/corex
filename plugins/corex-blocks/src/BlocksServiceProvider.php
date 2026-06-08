<?php

/**
 * @package Corex\Blocks
 */

declare(strict_types=1);

namespace Corex\Blocks;

defined('ABSPATH') || exit;

use Corex\Blocks\Connectors\ConnectorRegistry;
use Corex\Container\ContainerInterface;
use Corex\Foundation\ServiceProvider;
use Corex\Support\BootLogger;

/**
 * Registers the block engine and, on `init`, discovers and registers every block
 * under src/blocks (spec FR-014). Connectors are registered by the consuming
 * module via the bound ConnectorRegistry.
 */
final class BlocksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(
            BlockMap::class,
            static fn (ContainerInterface $c): BlockMap => new BlockMap($c->make(BootLogger::class)),
        );

        $this->container->singleton(
            DynamicBlockRegistrar::class,
            static fn (ContainerInterface $c): DynamicBlockRegistrar => new DynamicBlockRegistrar($c, $c->make(BootLogger::class)),
        );

        $this->container->singleton(ConnectorRegistry::class);
    }

    public function boot(): void
    {
        add_action('init', function (): void {
            $registrar = $this->container->make(DynamicBlockRegistrar::class);

            foreach ($this->container->make(BlockMap::class)->discover(__DIR__ . '/blocks') as $block) {
                $registrar->register($block);
            }
        });
    }
}
