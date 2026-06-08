<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Support\Config\Sources;

defined('ABSPATH') || exit;

use Corex\Support\BootLogger;
use Corex\Support\Config\Source;
use Dotenv\Dotenv;
use Dotenv\Exception\InvalidFileException;

/**
 * The highest-precedence layer: an optional `.env` file at the project root, read
 * with vlucas/phpdotenv (array-backed, so it never mutates the global environment).
 * A dot key maps to an uppercased env name (`app.name` → `APP_NAME`).
 *
 * An absent file is empty (FR-013); a malformed file is logged and treated as empty
 * rather than crashing boot (FR-014).
 */
final class DotenvSource implements Source
{
    /**
     * @var array<string, mixed>
     */
    private array $values;

    public function __construct(string $directory, BootLogger $logger)
    {
        $this->values = [];

        // An absent .env is valid — resolution falls through to options/defaults (FR-013).
        if (! is_file($directory . '/.env')) {
            return;
        }

        try {
            $this->values = Dotenv::createArrayBacked($directory)->safeLoad();
        } catch (InvalidFileException $e) {
            $logger->warning(sprintf('Malformed .env in %s: %s', $directory, $e->getMessage()));
        }
    }

    public function has(string $key): bool
    {
        return array_key_exists($this->envName($key), $this->values);
    }

    public function get(string $key): mixed
    {
        return $this->values[$this->envName($key)] ?? null;
    }

    private function envName(string $key): string
    {
        return strtoupper(str_replace('.', '_', $key));
    }
}
