<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Assets;

use Corex\Boot;

defined('ABSPATH') || exit;

/**
 * The resolution root for the asset facades (spec 062). Resolves the shared {@see AssetRegistry}
 * from the container (the framework boundary, like {@see \Corex\Support\Facades\Config}). Tests
 * swap in a fake registry via {@see swap()} so the facades can be exercised without booting WP.
 */
final class Assets
{
    private static ?AssetRegistry $swapped = null;

    public static function registry(): AssetRegistry
    {
        if (self::$swapped instanceof AssetRegistry) {
            return self::$swapped;
        }

        return Boot::app()->container()->make(AssetRegistry::class);
    }

    public static function manager(?string $base = null): AssetManager
    {
        return self::registry()->manager($base);
    }

    /**
     * Register an asset base for a theme/plugin/client in one call (spec 062), so its assets can be
     * enqueued/rendered with the facades: `Assets::registerBase('acme', $dir, $url, $version)` then
     * `Style::enqueue('acme-main', 'css/main.css', ['base' => 'acme'])`. Reads a `build/manifest.json`
     * under $dir when present; the version resolves per environment (filemtime in dev, manifest hash
     * in production, $fallbackVersion otherwise).
     */
    public static function registerBase(string $name, string $dir, string $url, string $fallbackVersion, bool $asDefault = false): AssetManager
    {
        $envValue = function_exists('wp_get_environment_type') ? (string) wp_get_environment_type() : '';
        $manifestPath = rtrim($dir, '/\\') . '/build/manifest.json';
        $manifest = is_file($manifestPath)
            ? BuildManifest::fromArray(json_decode((string) file_get_contents($manifestPath), true))
            : BuildManifest::fromArray([]);

        $manager = new AssetManager($dir, $url, AssetEnvironment::from($envValue), $manifest, $fallbackVersion, new AssetVersion());
        self::registry()->register($name, $manager, $asDefault);

        return $manager;
    }

    /** Test seam: force the registry the facades resolve (null restores container resolution). */
    public static function swap(?AssetRegistry $registry): void
    {
        self::$swapped = $registry;
    }
}
