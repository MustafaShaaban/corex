<?php

/**
 * The two commands that write the login settings are built by the container, with the one store the
 * Security screen's route writes through (spec 124, FR-015; DECISIONS #629). Each gets it in its
 * constructor rather than making its own, so both take the same lock as the screen.
 *
 * @package Corex\Tests\Integration\Security
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Cli\Commands\SecurityResetLoginCommand;
use Corex\Cli\Commands\SecurityTrustedProxiesCommand;
use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;

/** The store a command was given. */
function loginStoreOf(object $command, string $property): object
{
    return (new ReflectionProperty($command, $property))->getValue($command);
}

it('FR-015: builds both login settings commands from the container, with the store the route uses', function () {
    $container = Boot::app()->container();
    $store = $container->make(LoginProtectionSettingsStore::class);

    expect(loginStoreOf($container->make(SecurityResetLoginCommand::class), 'settings'))->toBe($store)
        ->and(loginStoreOf($container->make(SecurityTrustedProxiesCommand::class), 'store'))->toBe($store);
});

it('FR-015: asks for the store, so neither command can be built without one', function (string $command) {
    $parameters = (new ReflectionMethod($command, '__construct'))->getParameters();
    $store = array_values(array_filter(
        $parameters,
        static fn (ReflectionParameter $parameter): bool => (string) $parameter->getType() === LoginProtectionSettingsStore::class,
    ));

    expect($store)->toHaveCount(1)
        ->and($store[0]->isDefaultValueAvailable())->toBeFalse();
})->with([SecurityResetLoginCommand::class, SecurityTrustedProxiesCommand::class]);

it('FR-015: has the CLI provider build the recovery command from the container', function () {
    $source = (string) file_get_contents(dirname(__DIR__, 3) . '/packages/cli/src/CliServiceProvider.php');

    expect($source)->not->toContain('new SecurityResetLoginCommand(')
        ->and($source)->toContain('make(SecurityResetLoginCommand::class)');
});
