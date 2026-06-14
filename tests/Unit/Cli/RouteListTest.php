<?php

/**
 * Unit tests for the route descriptor + list formatter (spec 046: US2, FR-005).
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Routes\RouteDescriptor;
use Corex\Cli\Routes\RouteList;

it('holds a route descriptor and reports whether it is guarded', function () {
    $public  = new RouteDescriptor('corex/v1', '/data/(?P<source>[\w-]+)', 'GET', false);
    $guarded = new RouteDescriptor('corex/v1', '/captcha/test', 'POST', true);

    expect($public->namespace)->toBe('corex/v1')
        ->and($public->guarded)->toBeFalse()
        ->and($guarded->guarded)->toBeTrue();
});

it('formats routes into readable lines grouped by namespace', function () {
    $lines = (new RouteList())->lines([
        new RouteDescriptor('corex/v1', '/data/submissions', 'GET', true),
        new RouteDescriptor('corex/v1', '/captcha/test', 'POST', true),
        new RouteDescriptor('app/v1', '/project', 'GET', false),
    ]);

    $text = implode("\n", $lines);

    expect($text)->toContain('corex/v1')
        ->and($text)->toContain('app/v1')
        ->and($text)->toContain('GET')
        ->and($text)->toContain('/captcha/test')
        ->and($text)->toContain('guarded')
        ->and($text)->toContain('public');
});

it('returns an empty listing without error when there are no routes', function () {
    expect((new RouteList())->lines([]))->toBe([]);
});
