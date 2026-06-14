<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Generators;

defined('ABSPATH') || exit;

/**
 * The structured outcome of scaffolding a REST API resource — a set of files (controller,
 * routes, request, resource, test), not a single artifact. Mirrors GeneratorResult's status
 * constants so the command layer reports it the same way.
 */
final class ApiResourceScaffoldResult
{
    public const CREATED = 'created';
    public const SKIPPED = 'skipped';
    public const ERROR   = 'error';

    /**
     * @param list<string> $paths the files created (CREATED) or that already exist (SKIPPED)
     */
    private function __construct(
        public readonly string $status,
        public readonly string $apiDir,
        public readonly array $paths = [],
        public readonly ?string $message = null,
    ) {
    }

    /**
     * @param list<string> $paths
     */
    public static function created(string $apiDir, array $paths): self
    {
        return new self(self::CREATED, $apiDir, $paths);
    }

    public static function skipped(string $apiDir): self
    {
        return new self(self::SKIPPED, $apiDir, [], 'API resource already exists; use --force to overwrite');
    }

    public static function error(string $apiDir, string $message): self
    {
        return new self(self::ERROR, $apiDir, [], $message);
    }
}
