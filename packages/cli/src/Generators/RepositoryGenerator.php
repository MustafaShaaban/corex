<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Generators;

defined('ABSPATH') || exit;

/**
 * Scaffolds a `Corex\Repositories\PostRepository` subclass bound to its Model (FR-005, FR-006).
 */
final class RepositoryGenerator extends Generator
{
    public function stub(): string
    {
        return 'repository';
    }

    public function suffix(): string
    {
        return 'Repository';
    }

    public function subPath(): string
    {
        return 'Repositories';
    }

    public function placeholders(string $className, GeneratorContext $context): array
    {
        $model = $this->baseName($className);

        return [
            'class'      => $className,
            'namespace'  => $context->namespace . '\\Repositories',
            'model'      => $model,
            'model_fqcn' => $context->namespace . '\\Models\\' . $model,
        ];
    }
}
