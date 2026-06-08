<?php

/**
 * Unit test: make:model scaffolds a valid Model (spec US1: FR-001, FR-006, SC-001).
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Generators\GeneratorContext;
use Corex\Cli\Generators\GeneratorEngine;
use Corex\Cli\Generators\ModelGenerator;
use Corex\Cli\Generators\StubRenderer;
use Corex\Cli\Support\Naming;

it('scaffolds a valid Model with no leftover placeholders', function () {
    $base = sys_get_temp_dir() . '/corex_model_' . uniqid('', true);
    mkdir($base);
    $stubs = dirname(__DIR__, 3) . '/packages/cli/stubs';
    $naming = new Naming();

    $engine = new GeneratorEngine(new StubRenderer(), $naming, new GeneratorContext($base, 'App', 'corex'), $stubs);
    $result = $engine->generate(new ModelGenerator($naming), 'Career');

    $code = file_get_contents($result->path);

    expect($result->isCreated())->toBeTrue()
        ->and($code)->not->toContain('{{')
        ->and($code)->toContain('namespace App\\Models;')
        ->and($code)->toContain('final class Career extends Model')
        ->and($code)->toContain("return 'corex_career';")
        ->and($code)->toContain("defined('ABSPATH') || exit;");
});
