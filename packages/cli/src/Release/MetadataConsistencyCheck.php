<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Release;

defined('ABSPATH') || exit;

/**
 * Audits release metadata surfaces and reports exact mismatches without writing files.
 */
final class MetadataConsistencyCheck
{
    /**
     * @param array<string,string> $files            path => contents
     * @param array<string,string> $policyExceptions "path:field" => reason
     *
     * @return array{status:string,expected:string,mismatches:list<array<string,string>>}
     */
    public function evaluate(string $expected, array $files, array $policyExceptions = []): array
    {
        $mismatches = [];

        foreach ($files as $path => $contents) {
            foreach ($this->surfaces($path, $contents) as $surface) {
                if ($surface['actual'] === $expected) {
                    continue;
                }

                $mismatches[] = $this->mismatch($surface, $expected, $policyExceptions);
            }
        }

        return [
            'status' => $this->hasBlockingMismatch($mismatches) ? 'fail' : 'pass',
            'expected' => $expected,
            'mismatches' => $mismatches,
        ];
    }

    /**
     * @return list<array{path:string,field:string,actual:string}>
     */
    private function surfaces(string $path, string $contents): array
    {
        $surfaces = [];
        $this->appendHeaderVersion($surfaces, $path, $contents);
        $this->appendVersionConstants($surfaces, $path, $contents);
        $this->appendPackageVersion($surfaces, $path, $contents);
        $this->appendReadmeRelease($surfaces, $path, $contents);
        $this->appendChangelogRelease($surfaces, $path, $contents);
        $this->appendProgressRelease($surfaces, $path, $contents);

        return $surfaces;
    }

    /**
     * @param list<array{path:string,field:string,actual:string}> $surfaces
     */
    private function appendHeaderVersion(array &$surfaces, string $path, string $contents): void
    {
        if (preg_match('/^\s*(?:\*\s*)?Version:\s*([^\s]+)/m', $contents, $matches) !== 1) {
            return;
        }

        $surfaces[] = ['path' => $path, 'field' => 'Version', 'actual' => $matches[1]];
    }

    /**
     * @param list<array{path:string,field:string,actual:string}> $surfaces
     */
    private function appendVersionConstants(array &$surfaces, string $path, string $contents): void
    {
        preg_match_all("/define\('([^']*VERSION)',\s*'([^']*)'\)/", $contents, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            if (str_starts_with($match[1], 'COREX_')) {
                $surfaces[] = ['path' => $path, 'field' => $match[1], 'actual' => $match[2]];
            }
        }
    }

    /**
     * @param list<array{path:string,field:string,actual:string}> $surfaces
     */
    private function appendPackageVersion(array &$surfaces, string $path, string $contents): void
    {
        if (! in_array($path, ['package.json', 'composer.json'], true)) {
            return;
        }

        $json = json_decode($contents, true);

        if (is_array($json) && isset($json['version']) && is_string($json['version'])) {
            $surfaces[] = ['path' => $path, 'field' => 'version', 'actual' => $json['version']];
        }
    }

    /**
     * @param list<array{path:string,field:string,actual:string}> $surfaces
     */
    private function appendReadmeRelease(array &$surfaces, string $path, string $contents): void
    {
        if ($path === 'README.md' && preg_match('/latest release \*\*v?([0-9][^*]+)\*\*/i', $contents, $matches) === 1) {
            $surfaces[] = ['path' => $path, 'field' => 'latest release', 'actual' => trim($matches[1])];
        }
    }

    /**
     * @param list<array{path:string,field:string,actual:string}> $surfaces
     */
    private function appendChangelogRelease(array &$surfaces, string $path, string $contents): void
    {
        if ($path === 'CHANGELOG.md' && preg_match('/^## \[([0-9][^\]]+)\]/m', $contents, $matches) === 1) {
            $surfaces[] = ['path' => $path, 'field' => 'latest changelog entry', 'actual' => $matches[1]];
        }
    }

    /**
     * @param list<array{path:string,field:string,actual:string}> $surfaces
     */
    private function appendProgressRelease(array &$surfaces, string $path, string $contents): void
    {
        if ($path === 'PROGRESS.md' && preg_match('/release baseline is v?([0-9]+(?:\.[0-9]+){2})/i', $contents, $matches) === 1) {
            $surfaces[] = ['path' => $path, 'field' => 'release baseline', 'actual' => $matches[1]];
        }
    }

    /**
     * @param array{path:string,field:string,actual:string} $surface
     * @param array<string,string>                          $policyExceptions
     *
     * @return array<string,string>
     */
    private function mismatch(array $surface, string $expected, array $policyExceptions): array
    {
        $row = [
            'path' => $surface['path'],
            'field' => $surface['field'],
            'expected' => $expected,
            'actual' => $surface['actual'],
            'status' => 'mismatch',
        ];

        $policy = $policyExceptions[$surface['path'] . ':' . $surface['field']] ?? null;

        if ($policy !== null) {
            $row['status'] = 'ignored-by-policy';
            $row['policy'] = $policy;
        }

        return $row;
    }

    /**
     * @param list<array<string,string>> $mismatches
     */
    private function hasBlockingMismatch(array $mismatches): bool
    {
        foreach ($mismatches as $mismatch) {
            if ($mismatch['status'] !== 'ignored-by-policy') {
                return true;
            }
        }

        return false;
    }
}
