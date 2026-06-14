<?php

/**
 * @package Corex\Blocks
 */

declare(strict_types=1);

namespace Corex\Blocks;

defined('ABSPATH') || exit;

/**
 * Spec 040, extended: WordPress only learns a junctioned/symlinked plugin's real on-disk
 * location when it activates that plugin itself (via `wp_register_plugin_realpath()` in
 * wp-settings, recorded in the `$wp_plugin_paths` global). Corex add-ons loaded through Boot's
 * provider list — not WordPress's `active_plugins` — are never registered, so once
 * `register_block_type_from_metadata()` realpath()-resolves a block's `block.json` back to the
 * monorepo `addons/` path, `plugin_basename()` cannot map it under WP_PLUGIN_DIR and
 * `plugins_url()` emits a broken `…/plugins/C:/wamp64/…` asset URL.
 *
 * This registrar replays that mapping for every junctioned mount at boot, so block (and other)
 * asset URLs resolve correctly for every add-on — matching the WordPress-activated ones. The
 * path arithmetic is pure (headless-testable); the single `wp_register_plugin_realpath()` call
 * is the WordPress boundary.
 */
final class PluginRealpathRegistrar
{
    public function __construct(
        private readonly PluginMountMap $mountMap,
    ) {
    }

    /**
     * Register the realpath mapping with WordPress for each junctioned/symlinked mount, so
     * `plugin_basename()` resolves their assets back under WP_PLUGIN_DIR. A no-op when the
     * WordPress function is absent (headless/tests).
     */
    public function register(): void
    {
        if (! function_exists('wp_register_plugin_realpath')) {
            return;
        }

        foreach (self::pluginFiles($this->mountMap->mounts(), $this->mountMap->pluginsDir()) as $file) {
            if (is_file($file)) {
                wp_register_plugin_realpath($file);
            }
        }
    }

    /**
     * The candidate main-plugin file (`<entry>/<entry>.php`, the Corex add-on convention) for
     * each mount whose real target differs from its WP_PLUGIN_DIR location — i.e. it is a
     * junction/symlink WordPress would otherwise not know about. A mount that already lives
     * under the plugins dir is skipped (WordPress resolves it natively).
     *
     * @param array<string,string> $mounts     realTargetPath => mount entry name (see PluginMountMap)
     * @param string               $pluginsDir WP_PLUGIN_DIR
     *
     * @return list<string>
     */
    public static function pluginFiles(array $mounts, string $pluginsDir): array
    {
        $base  = rtrim(str_replace('\\', '/', $pluginsDir), '/');
        $files = [];

        foreach ($mounts as $realTarget => $entry) {
            $real  = rtrim(self::normalize($realTarget), '/');
            $mount = self::normalize($base . '/' . $entry);

            // A real directory already under the plugins dir → WordPress maps it natively.
            if ($real === $mount) {
                continue;
            }

            $files[] = $base . '/' . $entry . '/' . $entry . '.php';
        }

        return $files;
    }

    private static function normalize(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);

        // Windows drive letters compare case-insensitively (mirror BlockPathResolver).
        if (preg_match('~^[A-Za-z]:~', $normalized) === 1) {
            $normalized = lcfirst($normalized);
        }

        return $normalized;
    }
}
