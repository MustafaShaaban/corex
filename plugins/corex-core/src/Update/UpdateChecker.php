<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Update;

defined('ABSPATH') || exit;

/**
 * Decides whether a framework update is available — purely. Given the installed version and
 * a fetched update manifest, it returns the update info when the manifest advertises a newer
 * version (semantic compare), else null. No WordPress, no network — the UpdateService does
 * the fetch + injects this into WP's update flow (spec 034).
 */
final class UpdateChecker
{
    /**
     * @param array{version?:string,package?:string,url?:string,requires?:string,tested?:string} $manifest
     *
     * @return array{new_version:string,package:string,url:string,requires:string,tested:string}|null
     */
    public function check(string $currentVersion, array $manifest): ?array
    {
        $remote = trim((string) ($manifest['version'] ?? ''));

        if ($remote === '' || version_compare($remote, $currentVersion, '<=')) {
            return null;
        }

        return [
            'new_version' => $remote,
            'package'     => (string) ($manifest['package'] ?? ''),
            'url'         => (string) ($manifest['url'] ?? ''),
            'requires'    => (string) ($manifest['requires'] ?? ''),
            'tested'      => (string) ($manifest['tested'] ?? ''),
        ];
    }
}
