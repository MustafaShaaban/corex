<?php

/**
 * Unit tests for the custom login route/default endpoint guard.
 *
 * @package Corex\Tests\Unit\Security
 */

declare(strict_types=1);

use Corex\Config\Security\LoginProtection\LoginProtectionSettings;
use Corex\Config\Security\LoginProtection\LoginRouteGuard;

function routePolicy(array $overrides = []): LoginProtectionSettings
{
    return new LoginProtectionSettings(
        enabled: $overrides['enabled'] ?? true,
        customSlug: $overrides['customSlug'] ?? 'team-login',
        blockDefaultEndpoints: $overrides['blockDefaultEndpoints'] ?? true,
        threshold: 5,
        windowSeconds: 300,
        lockoutSeconds: 900,
        trustedProxyMode: false,
        trustedProxyRanges: [],
        retainDays: 30,
        successfulLoginLogging: true,
    );
}

it('blocks unauthenticated default login endpoints with an honest not-found decision', function () {
    $guard = new LoginRouteGuard(routePolicy());

    expect($guard->decision('/wp-login.php', authenticated: false)->blocked)->toBeTrue()
        ->and($guard->decision('/wp-login.php?action=lostpassword', authenticated: false)->statusCode)->toBe(404)
        ->and($guard->decision('/wp-admin/', authenticated: false)->reasonCode)->toBe('default_endpoint_blocked');
});

it('allows the configured custom login slug and does not move WordPress core files', function () {
    $guard = new LoginRouteGuard(routePolicy(['customSlug' => 'secure-entry']));

    expect($guard->decision('/secure-entry/', authenticated: false)->blocked)->toBeFalse()
        ->and($guard->decision('/secure-entry', authenticated: false)->blocked)->toBeFalse()
        ->and($guard->customLoginPath())->toBe('/secure-entry/')
        ->and($guard->movesCoreFiles())->toBeFalse();
});

it('allows authenticated users disabled policies and recovery unguard requests', function () {
    $enabled = new LoginRouteGuard(routePolicy());
    $disabled = new LoginRouteGuard(routePolicy(['enabled' => false]));

    expect($enabled->decision('/wp-login.php', authenticated: true)->blocked)->toBeFalse()
        ->and($enabled->decision('/wp-login.php', authenticated: false, unguarded: true)->blocked)->toBeFalse()
        ->and($disabled->decision('/wp-login.php', authenticated: false)->blocked)->toBeFalse();
});

it('does not block unrelated public routes', function () {
    $guard = new LoginRouteGuard(routePolicy());

    expect($guard->decision('/about/', authenticated: false)->blocked)->toBeFalse()
        ->and($guard->decision('/wp-content/uploads/logo.png', authenticated: false)->blocked)->toBeFalse();
});
