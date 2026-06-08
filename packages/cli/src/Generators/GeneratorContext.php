<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Generators;

defined('ABSPATH') || exit;

/**
 * Where generated files go and under what namespace/prefix — resolved from the
 * Config engine and injected into the generator engine (spec FR-002).
 */
final class GeneratorContext
{
    public function __construct(
        public readonly string $basePath,
        public readonly string $namespace,
        public readonly string $prefix,
    ) {
    }
}
