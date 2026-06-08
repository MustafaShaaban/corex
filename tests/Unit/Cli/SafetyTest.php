<?php

/**
 * Unit tests for generator safety (spec US3: FR-008, FR-009, FR-011, SC-003, SC-004).
 * Reuses cliSetEngine()/cliSetBase() from GeneratorSetTest (Pest includes all test
 * files before running).
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Generators\ModelGenerator;
use Corex\Cli\Support\InvalidNameException;
use Corex\Cli\Support\Naming;

it('does not overwrite an existing file without force, and overwrites with force', function () {
    $base = cliSetBase();
    $generator = new ModelGenerator(new Naming());

    cliSetEngine($base)->generate($generator, 'Career');
    $skip = cliSetEngine($base)->generate($generator, 'Career');

    expect($skip->status)->toBe('skipped')
        ->and($skip->message)->toContain('--force')
        ->and(cliSetEngine($base)->generate($generator, 'Career', force: true)->isCreated())->toBeTrue();
});

it('rejects an invalid name and writes nothing', function () {
    $base = cliSetBase();

    expect(fn () => cliSetEngine($base)->generate(new ModelGenerator(new Naming()), '9bad'))
        ->toThrow(InvalidNameException::class);

    expect(is_dir($base . '/Models'))->toBeFalse();
});
