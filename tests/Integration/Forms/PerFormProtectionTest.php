<?php

/**
 * Per-form protection round-trips through the builder without disturbing existing forms
 * (spec 071 US4: FR-023, FR-024, FR-025).
 *
 * @package Corex\Tests\Integration\Forms
 */

declare(strict_types=1);

use Corex\Forms\Flow\Flow;
use Corex\Forms\Flow\FlowConfiguration;
use Corex\Forms\Flow\FlowProtection;
use Corex\Forms\Flow\FlowRestPresenter;
use Corex\Forms\Flow\FlowVersion;

it('normalises an all-inherit declaration to an empty, checksum-neutral block', function () {
    // A form left on the site default must not change its stored checksum (FR-025).
    $inherit = FlowProtection::normalize(['captcha' => 'inherit', 'action' => '', 'threshold' => null]);

    expect($inherit)->toBe([]);

    $plain = new FlowConfiguration([], [], [], [], [], []);
    $withInherit = new FlowConfiguration([], [], [], [], [], [], $inherit);
    expect($withInherit->checksum())->toBe($plain->checksum());
});

it('keeps a declared per-form override, and clamps its threshold to the provider range', function () {
    $strict = FlowProtection::normalize(['captcha' => 'on', 'threshold' => 5.0, 'action' => 'Contact Form!!']);

    expect($strict['captcha'])->toBe('on')
        ->and($strict['threshold'])->toBe(1.0)                 // clamped from 5.0
        ->and($strict['action'])->toBe('ContactForm');        // normalised to the safe charset
});

it('does not let one form’s override touch another form’s expectation (FR-024)', function () {
    $a = FlowProtection::normalize(['captcha' => 'on', 'threshold' => 0.7]);
    $b = FlowProtection::normalize([]); // inherit

    $configA = new FlowConfiguration([], [], [], [], [], [], $a);
    $configB = new FlowConfiguration([], [], [], [], [], [], $b);

    expect($configA->protection)->not->toBe($configB->protection)
        ->and($configB->protection)->toBe([]);
});

it('projects the protection block back to the builder through the REST presenter', function () {
    $now = new DateTimeImmutable('2026-07-20T12:00:00+00:00');
    $config = new FlowConfiguration([], [], [], [], [], [], ['captcha' => 'on', 'threshold' => 0.7]);
    $version = new FlowVersion(9, 7, 1, $config, 1, $now);

    $projected = (new FlowRestPresenter())->version($version);

    expect($projected['configuration']['protection'])->toBe(['captcha' => 'on', 'threshold' => 0.7]);
});
