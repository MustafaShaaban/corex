<?php

/**
 * Unit tests for the static Boot entry and the bounded Corex facade
 * (spec US1: FR-001, FR-002, FR-008a).
 *
 * @package Corex\Tests\Unit\Foundation
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Bookings\BookingsServiceProvider;
use Corex\Boot;
use Corex\Captcha\CaptchaServiceProvider;
use Corex\Careers\CareersServiceProvider;
use Corex\Foundation\Application;
use Corex\Foundation\AddonRuntimeState;
use Corex\Multisite\MultisiteServiceProvider;
use Corex\Multisite\PluginActivationInspector;
use Corex\Newsletter\NewsletterServiceProvider;
use Corex\Support\Facades\Corex;
use Corex\Ui\UiServiceProvider;

it('hooks the bootstrap onto plugins_loaded', function () {
    Functions\expect('add_action')->once()->with('plugins_loaded', [Boot::class, 'boot']);

    Boot::init();
});

it('boots once and resolves dependencies through the Corex facade', function () {
    Functions\when('add_action')->justReturn(true);
    Functions\when('add_filter')->justReturn(true);
    // What WordPress itself returns for an unset option. `justReturn([])` made every option an
    // array instead, which reaches ThemeServiceProvider's `(string) $config->get(...)` as an
    // array-to-string conversion — and this suite is configured to fail on warnings.
    Functions\when('get_option')->justReturn(false);
    // Boot detects the install shape before building the Application. Stubbed explicitly rather
    // than left to function_exists(): once any test in the process mocks a WordPress function,
    // Brain Monkey defines it globally, so a later test that reaches it without an expectation
    // fails — and only when the suite runs in that order.
    Functions\when('is_multisite')->justReturn(false);
    Functions\when('apply_filters')->returnArg(2);

    Boot::boot();
    Boot::boot();

    expect(Boot::app())->toBeInstanceOf(Application::class)
        ->and(Corex::make(\stdClass::class))->toBeInstanceOf(\stdClass::class)
        ->and(Corex::make(PluginActivationInspector::class))->toBeInstanceOf(PluginActivationInspector::class);
});

it('builds boot providers from runtime add-on state', function () {
    $providers = Boot::providersForState(new AddonRuntimeState(
        activeSlugs: ['corex-ui'],
        installedPluginFiles: ['corex-ui/corex-ui.php', 'corex-captcha/corex-captcha.php'],
    ));

    expect($providers)->toContain(\Corex\Foundation\CoreServiceProvider::class, UiServiceProvider::class)
        ->and($providers)->not->toContain(CaptchaServiceProvider::class);
});

// The schema self-heal runs when MultisiteServiceProvider boots, and it creates the tables of the
// components declared by then. An add-on declares its table when it boots, so an add-on that
// booted after it would have a table nothing ever created (DECISIONS #299).
it('boots the provider that heals the schema after every add-on', function () {
    $providers = Boot::providersForState(new AddonRuntimeState(
        activeSlugs: ['corex-newsletter', 'corex-bookings', 'corex-careers'],
        installedPluginFiles: [
            'corex-newsletter/corex-newsletter.php',
            'corex-bookings/corex-bookings.php',
            'corex-careers/corex-careers.php',
        ],
    ));

    expect($providers)->toContain(
        NewsletterServiceProvider::class,
        BookingsServiceProvider::class,
        CareersServiceProvider::class,
    )->and(end($providers))->toBe(MultisiteServiceProvider::class);
});
