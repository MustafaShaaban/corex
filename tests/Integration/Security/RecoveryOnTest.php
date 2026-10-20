<?php

/**
 * The Security Center says while recovery is on (spec 112, User Story 4, FR-022).
 *
 * The screen is built with a chosen {@see LoginRecovery}, everything else from the container, and
 * the login settings are answered by a filter: nothing is written to the install.
 *
 * @package Corex\Tests\Integration\Security
 */

declare(strict_types=1);

use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Config\Security\LoginProtection\LoginRecovery;
use Corex\Config\Security\OperationsSecurityScreen;

function securityScreenWithRecovery(bool $active): OperationsSecurityScreen
{
    $container = \Corex\Boot::app()->container();
    $arguments = array_map(
        static function (ReflectionParameter $parameter) use ($container, $active): object {
            $type = (string) $parameter->getType();

            return $type === LoginRecovery::class ? new LoginRecovery($active) : $container->make($type);
        },
        (new ReflectionMethod(OperationsSecurityScreen::class, '__construct'))->getParameters(),
    );

    return new OperationsSecurityScreen(...$arguments);
}

/** Reach a private part of the screen without widening its API for a test. */
function callScreen(OperationsSecurityScreen $screen, string $method, mixed ...$arguments): mixed
{
    return (new ReflectionMethod($screen, $method))->invoke($screen, ...$arguments);
}

beforeEach(function () {
    $this->settings = static fn (): array => [
        'enabled'                 => true,
        'custom_slug'             => 'team-login',
        'block_default_endpoints' => true,
    ];
    add_filter('pre_option_' . LoginProtectionSettingsStore::OPTION, $this->settings);
});

afterEach(function () {
    remove_filter('pre_option_' . LoginProtectionSettingsStore::OPTION, $this->settings);
});

it('tells the Security Center whether recovery is on', function (bool $active) {
    $config = callScreen(securityScreenWithRecovery($active), 'securityConfig');

    expect($config['recoveryActive'])->toBe($active);
})->with([true, false]);

it('says on the overview that the default login endpoints are reachable while recovery is on', function () {
    $html = callScreen(securityScreenWithRecovery(true), 'overviewCard', [], 0);

    expect($html)->toContain('Reachable (recovery is on)')
        ->and($html)->not->toContain('Hidden');
});

it('says they are hidden while recovery is off and protection hides them', function () {
    $html = callScreen(securityScreenWithRecovery(false), 'overviewCard', [], 0);

    expect($html)->toContain('Hidden')
        ->and($html)->not->toContain('recovery is on');
});
