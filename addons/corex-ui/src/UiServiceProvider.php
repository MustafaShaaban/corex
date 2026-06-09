<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockMap;
use Corex\Blocks\DynamicBlockRegistrar;
use Corex\Foundation\ServiceProvider;
use Corex\Ui\Blocks\PostsProvider;
use Corex\Ui\Blocks\WpPostsProvider;

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
    }

    public function boot(): void
    {
        add_action('init', [$this, 'registerBlocks']);
    }

    /**
     * Discover and register the corex/* dynamic blocks. Each block's view assets are
     * declared in its block.json, so they load only where the block renders.
     */
    public function registerBlocks(): void
    {
        $registrar = $this->container->make(DynamicBlockRegistrar::class);

        foreach ($this->container->make(BlockMap::class)->discover(__DIR__ . '/blocks') as $block) {
            $registrar->register($block);
        }
    }
}
