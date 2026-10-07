<?php

/**
 * The admin's REST routes say at the route who may call them.
 *
 * The submissions and flow routes were registered open, with `__return_true`, and refused a
 * caller inside the handler. Nobody could call them who should not, and `wp corex routes:list`
 * printed every one of them as "public", which a reader takes at its word. Reported from the
 * first client site, 2026-10-07.
 *
 * @package Corex\Tests\Integration\Http
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Cli\Routes\RouteDescriptor;
use Corex\Cli\Routes\RoutesReader;
use Corex\Config\Submissions\SubmissionsController;
use Corex\Forms\Flow\FlowController;

beforeEach(function () {
    if (! post_type_exists('corex_submission')) {
        register_post_type('corex_submission', ['public' => false]);
    }
    $this->server = rest_get_server();
    $container = Boot::app()->container();
    $container->make(SubmissionsController::class)->register();
    $container->make(FlowController::class)->register();
});

afterEach(function () {
    wp_set_current_user(0);
});

/** @return list<RouteDescriptor> The listed routes under one part of the `corex/v1` namespace. */
function listedRoutesUnder(string $prefix): array
{
    return array_values(array_filter(
        (new RoutesReader())->read(['corex']),
        static fn (RouteDescriptor $route): bool => str_starts_with($route->path, $prefix),
    ));
}

it('lists every submissions route as guarded', function () {
    $routes = listedRoutesUnder('/submissions');
    $open = array_filter($routes, static fn (RouteDescriptor $route): bool => ! $route->guarded);

    expect(count($routes))->toBeGreaterThan(10)
        ->and(array_map(static fn (RouteDescriptor $route): string => $route->path, $open))->toBe([]);
});

it('lists every flow route as guarded, and the route a visitor submits to as public', function () {
    $open = array_values(array_map(
        static fn (RouteDescriptor $route): string => $route->path,
        array_filter(listedRoutesUnder('/flows'), static fn (RouteDescriptor $route): bool => ! $route->guarded),
    ));

    expect($open)->toBe(['/flows/(?P<id>\d+)/submit']);
});

it('refuses a visitor at the route, with the status and the reason the handler gave', function (string $route) {
    wp_set_current_user(0);

    $response = $this->server->dispatch(new WP_REST_Request('GET', $route));

    expect($response->get_status())->toBe(403)
        ->and($response->get_data()['code'])->toBe('forbidden');
})->with([
    'the inbox' => ['/corex/v1/submissions'],
    'the export history' => ['/corex/v1/submissions/exports'],
    'the flows' => ['/corex/v1/flows'],
]);

it('refuses somebody signed in who cannot manage submissions', function () {
    $subscriber = wp_insert_user([
        'user_login' => 'reader_' . strtolower(wp_generate_password(8, false)),
        'user_pass' => wp_generate_password(24),
        'role' => 'subscriber',
    ]);
    wp_set_current_user((int) $subscriber);

    $response = $this->server->dispatch(new WP_REST_Request('GET', '/corex/v1/submissions'));
    wp_delete_user((int) $subscriber);

    expect($response->get_status())->toBe(403)
        ->and($response->get_data()['message'])->toBe('You cannot manage submissions.');
});

it('still answers an administrator', function (string $route) {
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));

    $response = $this->server->dispatch(new WP_REST_Request('GET', $route));

    expect($response->get_status())->toBe(200)
        ->and($response->get_data()['ok'])->toBeTrue();
})->with([
    'the inbox' => ['/corex/v1/submissions'],
    'the flows' => ['/corex/v1/flows'],
]);
