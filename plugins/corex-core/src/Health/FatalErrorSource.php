<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Health;

defined('ABSPATH') || exit;

/**
 * Which plugin or theme a file belongs to, from its path and the site's folders (spec 118). Only
 * the kind and the folder's name are kept, never the path: `plugin` + `akismet`, `theme` +
 * `twentytwentyfive`. Pure, so it is unit-testable.
 */
final class FatalErrorSource
{
    public const PLUGIN    = 'plugin';
    public const MU_PLUGIN = 'mu-plugin';
    public const THEME     = 'theme';
    public const CORE      = 'core';
    public const UNKNOWN   = 'unknown';

    /**
     * @param array<string,string> $roots kind => folder, e.g. ['plugin' => WP_PLUGIN_DIR, ...];
     *                                    `core` is the WordPress root, matched last
     *
     * @return array{kind:string,slug:string}
     */
    public static function of(string $file, array $roots): array
    {
        $file = self::normal($file);

        foreach ([self::MU_PLUGIN, self::PLUGIN, self::THEME] as $kind) {
            $root = isset($roots[$kind]) ? self::normal($roots[$kind]) . '/' : '';
            if ($root !== '/' && str_starts_with($file, $root)) {
                $first = explode('/', substr($file, strlen($root)))[0];

                return ['kind' => $kind, 'slug' => preg_replace('/\.php$/', '', $first) ?? $first];
            }
        }

        $core = isset($roots[self::CORE]) ? self::normal($roots[self::CORE]) . '/' : '';
        if ($core !== '/' && (str_starts_with($file, $core . 'wp-includes/') || str_starts_with($file, $core . 'wp-admin/'))) {
            return ['kind' => self::CORE, 'slug' => 'wordpress'];
        }

        return ['kind' => self::UNKNOWN, 'slug' => ''];
    }

    private static function normal(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
