<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Commands;

defined('ABSPATH') || exit;

/**
 * A WordPress-free command decision result: tests can prove selected sites,
 * batching, rendered rows, and the exit decision without defining WP_CLI.
 */
final class MigrateCommandResult
{
    /**
     * @param list<int> $siteIds
     * @param list<array{site: string, outcome: string, components: string, tables: string, ms: string, error: string}> $rows
     */
    public function __construct(
        public readonly array $siteIds,
        public readonly array $rows,
        public readonly int $batchSize,
        public readonly bool $dryRun,
        public readonly int $failed = 0,
        public readonly bool $networkRefused = false,
    ) {
    }

    public function shouldExitNonZero(): bool
    {
        return $this->networkRefused || $this->failed > 0;
    }
}
