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
 */
final class ThemeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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
    }

    public function boot(): void
    {
        add_action('wp_head', [$this->container->make(GeneratorTag::class), 'print']);
        add_filter('wp_theme_json_data_default', [$this->container->make(ThemeDefaultPresets::class), 'filter']);

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
    }
}
