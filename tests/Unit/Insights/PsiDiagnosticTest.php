<?php

/**
 * Unit tests for the PageSpeed failure classifier (spec 044: US3, FR-010/FR-013).
 *
 * @package Corex\Tests\Unit\Insights
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Insights\PsiDiagnostic;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

it('classifies a non-public URL as local_url before any HTTP concern', function () {
    expect(PsiDiagnostic::classify(false, 0, null)->kind)->toBe(PsiDiagnostic::LOCAL_URL);
});

it('classifies a 429 as quota', function () {
    expect(PsiDiagnostic::classify(true, 429, null)->kind)->toBe(PsiDiagnostic::QUOTA);
});

it('classifies a 400/403 with a key error as invalid_key', function () {
    $body = ['error' => ['message' => 'The provided API key is invalid.']];

    expect(PsiDiagnostic::classify(true, 400, $body)->kind)->toBe(PsiDiagnostic::INVALID_KEY)
        ->and(PsiDiagnostic::classify(true, 403, $body)->kind)->toBe(PsiDiagnostic::INVALID_KEY);
});

it('classifies a 5xx as http_error', function () {
    expect(PsiDiagnostic::classify(true, 503, null)->kind)->toBe(PsiDiagnostic::HTTP_ERROR);
});

it('classifies a 200 without a lighthouse result as invalid_response', function () {
    expect(PsiDiagnostic::classify(true, 200, ['kind' => 'whatever'])->kind)->toBe(PsiDiagnostic::INVALID_RESPONSE);
});

it('classifies a 200 with a lighthouse result as ok', function () {
    expect(PsiDiagnostic::classify(true, 200, ['lighthouseResult' => ['categories' => []]])->kind)->toBe(PsiDiagnostic::OK);
});

it('scrubs an API key from the admin-only detail', function () {
    $result = PsiDiagnostic::classify(true, 400, ['error' => ['message' => 'bad request key=SECRET123 here']]);

    expect($result->detail)->not->toContain('SECRET123')
        ->and($result->keyAdvice)->toBe('recommended');
});
