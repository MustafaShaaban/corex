<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Generators;

defined('ABSPATH') || exit;

/**
 * The structured outcome of scaffolding a block — a directory of files, not a single
 * artifact. Mirrors GeneratorResult's status constants so the command layer reports
 * both the same way.
 */
final class BlockScaffoldResult
{
    public const CREATED = 'created';
    public const SKIPPED = 'skipped';
    public const ERROR   = 'error';

    /**
     * @param list<string> $paths the files created (CREATED) or that already exist (SKIPPED)
     */
    private function __construct(
        public readonly string $status,
        public readonly string $blockDir,
        public readonly array $paths = [],
        public readonly ?string $message = null,
    ) {
    }

    /**
     * @param list<string> $paths
     */
    public static function created(string $blockDir, array $paths): self
    {
        return new self(self::CREATED, $blockDir, $paths);
    }

    public static function skipped(string $blockDir): self
    {
        return new self(self::SKIPPED, $blockDir, [], 'Block already exists; use --force to overwrite');
    }

    public static function error(string $blockDir, string $message): self
    {
        return new self(self::ERROR, $blockDir, [], $message);
    }
}
