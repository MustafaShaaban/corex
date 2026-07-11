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

it('hides /wp-admin and wp-login.php from logged-out visitors instead of revealing the slug', function () {
    $guard = new LoginRouteGuard(routePolicy());

    // Logged-out default endpoints are hidden (this is the bug fix: /wp-admin must 404, not
    // redirect to — and reveal — the custom login).
    expect($guard->hidesDefaultEndpoint('/wp-admin/', 'index.php', loggedIn: false, isAdmin: true, ajax: false))->toBeTrue()
        ->and($guard->hidesDefaultEndpoint('/wp-login.php', 'wp-login.php', loggedIn: false, isAdmin: false, ajax: false))->toBeTrue();
});

it('never hides admin for logged-in users, AJAX, admin-post, or public routes', function () {
    $guard = new LoginRouteGuard(routePolicy());

    expect($guard->hidesDefaultEndpoint('/wp-admin/', 'index.php', loggedIn: true, isAdmin: true, ajax: false))->toBeFalse()
        ->and($guard->hidesDefaultEndpoint('/wp-admin/admin-ajax.php', 'admin-ajax.php', loggedIn: false, isAdmin: true, ajax: true))->toBeFalse()
        ->and($guard->hidesDefaultEndpoint('/wp-admin/admin-ajax.php', 'admin-ajax.php', loggedIn: false, isAdmin: true, ajax: false))->toBeFalse()
        ->and($guard->hidesDefaultEndpoint('/wp-admin/admin-post.php', 'admin-post.php', loggedIn: false, isAdmin: true, ajax: false))->toBeFalse()
        ->and($guard->hidesDefaultEndpoint('/about/', 'index.php', loggedIn: false, isAdmin: false, ajax: false))->toBeFalse();
});

it('never hides when the policy is disabled or default-endpoint blocking is off', function () {
    $disabled = new LoginRouteGuard(routePolicy(['enabled' => false]));
    $noBlock = new LoginRouteGuard(routePolicy(['blockDefaultEndpoints' => false]));

    expect($disabled->hidesDefaultEndpoint('/wp-admin/', 'index.php', loggedIn: false, isAdmin: true, ajax: false))->toBeFalse()
        ->and($noBlock->hidesDefaultEndpoint('/wp-login.php', 'wp-login.php', loggedIn: false, isAdmin: false, ajax: false))->toBeFalse();
});
