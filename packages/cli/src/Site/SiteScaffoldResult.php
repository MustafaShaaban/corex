<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Site;

defined('ABSPATH') || exit;

/**
 * The outcome of scaffolding a client site (spec 049) — the generated files under the site
 * root. Mirrors the other scaffold-result value objects so the command layer reports it the
 * same way.
 */
final class SiteScaffoldResult
{
    public const CREATED = 'created';
    public const SKIPPED = 'skipped';
    public const ERROR   = 'error';

    /**
     * @param list<string> $paths
     */
    private function __construct(
        public readonly string $status,
        public readonly string $siteDir,
        public readonly array $paths = [],
        public readonly ?string $message = null,
    ) {
    }

    /**
     * @param list<string> $paths
     */
    public static function created(string $siteDir, array $paths): self
    {
        return new self(self::CREATED, $siteDir, $paths);
    }

    public static function skipped(string $siteDir): self
    {
        return new self(self::SKIPPED, $siteDir, [], 'Site already exists; use --force to overwrite');
    }

    public static function error(string $siteDir, string $message): self
    {
        return new self(self::ERROR, $siteDir, [], $message);
    }
}
