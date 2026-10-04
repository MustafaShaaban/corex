<?php

/**
 * Integration tests: the CLI provider stays inert without WP-CLI and exposes
 * every command registration without resolving command dependencies eagerly
 * (spec US4: FR-012, FR-013, FR-014; issue #201).
 *
 * @package Corex\Tests\Integration\Cli
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Cli\CliServiceProvider;
use Corex\Cli\Generators\GeneratorContext;
use Corex\Cli\Generators\GeneratorEngine;
use Corex\Cli\Support\Naming;
use Corex\Container\ContainerInterface;

final class UnresolvableCommandContainer201 implements ContainerInterface
{
    public int $resolutionAttempts = 0;

    public function bind(string $id, \Closure|string|null $concrete = null): void
    {
    }

    public function singleton(string $id, \Closure|string|null $concrete = null): void
    {
    }

    public function instance(string $id, object $instance): object
    {
        return $instance;
    }

    public function make(string $id, array $parameters = []): mixed
    {
        $this->resolutionAttempts++;

        throw new LogicException("Command dependency {$id} was resolved while registrations were built.");
    }

    public function get(string $id): mixed
    {
        return $this->make($id);
    }

    public function has(string $id): bool
    {
        return false;
    }
}

it('boots with the CLI provider and resolves the engine when WP-CLI is absent', function () {
    expect(class_exists('WP_CLI'))->toBeFalse(); // the Pest runtime has no WP-CLI

    $container = Boot::app()->container();

    expect($container->make(GeneratorEngine::class))->toBeInstanceOf(GeneratorEngine::class)
        ->and($container->make(Naming::class))->toBeInstanceOf(Naming::class)
        ->and($container->make(GeneratorContext::class)->namespace)->toBe('App');
});

it('keeps all command registrations available when dependencies cannot resolve', function () {
    $container = new UnresolvableCommandContainer201();
    $registrations = (new CliServiceProvider($container))->commandRegistrations();
    $expectedCommands = [
        'corex make:model',
        'corex make:repository',
        'corex make:controller',
        'corex make:service',
        'corex make:option-page',
        'corex make:guide',
        'corex make:block',
        'corex make:api-resource',
        'corex make:site',
        'corex routes:list',
        'corex api:docs',
        'corex assets:doctor',
        'corex cache:status',
        'corex cache:doctor',
        'corex cache:clear',
        'corex compliance:check',
        'corex package:update',
        'corex docs:sync',
        'corex docs:serve',
        'corex docs:generate',
        'corex reset',
        'corex migrate',
        'corex security reset-login',
        'corex doctor',
        'corex readiness',
        'corex version',
    ];

    expect($registrations)->toHaveCount(26)
        ->and(array_keys($registrations))->toBe($expectedCommands)
        ->and($registrations)->toHaveKeys(['corex migrate', 'corex doctor', 'corex reset'])
        ->and($container->resolutionAttempts)->toBe(0);

    foreach ($registrations as $registration) {
        expect(is_callable($registration['handler']))->toBeTrue()
            ->and($registration['definition'])->toBeArray();
    }
});

it('declares php-parser as a production dependency', function () {
    $composer = json_decode(
        (string) file_get_contents(dirname(__DIR__, 3) . '/composer.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($composer['require']['nikic/php-parser'] ?? null)->toBe('^5.0');
});
