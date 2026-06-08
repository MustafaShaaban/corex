<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Generators;

defined('ABSPATH') || exit;

/**
 * The structured outcome of a generation (spec FR-011) — the command formats it
 * for the developer.
 */
final class GeneratorResult
{
    public const CREATED = 'created';

    public const SKIPPED = 'skipped';

    public const ERROR = 'error';

    private function __construct(
        public readonly string $status,
        public readonly string $path,
        public readonly ?string $message = null,
    ) {
    }

    public static function created(string $path): self
    {
        return new self(self::CREATED, $path);
    }

    public static function skipped(string $path): self
    {
        return new self(self::SKIPPED, $path, 'File already exists; use --force to overwrite.');
    }

    public static function error(string $path, string $message): self
    {
        return new self(self::ERROR, $path, $message);
    }

    public function isCreated(): bool
    {
        return $this->status === self::CREATED;
    }
}
