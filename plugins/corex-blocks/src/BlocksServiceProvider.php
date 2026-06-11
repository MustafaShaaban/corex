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
        add_filter('block_categories_all', [$this, 'registerBlockCategory']);

        add_action('init', function (): void {
            $registrar = $this->container->make(DynamicBlockRegistrar::class);
            $built = dirname(__DIR__) . '/build/blocks';
            $blocksDir = is_dir($built) ? $built : __DIR__ . '/blocks';

            foreach ($this->container->make(BlockMap::class)->discover($blocksDir) as $block) {
                $registrar->register($block);
            }
        });
    }

    /**
     * Add the "Corex" inserter category so every corex/* block groups together,
     * separate from core's Widgets/Design groups. Prepended once; idempotent.
     *
     * @param array<int, array{slug: string, title: string, icon?: string|null}> $categories
     * @return array<int, array{slug: string, title: string, icon?: string|null}>
     */
    public function registerBlockCategory(array $categories): array
    {
        foreach ($categories as $category) {
            if (($category['slug'] ?? '') === 'corex') {
                return $categories;
            }
        }

        array_unshift($categories, [
            'slug'  => 'corex',
            'title' => __('Corex', 'corex'),
            'icon'  => null,
        ]);

        return $categories;
    }
}
