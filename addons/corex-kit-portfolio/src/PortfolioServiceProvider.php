<?php

/**
 * @package Corex\Portfolio
 */

declare(strict_types=1);

namespace Corex\Portfolio;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockMap;
use Corex\Blocks\DynamicBlockRegistrar;
use Corex\Foundation\ServiceProvider;
use Corex\Kit\BlueprintRegistry;
use Corex\Portfolio\Blocks\ProjectsProvider;
use Corex\Portfolio\Blocks\WpProjectsProvider;

/**
 * Boots the Portfolio kit: registers the `corex_project` CPT + `project_type`
 * taxonomy (the portfolio domain), the dynamic projects-grid block (through the
 * corex-blocks engine), and the PortfolioBlueprint manifest. The FSE templates live
 * in the theme (the skin); deactivating the kit leaves them intact.
 */
final class PortfolioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(ProjectsProvider::class, WpProjectsProvider::class);
    }

    public function boot(): void
    {
        add_action('init', [$this, 'registerContent']);
        add_action('init', [$this, 'registerBlock']);

        if ($this->container->has(BlueprintRegistry::class)) {
            $this->container->make(BlueprintRegistry::class)->register(
                $this->container->make(PortfolioBlueprint::class),
            );
        }
    }

    /**
     * The portfolio domain: a public `corex_project` CPT (block-editor + thumbnails)
     * and a `project_type` taxonomy. Registered on init so it exists in every context.
     */
    public function registerContent(): void
    {
        register_post_type(WpProjectsProvider::POST_TYPE, [
            'labels' => [
                'name'          => __('Projects', 'corex'),
                'singular_name' => __('Project', 'corex'),
            ],
            'public'       => true,
            'show_in_rest' => true,
            'menu_icon'    => 'dashicons-portfolio',
            'has_archive'  => true,
            'rewrite'      => ['slug' => 'projects'],
            'supports'     => ['title', 'editor', 'excerpt', 'thumbnail', 'custom-fields'],
        ]);

        register_taxonomy('project_type', WpProjectsProvider::POST_TYPE, [
            'labels' => [
                'name'          => __('Project Types', 'corex'),
                'singular_name' => __('Project Type', 'corex'),
            ],
            'public'            => true,
            'show_in_rest'      => true,
            'hierarchical'      => true,
            'show_admin_column' => true,
        ]);
    }

    /**
     * Discover + register the projects block. Its assets are declared in block.json, so
     * they load only where the block renders (Principle VI).
     */
    public function registerBlock(): void
    {
        $registrar = $this->container->make(DynamicBlockRegistrar::class);
        $built = dirname(__DIR__) . '/build/blocks';
        $blocksDir = is_dir($built) ? $built : __DIR__ . '/Blocks';

        foreach ($this->container->make(BlockMap::class)->discover($blocksDir) as $block) {
            $registrar->register($block);
        }
    }
}
