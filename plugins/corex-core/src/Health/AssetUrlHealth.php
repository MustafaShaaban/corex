<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Health;

defined('ABSPATH') || exit;

/**
 * Pure predicate: is a block asset URL a well-formed public URL under the plugins URL base, or does it embed an
 * absolute filesystem path (the spec-040 failure shape, e.g. `…/wp-content/plugins/C:/wamp64/www/…`)? Checking
 * "under the site host" alone is insufficient — the malformed URL is under the host; the discriminator is the
 * embedded drive letter / filesystem path. An empty URL means "nothing to load" and is treated as healthy.
 */
final class AssetUrlHealth
{
    public function isHealthy(string $url, string $pluginsUrlBase): bool
    {
        if ($url === '') {
            return true; // no asset to load
        }

        $base = rtrim($pluginsUrlBase, '/');

        if (! str_starts_with($url, $base . '/')) {
            return false;
        }

        $remainder = substr($url, strlen($base));

        // A drive letter, a second scheme, or a parent-traversal segment all mean an embedded filesystem path.
        if (preg_match('~/[A-Za-z]:~', $remainder) === 1) {
            return false;
        }

        if (str_contains($remainder, '://') || str_contains($remainder, '..')) {
            return false;
        }

        return true;
    }
}
