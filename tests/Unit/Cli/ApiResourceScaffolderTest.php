<?php

/**
 * Unit tests for the API resource scaffolder (make:api-resource, spec 046, US1): a complete
 * secured REST resource from one name — controller + routes + request + resource + test.
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Generators\ApiResourceScaffolder;
use Corex\Cli\Generators\ApiResourceScaffoldResult;
use Corex\Cli\Generators\GeneratorContext;
use Corex\Cli\Generators\StubRenderer;
use Corex\Cli\Support\Naming;

function apiStubsDir(): string
{
    return dirname(__DIR__, 3) . '/packages/cli/stubs';
}

function tempApiBase(): string
{
    $dir = sys_get_temp_dir() . '/corex_api_' . uniqid('', true);
    mkdir($dir);

    return $dir;
}

function apiScaffolder(): ApiResourceScaffolder
{
    return new ApiResourceScaffolder(new StubRenderer(), new Naming(), apiStubsDir());
}

it('scaffolds the full resource file set from one name', function () {
    $base    = tempApiBase();
    $context = new GeneratorContext($base, 'App', 'corex');

    $result = apiScaffolder()->scaffold('Project', $context);

    expect($result->status)->toBe(ApiResourceScaffoldResult::CREATED);

    foreach (['ProjectController.php', 'ProjectRoutes.php', 'ProjectRequest.php', 'ProjectResource.php', 'ProjectTest.php'] as $file) {
        expect(is_file($base . '/Api/' . $file))->toBeTrue();
    }
});

it('generates a thin controller that uses the envelope and is valid PHP', function () {
    $base    = tempApiBase();
    $context = new GeneratorContext($base, 'App', 'corex');

    apiScaffolder()->scaffold('Project', $context);

    $path = $base . '/Api/ProjectController.php';
    $code = (string) file_get_contents($path);

    expect($code)->toContain('namespace App\\Api;')
        ->and($code)->toContain('ResponseEnvelope')
        ->and($code)->toContain('final class ProjectController')
        ->and($code)->toContain('App\\Services\\ProjectService');

    exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $exit);
    expect($exit)->toBe(0);
});

it('registers routes under the app REST namespace with a permission callback', function () {
    $base    = tempApiBase();
    $context = new GeneratorContext($base, 'App', 'corex');

    apiScaffolder()->scaffold('Project', $context);

    $routes = (string) file_get_contents($base . '/Api/ProjectRoutes.php');

    expect($routes)->toContain("register_rest_route('corex/v1'")
        ->and($routes)->toContain("'/project'")
        ->and($routes)->toContain("'permission_callback'");
});

it('skips without --force and overwrites with --force', function () {
    $base    = tempApiBase();
    $context = new GeneratorContext($base, 'App', 'corex');

    apiScaffolder()->scaffold('Project', $context);

    expect(apiScaffolder()->scaffold('Project', $context)->status)->toBe(ApiResourceScaffoldResult::SKIPPED)
        ->and(apiScaffolder()->scaffold('Project', $context, true)->status)->toBe(ApiResourceScaffoldResult::CREATED);
});
