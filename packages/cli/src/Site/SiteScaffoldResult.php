<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Site;

defined('ABSPATH') || exit;

/**
 * The outcome of scaffolding a client site (spec 049): the generated files under the site root,
 * and the existing theme files it left as they were (spec 135, FR-019). Mirrors the other
 * scaffold-result value objects so the command layer reports it the same way.
 */
final class SiteScaffoldResult
{
    public const CREATED = 'created';
    public const SKIPPED = 'skipped';
    public const ERROR   = 'error';

    /**
     * @param list<string> $paths Files written.
     * @param list<string> $kept     Files of an existing theme left untouched (FR-019).
     * @param list<string> $lookedIn Where the CoreX theme was looked for, when it was not found (FR-012).
     */
    private function __construct(
        public readonly string $status,
        public readonly string $siteDir,
        public readonly array $paths = [],
        public readonly ?string $message = null,
        public readonly array $kept = [],
        public readonly array $lookedIn = [],
    ) {
    }

    /**
     * @param list<string> $paths
     * @param list<string> $kept
     */
    public static function created(string $siteDir, array $paths, array $kept = []): self
    {
        return new self(self::CREATED, $siteDir, $paths, null, $kept);
    }

    public static function skipped(string $siteDir): self
    {
        return new self(self::SKIPPED, $siteDir, [], 'Site already exists; use --force to overwrite');
    }

    public static function error(string $siteDir, string $message): self
    {
        return new self(self::ERROR, $siteDir, [], $message);
    }

    /**
     * Nothing was written: the CoreX theme's theme.json, which a client theme's tokens are read
     * from, was found at none of the paths looked in (spec 135, FR-012).
     */
    public static function parentThemeUnavailable(string $siteDir, ParentThemeUnavailable $reason): self
    {
        return new self(self::ERROR, $siteDir, [], $reason->getMessage(), [], $reason->paths);
    }
}
