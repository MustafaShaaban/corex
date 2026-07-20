<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\AdminUi;

defined('ABSPATH') || exit;

/**
 * Cache-busting version for a hand-authored admin screen asset.
 *
 * Every screen stylesheet under `assets/` is source CSS, not a built bundle — nothing hashes it,
 * so its version has to come from the file itself. Three spellings had grown up instead, and two
 * of them do not bust on a CSS edit at all: a hardcoded literal (Insights sat on '1.1.0' across
 * every restyle, so returning visitors kept the old sheet and every fix looked like it had not
 * landed), and the JS bundle's `index.asset.php` hash, which only moves when the *JavaScript*
 * rebuilds. This is the one spelling: the file's own mtime.
 *
 * Falls back to the plugin version when the file is unreadable — a missing asset must not fatal
 * an admin screen.
 */
final class ScreenAsset
{
    public static function version(string $path): string
    {
        $mtime = is_file($path) ? filemtime($path) : false;

        if ($mtime !== false) {
            return (string) $mtime;
        }

        return defined('COREX_CONFIG_VERSION') ? (string) COREX_CONFIG_VERSION : '0.0.0';
    }
}
