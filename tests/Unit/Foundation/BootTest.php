<?php

/**
 * Unit tests for the static Boot entry and the bounded Corex facade
 * (spec US1: FR-001, FR-002, FR-008a).
 *
 * @package Corex\Tests\Unit\Foundation
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Boot;
use Corex\Foundation\Application;
use Corex\Support\Facades\Corex;

it('hooks the bootstrap onto plugins_loaded', function () {
    Functions\expect('add_action')->once()->with('plugins_loaded', [Boot::class, 'boot']);

    Boot::init();
});

it('boots once and resolves dependencies through the Corex facade', function () {
    Functions\when('add_action')->justReturn(true);

    Boot::boot();
    Boot::boot();

    expect(Boot::app())->toBeInstanceOf(Application::class)
        ->and(Corex::make(\stdClass::class))->toBeInstanceOf(\stdClass::class);
});
