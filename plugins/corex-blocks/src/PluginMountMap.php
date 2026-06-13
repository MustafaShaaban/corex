<?php

/**
 * @package Corex\Blocks
 */

declare(strict_types=1);

namespace Corex\Blocks;

defined('ABSPATH') || exit;

/**
 * The WP/filesystem boundary for {@see BlockPathResolver} (spec 040): it exposes WP_PLUGIN_DIR and a map of each
 * plugin-dir entry's realpath-resolved target back to its mount name. Junctions/symlinks resolve to their real
 * on-disk location via `realpath()`, which is exactly what lets the resolver map a realpath-resolved block dir
 * back under the plugins dir. Built once and memoized per request (one scandir + realpath per entry).
 */
final class PluginMountMap
{
    /** @var array<string,string>|null */
    private ?array $mounts = null;

    public function pluginsDir(): string
    {
        return str_replace('\\', '/', rtrim(WP_PLUGIN_DIR, '/\\'));
    }

    /**
     * @return array<string,string> realTargetPath (forward-slash) => mount entry name
     */
    public function mounts(): array
    {
        if ($this->mounts !== null) {
            return $this->mounts;
        }

        $this->mounts = [];
        $base         = WP_PLUGIN_DIR;
        $entries      = scandir($base);

        if ($entries === false) {
            return $this->mounts;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $base . '/' . $entry;

            if (! is_dir($path)) {
                continue;
            }

            $real = realpath($path);

            if ($real === false) {
                continue;
            }

            $this->mounts[str_replace('\\', '/', $real)] = $entry;
        }

        return $this->mounts;
    }
}
