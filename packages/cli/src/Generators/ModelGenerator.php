<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Generators;

defined('ABSPATH') || exit;

use Corex\Cli\Support\Naming;

/**
 * Scaffolds a read-only `Corex\Models\Model` subclass (spec FR-005, FR-006).
 */
final class ModelGenerator extends Generator
{
    public function __construct(private readonly Naming $naming)
    {
    }

    public function stub(): string
    {
        return 'model';
    }

    public function suffix(): string
    {
        return '';
    }

    public function subPath(): string
    {
        return 'Models';
    }

    public function placeholders(string $className, GeneratorContext $context): array
    {
        return [
            'class'     => $className,
            'namespace' => $context->namespace . '\\Models',
            'prefix'    => $context->prefix,
            'post_type' => $context->prefix . '_' . $this->naming->postTypeFor($className),
        ];
    }
}
