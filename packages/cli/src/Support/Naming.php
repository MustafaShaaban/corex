<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Support;

defined('ABSPATH') || exit;

/**
 * Normalizes and validates a requested artifact name (spec FR-009, FR-010).
 */
final class Naming
{
    private const RESERVED = [
        'class', 'function', 'interface', 'trait', 'enum', 'namespace', 'use', 'return',
        'new', 'abstract', 'final', 'public', 'private', 'protected', 'static', 'const',
        'if', 'else', 'for', 'foreach', 'while', 'do', 'switch', 'case', 'break', 'continue',
        'array', 'list', 'echo', 'print', 'exit', 'die', 'true', 'false', 'null',
    ];

    /**
     * Trim, strip an existing suffix, validate as a class identifier, re-apply the suffix.
     *
     * @throws InvalidNameException on an empty, non-identifier, or reserved-word name.
     */
    public function classNameFor(string $raw, string $suffix = ''): string
    {
        $name = trim($raw);

        if ($suffix !== '' && str_ends_with($name, $suffix)) {
            $name = substr($name, 0, -strlen($suffix));
        }

        if ($name === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1 || in_array(strtolower($name), self::RESERVED, true)) {
            throw new InvalidNameException(sprintf('Invalid class name: "%s".', $raw));
        }

        return $name . $suffix;
    }

    public function postTypeFor(string $className): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
    }
}
