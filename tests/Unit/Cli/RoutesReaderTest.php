<?php

/**
 * Unit tests for parsing WP's registered routes into descriptors (spec 046: US2, FR-005).
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Routes\RoutesReader;

function wpRoutes(): array
{
    return [
        '/'                            => [['methods' => ['GET' => true]]],
        '/wp/v2/posts'                 => [['methods' => ['GET' => true], 'permission_callback' => '__return_true']],
        '/corex/v1/data/(?P<s>[\w-]+)' => [['methods' => ['GET' => true], 'permission_callback' => [new stdClass(), 'canManage']]],
        '/corex/v1/captcha/test'       => [['methods' => ['POST' => true], 'permission_callback' => [new stdClass(), 'canRun']]],
        '/app/v1/project'              => [['methods' => ['GET' => true, 'POST' => true], 'permission_callback' => '__return_true']],
    ];
}

it('keeps only the requested namespaces and drops WP core + index routes', function () {
    $descriptors = (new RoutesReader())->fromRoutes(wpRoutes(), ['corex', 'app']);

    $namespaces = array_map(static fn ($d) => $d->namespace, $descriptors);

    expect($namespaces)->not->toContain('wp/v2')
        ->and($namespaces)->toContain('corex/v1')
        ->and($namespaces)->toContain('app/v1')
        ->and($descriptors)->toHaveCount(3);
});

it('splits the namespace from the path and collects the methods', function () {
    $descriptors = (new RoutesReader())->fromRoutes(wpRoutes(), ['app']);

    expect($descriptors[0]->namespace)->toBe('app/v1')
        ->and($descriptors[0]->path)->toBe('/project')
        ->and($descriptors[0]->methods)->toBe('GET, POST');
});

it('marks a route guarded only when its permission callback is not public', function () {
    $byPath = [];
    foreach ((new RoutesReader())->fromRoutes(wpRoutes(), ['corex', 'app']) as $d) {
        $byPath[$d->namespace . $d->path] = $d;
    }

    expect($byPath['corex/v1/captcha/test']->guarded)->toBeTrue()
        ->and($byPath['app/v1/project']->guarded)->toBeFalse();
});
