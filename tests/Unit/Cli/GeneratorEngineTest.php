<?php

/**
 * Unit tests for the generator engine (spec US1: FR-002, FR-003, FR-008).
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Generators\GeneratorContext;
use Corex\Cli\Generators\GeneratorEngine;
use Corex\Cli\Generators\StubRenderer;
use Corex\Cli\Generators\UnresolvedPlaceholderException;
use Corex\Cli\Support\Naming;
use Corex\Tests\Fixtures\Cli\FixtureGenerator;

require_once __DIR__ . '/CliFixtures.php';

function tempCliDir(): string
{
    $dir = sys_get_temp_dir() . '/corex_gen_' . uniqid('', true);
    mkdir($dir);

    return $dir;
}

function makeEngine(string $base, string $stubs): GeneratorEngine
{
    return new GeneratorEngine(new StubRenderer(), new Naming(), new GeneratorContext($base, 'App', 'corex'), $stubs);
}

it('creates a file with placeholders rendered, in the artifact sub-path', function () {
    $base = tempCliDir();
    $stubs = tempCliDir();
    file_put_contents($stubs . '/fixture.stub', 'class {{ class }}');

    $result = makeEngine($base, $stubs)->generate(new FixtureGenerator(), 'Foo');

    expect($result->isCreated())->toBeTrue()
        ->and(is_file($result->path))->toBeTrue()
        ->and(str_replace('\\', '/', $result->path))->toContain('Things/Foo.php')
        ->and(file_get_contents($result->path))->toBe('class Foo');
});

it('skips an existing file without force and overwrites with force', function () {
    $base = tempCliDir();
    $stubs = tempCliDir();
    file_put_contents($stubs . '/fixture.stub', 'class {{ class }}');
    $engine = makeEngine($base, $stubs);

    $engine->generate(new FixtureGenerator(), 'Foo');
    $skip = $engine->generate(new FixtureGenerator(), 'Foo');
    expect($skip->status)->toBe('skipped');

    file_put_contents($stubs . '/fixture.stub', 'NEW {{ class }}');
    $forced = makeEngine($base, $stubs)->generate(new FixtureGenerator(), 'Foo', force: true);
    expect($forced->isCreated())->toBeTrue()
        ->and(file_get_contents($forced->path))->toBe('NEW Foo');
});

it('does not write a file when a stub has an unresolved placeholder', function () {
    $base = tempCliDir();
    $stubs = tempCliDir();
    file_put_contents($stubs . '/fixture.stub', '{{ class }} {{ unknown }}');

    expect(fn () => makeEngine($base, $stubs)->generate(new FixtureGenerator(), 'Bar'))
        ->toThrow(UnresolvedPlaceholderException::class);
    expect(is_file($base . '/Things/Bar.php'))->toBeFalse();
});
