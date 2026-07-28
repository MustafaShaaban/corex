<?php

/**
 * Unit test: make:guide scaffolds a registerable Guide (spec 084: FR-016).
 *
 * The generator is how a site developer meets this API, so what it produces has to compile and has
 * to demonstrate the parts that are easy to skip — a warning before something hard to undo, and an
 * expected result on every instruction.
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Generators\GeneratorContext;
use Corex\Cli\Generators\GeneratorEngine;
use Corex\Cli\Generators\GuideGenerator;
use Corex\Cli\Generators\StubRenderer;
use Corex\Cli\Support\Naming;

/**
 * @return string the generated file's contents
 */
function generateGuide(string $className): string
{
    $base = sys_get_temp_dir() . '/corex_guide_' . uniqid('', true);
    mkdir($base);
    $stubs = dirname(__DIR__, 3) . '/packages/cli/stubs';

    $engine = new GeneratorEngine(
        new StubRenderer(),
        new Naming(),
        new GeneratorContext($base, 'App', 'corex'),
        $stubs,
    );

    return (string) file_get_contents($engine->generate(new GuideGenerator(), $className)->path);
}

it('scaffolds a registerable guide with no leftover placeholders', function () {
    $code = generateGuide('Projects');

    expect($code)->not->toContain('{{')
        ->and($code)->toContain('namespace App\\Guides;')
        ->and($code)->toContain('final class ProjectsGuide')
        ->and($code)->toContain('use Corex\\Guides\\Guide;')
        ->and($code)->toContain("Guide::for('projects'")
        ->and($code)->toContain("defined('ABSPATH') || exit;");
});

/**
 * The scaffold points at `registerDeferred()`, not `register()`. A site developer copying the
 * comment is the most likely way this API is ever called, so the example has to be the one that
 * survives plugin-load ordering rather than the one that happens to work today.
 */
it('shows the registration that survives load ordering', function () {
    expect(generateGuide('Projects'))->toContain('registerDeferred');
});

it('demonstrates the two parts an author skips from a blank file', function () {
    $code = generateGuide('Projects');

    expect($code)->toContain('warning:')
        ->and(substr_count($code, 'new GuideStep('))->toBeGreaterThan(1);
});

it('turns a multi-word class name into a readable id', function () {
    expect(generateGuide('JobApplications'))
        ->toContain("Guide::for('job-applications'")
        ->and(generateGuide('JobApplications'))->toContain('final class JobApplicationsGuide');
});
