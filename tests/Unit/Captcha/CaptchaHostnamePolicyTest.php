<?php

/**
 * Unit tests for the captcha hostname policy (spec 071 US1: FR-011).
 *
 * Substring matching is the classic reCAPTCHA verification bug: `str_contains($allowed, $host)`
 * accepts `corex.local.evil.com`. These tests pin exact, normalised comparison.
 *
 * @package Corex\Tests\Unit\Captcha
 */

declare(strict_types=1);

use Corex\Captcha\CaptchaHostnamePolicy;

it('accepts an exact hostname match', function () {
    $policy = new CaptchaHostnamePolicy(['corex.local']);
    expect($policy->allows('corex.local'))->toBeTrue();
});

it('rejects a hostname that merely contains an allowed one', function () {
    $policy = new CaptchaHostnamePolicy(['corex.local']);
    expect($policy->allows('corex.local.evil.com'))->toBeFalse()
        ->and($policy->allows('evil-corex.local'))->toBeFalse();
});

it('normalises case and surrounding whitespace on both sides', function () {
    $policy = new CaptchaHostnamePolicy([' Corex.Local ']);
    expect($policy->allows('COREX.local'))->toBeTrue();
});

it('supports an allowlist of several hosts for staging and production', function () {
    $policy = new CaptchaHostnamePolicy(['corex.local', 'staging.example.com', 'example.com']);
    expect($policy->allows('staging.example.com'))->toBeTrue()
        ->and($policy->allows('example.com'))->toBeTrue()
        ->and($policy->allows('other.example.com'))->toBeFalse();
});

it('rejects everything when the allowlist is empty', function () {
    $policy = new CaptchaHostnamePolicy([]);
    expect($policy->allows('corex.local'))->toBeFalse();
});

it('exposes its allowlist normalised for display', function () {
    $policy = new CaptchaHostnamePolicy([' Corex.Local ', 'EXAMPLE.com']);
    expect($policy->allowed())->toBe(['corex.local', 'example.com']);
});
