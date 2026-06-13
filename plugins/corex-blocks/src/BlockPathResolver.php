<?php

/**
 * @package Corex\Blocks
 */

declare(strict_types=1);

namespace Corex\Blocks;

defined('ABSPATH') || exit;

/**
 * Maps a discovered block directory back to its WP_PLUGIN_DIR-relative mount location before registration, so
 * WordPress's `plugins_url()` can always derive a correct asset URL regardless of how the plugin/add-on is
 * mounted (Windows junction, POSIX symlink, or a realpath-resolved/CI checkout) — spec 040. Pure string
 * arithmetic over a mount map the boundary supplies; never touches the filesystem, so the realpath-resolved
 * case is unit-testable with synthetic paths.
 */
final class BlockPathResolver
{
    /**
     * @param string               $blockDir   Absolute block dir (possibly realpath-resolved outside plugins).
     * @param string               $pluginsDir WP_PLUGIN_DIR.
     * @param array<string,string> $mounts     realTargetPath => mountEntryName (see PluginMountMap).
     *
     * @return string The dir under $pluginsDir when mappable; the original $blockDir otherwise.
     */
    public function resolve(string $blockDir, string $pluginsDir, array $mounts): string
    {
        $dir         = $this->normalize($blockDir);
        $compareBase = rtrim($this->normalize($pluginsDir), '/');

        // Already under the plugins dir (the common junction case) → unchanged, byte-for-byte (FR-005).
        if ($dir === $compareBase || str_starts_with($dir, $compareBase . '/')) {
            return $blockDir;
        }

        // The base for the rebuilt path keeps the caller's original casing (only forward-slashed); normalization
        // is for comparison only, so the returned path doesn't drift the drive-letter case.
        $base = rtrim(str_replace('\\', '/', $pluginsDir), '/');

        // Realpath-resolved outside the plugins dir → rebuild under the matching mount entry.
        foreach ($mounts as $realTarget => $entry) {
            $target = rtrim($this->normalize($realTarget), '/');

            if ($dir === $target) {
                return $base . '/' . $entry;
            }

            if (str_starts_with($dir, $target . '/')) {
                return $base . '/' . $entry . substr($dir, strlen($target));
            }
        }

        // Unmappable → never fabricate; return the original and let the health probe flag any bad URL (FR-004).
        return $blockDir;
    }

    private function normalize(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);

        // Windows drive letters: compare case-insensitively by lower-casing the leading drive.
        if (preg_match('~^[A-Za-z]:~', $normalized) === 1) {
            $normalized = lcfirst($normalized);
        }

        return $normalized;
    }
}
