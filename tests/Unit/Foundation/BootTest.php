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
use Corex\Captcha\CaptchaServiceProvider;
use Corex\Foundation\Application;
use Corex\Foundation\AddonRuntimeState;
use Corex\Support\Facades\Corex;
use Corex\Ui\UiServiceProvider;

it('hooks the bootstrap onto plugins_loaded', function () {
    Functions\expect('add_action')->once()->with('plugins_loaded', [Boot::class, 'boot']);

    Boot::init();
});

it('boots once and resolves dependencies through the Corex facade', function () {
    Functions\when('add_action')->justReturn(true);
    Functions\when('get_option')->justReturn([]);

    Boot::boot();
    Boot::boot();

    expect(Boot::app())->toBeInstanceOf(Application::class)
        ->and(Corex::make(\stdClass::class))->toBeInstanceOf(\stdClass::class);
});

it('builds boot providers from runtime add-on state', function () {
    $providers = Boot::providersForState(new AddonRuntimeState(
        activeSlugs: ['corex-ui'],
        installedPluginFiles: ['corex-ui/corex-ui.php', 'corex-captcha/corex-captcha.php'],
    ));

    expect($providers)->toContain(\Corex\Foundation\CoreServiceProvider::class, UiServiceProvider::class)
        ->and($providers)->not->toContain(CaptchaServiceProvider::class);
});
