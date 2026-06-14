<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Assets;

defined('ABSPATH') || exit;

/**
 * The per-environment cache-busting strategy (spec 047): local → filemtime (busts on every
 * edit); staging/production → the manifest content hash when available, else the framework/
 * site version (busts on a release). A missing asset or a path that escapes its base falls
 * back to the framework/site version — never an error, never a traversal. Pure (the mtime +
 * manifest hash are injected).
 */
final class AssetVersion
{
    /**
     * @param int|null    $mtime        filemtime of the resolved asset, or null if missing
     * @param string|null $manifestHash content hash from the build manifest, or null/empty
     * @param string      $fallback     the framework/site version
     */
    public function token(
        string $relative,
        ?int $mtime,
        ?string $manifestHash,
        AssetEnvironment $environment,
        string $fallback,
    ): string {
        if ($this->escapesBase($relative)) {
            return $fallback;
        }

        if ($environment->isLocal()) {
            return $mtime !== null ? (string) $mtime : $fallback;
        }

        return $manifestHash !== null && $manifestHash !== '' ? $manifestHash : $fallback;
    }

    private function escapesBase(string $relative): bool
    {
        return str_contains($relative, '..') || str_starts_with($relative, '/') || str_contains($relative, ':');
    }
}
