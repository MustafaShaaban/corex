<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Generators;

defined('ABSPATH') || exit;

/**
 * One artifact generator: pairs a stub with a target sub-path, a class-name suffix,
 * and the placeholder values to render (spec FR-005).
 */
abstract class Generator
{
    abstract public function stub(): string;

    abstract public function suffix(): string;

    abstract public function subPath(): string;

    /**
     * @return array<string, string>
     */
    abstract public function placeholders(string $className, GeneratorContext $context): array;
}
