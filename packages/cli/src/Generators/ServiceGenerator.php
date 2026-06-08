<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Generators;

defined('ABSPATH') || exit;

/**
 * Scaffolds a service with its repository constructor-injected (FR-005, FR-006).
 */
final class ServiceGenerator extends Generator
{
    public function stub(): string
    {
        return 'service';
    }

    public function suffix(): string
    {
        return 'Service';
    }

    public function subPath(): string
    {
        return 'Services';
    }

    public function placeholders(string $className, GeneratorContext $context): array
    {
        $model = $this->baseName($className);
        $repository = $model . 'Repository';

        return [
            'class'           => $className,
            'namespace'       => $context->namespace . '\\Services',
            'model'           => $model,
            'repository'      => $repository,
            'repository_fqcn' => $context->namespace . '\\Repositories\\' . $repository,
        ];
    }
}
