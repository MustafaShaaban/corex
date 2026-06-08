<?php

/**
 * Unit tests for the four-generator set (spec US2: FR-005, FR-006).
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Generators\ControllerGenerator;
use Corex\Cli\Generators\GeneratorContext;
use Corex\Cli\Generators\GeneratorEngine;
use Corex\Cli\Generators\RepositoryGenerator;
use Corex\Cli\Generators\ServiceGenerator;
use Corex\Cli\Generators\StubRenderer;
use Corex\Cli\Support\Naming;

function cliSetEngine(string $base): GeneratorEngine
{
    $stubs = dirname(__DIR__, 3) . '/packages/cli/stubs';

    return new GeneratorEngine(new StubRenderer(), new Naming(), new GeneratorContext($base, 'App', 'corex'), $stubs);
}

function cliSetBase(): string
{
    $dir = sys_get_temp_dir() . '/corex_set_' . uniqid('', true);
    mkdir($dir);

    return $dir;
}

it('scaffolds a PostRepository subclass bound to its model', function () {
    $result = cliSetEngine(cliSetBase())->generate(new RepositoryGenerator(), 'Career');
    $code = file_get_contents($result->path);

    expect(str_replace('\\', '/', $result->path))->toContain('Repositories/CareerRepository.php')
        ->and($code)->toContain('namespace App\\Repositories;')
        ->and($code)->toContain('final class CareerRepository extends PostRepository')
        ->and($code)->toContain('return Career::class;')
        ->and($code)->toContain('use App\\Models\\Career;')
        ->and($code)->not->toContain('{{');
});

it('scaffolds a service with its repository constructor-injected', function () {
    $code = file_get_contents(cliSetEngine(cliSetBase())->generate(new ServiceGenerator(), 'Career')->path);

    expect($code)->toContain('final class CareerService')
        ->and($code)->toContain('private readonly CareerRepository $repository')
        ->and($code)->toContain('use App\\Repositories\\CareerRepository;')
        ->and($code)->not->toContain('{{');
});

it('scaffolds a thin controller with its service constructor-injected', function () {
    $code = file_get_contents(cliSetEngine(cliSetBase())->generate(new ControllerGenerator(), 'Career')->path);

    expect($code)->toContain('final class CareerController')
        ->and($code)->toContain('private readonly CareerService $service')
        ->and($code)->not->toContain('{{');
});
