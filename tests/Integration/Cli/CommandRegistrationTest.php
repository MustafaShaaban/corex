<?php

/**
 * Integration test: the CLI provider boots cleanly and the generator engine
 * resolves through the container even though WP-CLI is absent in the test runtime
 * (spec US4: FR-012, FR-013, FR-014).
 *
 * @package Corex\Tests\Integration\Cli
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Cli\Generators\GeneratorContext;
use Corex\Cli\Generators\GeneratorEngine;
use Corex\Cli\Support\Naming;

it('boots with the CLI provider and resolves the engine when WP-CLI is absent', function () {
    expect(class_exists('WP_CLI'))->toBeFalse(); // the Pest runtime has no WP-CLI

    $container = Boot::app()->container();

    expect($container->make(GeneratorEngine::class))->toBeInstanceOf(GeneratorEngine::class)
        ->and($container->make(Naming::class))->toBeInstanceOf(Naming::class)
        ->and($container->make(GeneratorContext::class)->namespace)->toBe('App');
});
