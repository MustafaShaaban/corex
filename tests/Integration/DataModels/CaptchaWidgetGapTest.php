<?php

/**
 * Integration test: what the admin lists as a gap for the site's captcha provider.
 *
 * Turnstile and hCaptcha could be chosen and given keys while no widget for either was placed on a
 * form, and for a while the admin listed that as its own gap (reported 2026-10-07 from the first
 * client site). Both widgets are placed now (spec 104), so the only captcha gap is missing keys.
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

it('lists the missing keys, for a provider that has none', function () {
    expect(gapsWithCaptcha('turnstile', ''))->toContain('captcha.keys');
});

it('lists no captcha gap for a provider whose keys are saved', function (string $driver) {
    expect(array_filter(
        gapsWithCaptcha($driver, 'a-secret'),
        static fn (string $key): bool => str_starts_with($key, 'captcha.'),
    ))->toBe([]);
})->with(['recaptcha', 'turnstile', 'hcaptcha']);
