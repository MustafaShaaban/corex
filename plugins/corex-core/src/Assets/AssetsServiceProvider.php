<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Assets;

defined('ABSPATH') || exit;

use Corex\Container\ContainerInterface;
use Corex\Foundation\ServiceProvider;
use Corex\Support\Config\ConfigInterface;

/**
 * Binds the corex-core {@see AssetManager} (spec 047) — its base dir/URL, the resolved
 * environment (Corex config → `wp_get_environment_type()` fallback), the build manifest
 * (read once, gracefully empty if absent), and the framework version fallback. Site
 * plugins (spec 049) construct their own manager for their own base the same way.
 */
final class AssetsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(
            AssetManager::class,
            function (ContainerInterface $c): AssetManager {
                $config = $c->make(ConfigInterface::class);

                return new AssetManager(
                    COREX_CORE_PATH,
                    plugins_url('', COREX_CORE_FILE),
                    AssetEnvironment::from($this->environmentValue($config)),
                    $this->loadManifest(COREX_CORE_PATH . 'build/manifest.json'),
                    COREX_CORE_VERSION,
                    new AssetVersion(),
                );
            },
        );

        // The base registry the asset facades (Style/Script/Image/Picture) resolve (spec 062). The
        // framework registers itself as `corex` (the default); themes/plugins/client sites register
        // their own base the same way so the facades pick the correct URL/version per asset.
        $this->container->singleton(
            AssetRegistry::class,
            static function (ContainerInterface $c): AssetRegistry {
                $registry = new AssetRegistry();
                $registry->register('corex', $c->make(AssetManager::class), true);

                return $registry;
            },
        );
    }

    public function boot(): void
    {
        // Register the curated CoreX font collection for the WP 7 Font Library (spec 062, Priority 2),
        // pointing at the framework's self-hosted brand woff2. Optional editor tooling — guarded inside.
        add_action('init', static function (): void {
            (new FontCollection(plugins_url('assets/fonts', COREX_CORE_FILE)))->register();
        });
    }

    private function environmentValue(ConfigInterface $config): string
    {
        $value = (string) $config->get('app.env', '');

        if ($value === '' && function_exists('wp_get_environment_type')) {
            $value = (string) wp_get_environment_type();
        }

        return $value;
    }

    private function loadManifest(string $path): BuildManifest
    {
        if (! is_file($path)) {
            return BuildManifest::fromArray([]);
        }

        return BuildManifest::fromArray(json_decode((string) file_get_contents($path), true));
    }
}
