<?php

/**
 * Fixtures for the generator-engine tests. Required directly; ignored by Pest.
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

namespace Corex\Tests\Fixtures\Cli;

use Corex\Cli\Generators\Generator;
use Corex\Cli\Generators\GeneratorContext;

final class FixtureGenerator extends Generator
{
    public function stub(): string
    {
        return 'fixture';
    }

    public function suffix(): string
    {
        return '';
    }

    public function subPath(): string
    {
        return 'Things';
    }

    public function placeholders(string $className, GeneratorContext $context): array
    {
        return ['class' => $className];
    }
}
