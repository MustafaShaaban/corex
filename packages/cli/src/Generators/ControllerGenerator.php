<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Generators;

defined('ABSPATH') || exit;

/**
 * Scaffolds a thin controller with its service constructor-injected (FR-005, FR-006).
 */
final class ControllerGenerator extends Generator
{
    public function stub(): string
    {
        return 'controller';
    }

    public function suffix(): string
    {
        return 'Controller';
    }

    public function subPath(): string
    {
        return 'Controllers';
    }

    public function placeholders(string $className, GeneratorContext $context): array
    {
        $service = $this->baseName($className) . 'Service';

        return [
            'class'        => $className,
            'namespace'    => $context->namespace . '\\Controllers',
            'service'      => $service,
            'service_fqcn' => $context->namespace . '\\Services\\' . $service,
        ];
    }
}
