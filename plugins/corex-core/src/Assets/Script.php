<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Assets;

defined('ABSPATH') || exit;

/**
 * Enqueue/register a source-controlled script by its base-relative path (spec 062), resolving the URL
 * + version through the {@see AssetManager}. Supports frontend/admin/editor/block scripts, dependencies,
 * footer placement, a loading strategy (defer/async), ES module type, and an explicit version — and
 * merges a sibling WordPress `*.asset.php` build record (its `dependencies` + `version`) when present
 * (wp-scripts emits one next to the built file).
 *
 *   Corex\Assets\Script::enqueue('client-app', 'js/app.js', ['defer' => true, 'in_footer' => true]);
 *   Corex\Assets\Script::enqueueModule('client-module', 'js/module.js');
 */
final class Script
{
    /**
     * @param array<string,mixed> $options deps, in_footer, defer|async|strategy, module, version, base, register_only
     */
    public static function enqueue(string $handle, string $relative, array $options = []): bool
    {
        $manager = Assets::manager($options['base'] ?? null);
        $opts    = ScriptOptions::from($options, self::assetFile($manager, $relative));

        $url     = $manager->url($relative);
        $version = $opts->version ?? $manager->version($relative);

        if (! empty($options['register_only'])) {
            wp_register_script($handle, $url, $opts->deps, $version, $opts->wpArgs());
        } else {
            wp_enqueue_script($handle, $url, $opts->deps, $version, $opts->wpArgs());
        }

        if ($opts->module) {
            self::markAsModule($handle);
        }

        return true;
    }

    /** Enqueue an ES module (`<script type="module">`). */
    public static function enqueueModule(string $handle, string $relative, array $options = []): bool
    {
        $options['module'] = true;

        return self::enqueue($handle, $relative, $options);
    }

    /**
     * Decode the sibling `*.asset.php` build record for a JS file, if present.
     *
     * @return array{dependencies?:list<string>,version?:string}|null
     */
    private static function assetFile(AssetManager $manager, string $relative): ?array
    {
        $path = preg_replace('/\.js$/i', '.asset.php', $manager->path($relative));

        if (! is_string($path) || ! is_file($path)) {
            return null;
        }

        $data = require $path;

        return is_array($data) ? $data : null;
    }

    /** Emit `type="module"` for the handle's `<script>` tag. */
    private static function markAsModule(string $handle): void
    {
        add_filter('script_loader_tag', static function (string $tag, string $tagHandle) use ($handle): string {
            if ($tagHandle !== $handle) {
                return $tag;
            }

            return str_contains($tag, 'type="module"') ? $tag : str_replace('<script ', '<script type="module" ', $tag);
        }, 10, 2);
    }
}
