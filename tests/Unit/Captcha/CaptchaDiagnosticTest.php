<?php

/**
 * Unit tests for the captcha test-verification classifier (spec 044: US2, FR-007/FR-008).
 * The classifier never receives the secret — only key presence + the provider's response
 * shape — so a result can never leak one.
 *
 * @package Corex\Tests\Unit\Captcha
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Captcha\CaptchaDiagnostic;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

it('treats none and honeypot drivers as not applicable', function () {
    expect(CaptchaDiagnostic::classify('none', false, false, null)->kind)->toBe(CaptchaDiagnostic::NOT_APPLICABLE)
        ->and(CaptchaDiagnostic::classify('honeypot', false, false, null)->kind)->toBe(CaptchaDiagnostic::NOT_APPLICABLE);
});

it('reports missing_keys when a key-driver lacks a site key or secret', function () {
    expect(CaptchaDiagnostic::classify('recaptcha', false, true, null)->kind)->toBe(CaptchaDiagnostic::MISSING_KEYS)
        ->and(CaptchaDiagnostic::classify('recaptcha', true, false, null)->kind)->toBe(CaptchaDiagnostic::MISSING_KEYS);
});

it('reports network_error when the provider could not be reached', function () {
    expect(CaptchaDiagnostic::classify('turnstile', true, true, null, true)->kind)->toBe(CaptchaDiagnostic::NETWORK_ERROR)
        ->and(CaptchaDiagnostic::fromVerifyResponse('recaptcha', true, true, null)->kind)->toBe(CaptchaDiagnostic::NETWORK_ERROR);
});

it('reads invalid_keys from a secret error-code in the verify response', function () {
    $body = ['success' => false, 'error-codes' => ['invalid-input-secret']];

    expect(CaptchaDiagnostic::fromVerifyResponse('recaptcha', true, true, $body)->kind)->toBe(CaptchaDiagnostic::INVALID_KEYS);
});

it('treats a token-only error as ok (the secret was accepted, only the probe token was bad)', function () {
    $body = ['success' => false, 'error-codes' => ['invalid-input-response']];

    expect(CaptchaDiagnostic::fromVerifyResponse('recaptcha', true, true, $body)->kind)->toBe(CaptchaDiagnostic::OK);
});

it('reports ok when the provider accepted the keys outright', function () {
    $result = CaptchaDiagnostic::fromVerifyResponse('hcaptcha', true, true, ['success' => true]);

    expect($result->kind)->toBe(CaptchaDiagnostic::OK)
        ->and($result->message)->toBeString()->not->toBe('');
});
