<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockMap;
use Corex\Blocks\DynamicBlockRegistrar;
use Corex\Container\ContainerInterface;
use Corex\Foundation\ServiceProvider;
use Corex\Ui\Blocks\PostsProvider;
use Corex\Ui\Blocks\WpPostsProvider;
use Corex\Ui\Patterns\PatternLibrary;
use Corex\Ui\Patterns\PatternRegistrar;

/**
 * Boots the Corex UI library: binds the dynamic-block renderers' dependencies,
 * registers the corex/* dynamic blocks through the corex-blocks engine, and (per
 * later stories) the section patterns + the UI manifest. Presentation only.
 */
final class UiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(PostsProvider::class, WpPostsProvider::class);

        $this->container->singleton(
            UiManifest::class,
            // Block metadata lives under the (case-exact) Blocks/ dir alongside the renderers;
            // BlockMap scans only */block.json, so the .php classes are ignored. The exact case
            // matters on case-sensitive filesystems (Linux/CI).
            static fn (ContainerInterface $c): UiManifest => new UiManifest($c->make(PatternLibrary::class), __DIR__ . '/Blocks'),
        );
    }

    public function boot(): void
    {
        add_action('init', [$this, 'registerBlocks']);
        add_action('init', [$this, 'registerPatterns']);
    }

    /**
     * Register the Corex pattern category and the section patterns.
     */
    public function registerPatterns(): void
    {
        $this->container->make(PatternRegistrar::class)->register();
    }

    /**
     * Discover and register the corex/* dynamic blocks. Each block's view assets are
     * declared in its block.json, so they load only where the block renders.
     */
    public function registerBlocks(): void
    {
        $registrar = $this->container->make(DynamicBlockRegistrar::class);
        $built = dirname(__DIR__) . '/build/blocks';
        $blocksDir = is_dir($built) ? $built : __DIR__ . '/Blocks';

        foreach ($this->container->make(BlockMap::class)->discover($blocksDir) as $block) {
            $registrar->register($block);
        }
    }
}
