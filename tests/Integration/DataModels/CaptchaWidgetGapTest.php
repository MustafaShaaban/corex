<?php

/**
 * Integration test: the admin says when a captcha provider has keys and challenges nobody.
 *
 * Turnstile and hCaptcha can be chosen and given keys. No widget for either is placed on a form.
 * "Selected but its keys are missing" was the only captcha gap listed, so a site with the keys
 * saved read as protected. Reported 2026-10-07 from the first client site.
 *
 * @package Corex\Tests\Integration\DataModels
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\DataModels\CapabilityFacts;

/**
 * The configuration gaps the admin lists, with the captcha settings as given. The settings are
 * answered by a filter, so the install's own are neither read nor changed.
 *
 * @return list<string> The keys of the gaps.
 */
function gapsWithCaptcha(string $driver, string $secret): array
{
    $asDriver = static fn (): string => $driver;
    $asSecret = static fn (): string => $secret;
    add_filter('pre_option_corex_captcha_driver', $asDriver);
    add_filter('pre_option_corex_captcha_secret', $asSecret);

    try {
        $facts = (new CapabilityFacts(Boot::app()->container()))->gather([]);
    } finally {
        remove_filter('pre_option_corex_captcha_driver', $asDriver);
        remove_filter('pre_option_corex_captcha_secret', $asSecret);
    }

    return array_column($facts['gaps'], 'key');
}

it('lists a provider that has keys and no widget as a gap', function (string $driver) {
    expect(gapsWithCaptcha($driver, 'a-secret'))->toContain('captcha.widget')
        ->not->toContain('captcha.keys');
})->with(['turnstile', 'hcaptcha']);

it('lists the missing keys first, for a provider that has none', function () {
    expect(gapsWithCaptcha('turnstile', ''))->toContain('captcha.keys')
        ->not->toContain('captcha.widget');
});

it('lists neither for the provider whose widget is placed', function () {
    expect(gapsWithCaptcha('recaptcha', 'a-secret'))
        ->not->toContain('captcha.widget')
        ->not->toContain('captcha.keys');
});
