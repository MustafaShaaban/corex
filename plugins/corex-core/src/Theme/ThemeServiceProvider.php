<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Theme;

use Corex\Container\ContainerInterface;
use Corex\Foundation\ServiceProvider;
use Corex\Support\BootLogger;
use Corex\Support\Config\ConfigInterface;
use WP_Theme_JSON_Data;

defined('ABSPATH') || exit;

/**
 * What the framework puts into the front end's theme layer, so the theme itself
 * stays a logic-free skin (constitution Principle I, FR-010):
 *
 * - per-site brand.json overrides onto the theme.json token defaults, at the one
 *   WordPress moment that owns theme data, the `wp_theme_json_data_theme` filter.
 *   All merge/read logic lives in the headless {@see BrandResolver}.
 * - the {@see GeneratorTag} in every page head (spec 111).
 * - WordPress's own font sizes and shadows kept off the CoreX theme's pages
 *   ({@see ThemeDefaultPresets}, spec 121).
 * - the CoreX theme's heading face preloaded, so nothing moves when it arrives
 *   ({@see ThemeFontPreload}, spec 121).
 * - avatars loaded lazily on the CoreX theme's pages ({@see AvatarLazyLoading}, spec 121).
 */
final class ThemeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Spec 135 slice 6: a child of the CoreX theme's patterns switch and custom templates.
        $this->container->singleton(
            ChildThemeSwitches::class,
            static fn (ContainerInterface $c): ChildThemeSwitches => new ChildThemeSwitches(),
        );

        $this->container->singleton(
            ParentPatternsHider::class,
            static fn (ContainerInterface $c): ParentPatternsHider => new ParentPatternsHider($c->make(ChildThemeSwitches::class)),
        );

        $this->container->singleton(
            ChildCustomTemplates::class,
            static fn (ContainerInterface $c): ChildCustomTemplates => new ChildCustomTemplates($c->make(ChildThemeSwitches::class)),
        );

        $this->container->singleton(
            BrandResolver::class,
            static fn (ContainerInterface $c): BrandResolver => new BrandResolver($c->make(BootLogger::class)),
        );

        $this->container->singleton(
            BrandOverrideValidator::class,
            static fn (ContainerInterface $c): BrandOverrideValidator => new BrandOverrideValidator(),
        );

        $this->container->singleton(
            GeneratorTag::class,
            static fn (ContainerInterface $c): GeneratorTag => new GeneratorTag(),
        );

        $this->container->singleton(
            ThemeDefaultPresets::class,
            static fn (ContainerInterface $c): ThemeDefaultPresets => new ThemeDefaultPresets(),
        );

        $this->container->singleton(
            ThemeFontPreload::class,
            static fn (ContainerInterface $c): ThemeFontPreload => new ThemeFontPreload(),
        );

        $this->container->singleton(
            AvatarLazyLoading::class,
            static fn (ContainerInterface $c): AvatarLazyLoading => new AvatarLazyLoading(),
        );
    }

    public function boot(): void
    {
        add_action('wp_head', [$this->container->make(GeneratorTag::class), 'print']);
        add_filter('wp_theme_json_data_default', [$this->container->make(ThemeDefaultPresets::class), 'filter']);
        add_filter('wp_preload_resources', [$this->container->make(ThemeFontPreload::class), 'add']);
        add_filter('wp_get_loading_optimization_attributes', [$this->container->make(AvatarLazyLoading::class), 'filter'], 10, 4);

        $config = $this->container->make(ConfigInterface::class);
        $configured = (string) $config->get('theme.brand_path', '');

        $resolver = $this->container->make(BrandResolver::class);
        $validator = $this->container->make(BrandOverrideValidator::class);
        $logger = $this->container->make(BootLogger::class);

        add_filter(
            'wp_theme_json_data_theme',
            static function (WP_Theme_JSON_Data $themeJson) use ($resolver, $validator, $logger, $configured): WP_Theme_JSON_Data {
                // Default to the active theme root; honor child→parent fallback.
                $path = $configured !== '' ? $configured : get_theme_file_path('brand.json');

                $brand = $resolver->read($path);
                if ($brand === []) {
                    return $themeJson;
                }

                // Drop any incomplete wholesale-replacement list so its complete
                // defaults survive; a complete brand.json passes through untouched.
                $defaults = $themeJson->get_data();
                $result = $validator->validate($defaults, $brand);

                foreach ($result['issues'] as $issue) {
                    $logger->warning(sprintf(
                        'brand.json: incomplete %s replacement list ignored; defaults retained.',
                        $issue['path'],
                    ));
                }

                return $themeJson->update_with($resolver->merge($defaults, $result['overrides']));
            }
        );

        // Spec 135 slice 6 (DECISIONS #664, #668): under a child of the CoreX theme that sets
        // `hidePatterns`, the CoreX theme's patterns leave the inserter once WordPress has registered
        // them (`init` at 10). Only where patterns are chosen, an admin screen and the REST API the
        // editors read: marking them loads every pattern file, and a visitor's page needs none of it
        // (they stay registered either way). A child's custom templates are completed with CoreX's.
        $patterns = $this->container->make(ParentPatternsHider::class);
        add_action('init', static function () use ($patterns): void {
            if (is_admin()) {
                $patterns->hideFromInserter();
            }
        }, 11);
        add_action('rest_api_init', [$patterns, 'hideFromInserter']);
        add_filter('wp_theme_json_data_theme', [$this->container->make(ChildCustomTemplates::class), 'filter']);
    }
}
