<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Assets;

defined('ABSPATH') || exit;

/**
 * The `assets:doctor` report (spec 047, US4): the resolved environment, whether a build
 * manifest is present, sample URL/version resolutions, and whether source maps are exposed.
 * Pure — values injected; carries no secret.
 */
final class AssetReport
{
    /**
     * @param array<string,string> $samples relative path => resolved version
     *
     * @return array<string,mixed>
     */
    public function build(AssetEnvironment $environment, bool $manifestPresent, array $samples): array
    {
        return [
            'environment' => $environment->name,
            'manifest'    => $manifestPresent ? 'present' : 'absent',
            'source_maps' => $environment->exposesSourceMaps() ? 'exposed' : 'hidden',
            'samples'     => $samples,
        ];
    }

    /**
     * @param array<string,mixed> $report
     *
     * @return list<string>
     */
    public function lines(array $report): array
    {
        $lines = [
            sprintf('Environment: %s', (string) ($report['environment'] ?? '')),
            sprintf('Build manifest: %s', (string) ($report['manifest'] ?? '')),
            sprintf('Source maps: %s', (string) ($report['source_maps'] ?? '')),
        ];

        $samples = is_array($report['samples'] ?? null) ? $report['samples'] : [];
        if ($samples !== []) {
            $lines[] = 'Samples:';
            foreach ($samples as $path => $version) {
                $lines[] = sprintf('  %s — ver %s', (string) $path, (string) $version);
            }
        }

        return $lines;
    }
}
