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

        // Puts what an editor set on a block on its root element (spec 136, FR-001 to FR-003).
        // One instance under both names: boot registers its listeners on the one the registrar uses.
        $this->container->singleton(WordPressBlockWrapper::class);
        $this->container->singleton(
            BlockWrapper::class,
            static fn (ContainerInterface $c): BlockWrapper => $c->make(WordPressBlockWrapper::class),
        );

        $this->container->singleton(
            DynamicBlockRegistrar::class,
            static fn (ContainerInterface $c): DynamicBlockRegistrar => new DynamicBlockRegistrar(
                $c,
                $c->make(BootLogger::class),
                $c->make(PluginMountMap::class),
                $c->make(BlockPathResolver::class),
                $c->make(BlockWrapper::class),
            ),
        );

        // The one public way to register a folder of blocks: CoreX's own and a site plugin's (spec 135, FR-032).
        $this->container->singleton(
            BlockFolder::class,
            static fn (ContainerInterface $c): BlockFolder => new BlockFolder(
                $c->make(BlockMap::class),
                $c->make(DynamicBlockRegistrar::class),
            ),
        );

        $this->container->singleton(
            PluginRealpathRegistrar::class,
            static fn (ContainerInterface $c): PluginRealpathRegistrar => new PluginRealpathRegistrar(
                $c->make(PluginMountMap::class),
            ),
        );

        $this->container->singleton(ConnectorRegistry::class);

        $this->container->singleton(
            SampleBlocks::class,
            static fn (): SampleBlocks => new SampleBlocks(
                dirname(__DIR__) . '/corex-blocks.php',
                defined('COREX_BLOCKS_VERSION') ? (string) COREX_BLOCKS_VERSION : '0.0.0',
            ),
        );
    }

    public function boot(): void
    {
        // Teach WordPress where every junctioned Corex add-on really lives before any asset URL
        // is derived, so add-ons loaded via Boot (not WP's active_plugins) get correct block/
        // asset URLs instead of a broken `…/plugins/C:/…` path (spec 040).
        $this->container->make(PluginRealpathRegistrar::class)->register();

        add_filter('block_categories_all', [$this, 'registerBlockCategory']);

        // The editor's preview gets a block's markup without the wrapper attributes (spec 136, FR-006a).
        $this->container->make(WordPressBlockWrapper::class)->register();

        // Sample content in shipped patterns: hidden from visitors, labelled for editors (spec 115).
        $this->container->make(SampleBlocks::class)->register();

        // The newsletter signup's editor draws the page's note when the add-on is off (spec 136, FR-018).
        // Made only in the block editor, so no other request builds its renderer.
        add_action('enqueue_block_editor_assets', function (): void {
            $this->container->make(NewsletterSignupEditor::class)->enqueue();
        });

        add_action('init', function (): void {
            $this->container->make(BlockFolder::class)->register(dirname(__DIR__) . '/build/blocks', __DIR__ . '/blocks');
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
