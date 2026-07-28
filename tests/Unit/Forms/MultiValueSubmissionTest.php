<?php

/**
 * A multi-value field keeps every value the visitor chose (#148 item 1).
 *
 * The two halves of this fix only work together, and each is worse alone — which is why they are
 * tested together here rather than in two files.
 *
 * The runtime used to send `el.value` for a `<select multiple>`, which is the *first* selected
 * option, so a visitor who picked three services had one stored. The tempting one-line fix — send
 * the real list — makes it worse: every arm of `sanitizeShape()` mapped to a scalar sanitizer, and
 * `sanitize_text_field()` returns `''` for an array, so the field would have been blanked entirely.
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Http\Middleware\Request;
use Corex\Http\Middleware\Response;
use Corex\Http\Middleware\SanitizeMiddleware;

beforeEach(function () {
    Functions\when('sanitize_text_field')->alias(
        static fn (string $value): string => trim(strip_tags($value)),
    );
});

/**
 * The shape `SubmitController::sanitizeShape()` builds for a multi-select, exercised through the
 * middleware that actually applies it — the two are only correct in combination.
 *
 * @param array<string,mixed> $input
 *
 * @return array<string,mixed>
 */
function sanitizedThrough(callable $sanitizer, array $input): array
{
    $captured = [];

    (new SanitizeMiddleware(['services' => $sanitizer]))->process(
        new Request('POST', $input),
        static function (Request $request) use (&$captured) {
            $captured = $request->input;

            return Response::ok();
        },
    );

    return $captured;
}

/**
 * @return callable the private sanitizer the controller supplies for a multi-select
 */
function multiSelectSanitizer(): callable
{
    $method = new ReflectionMethod(\Corex\Forms\Submission\SubmitController::class, 'sanitizeList');

    return static fn (mixed $value): mixed => $method->invoke(null, $value);
}

it('keeps every selected value rather than the first', function () {
    $clean = sanitizedThrough(multiSelectSanitizer(), [
        'services' => ['brand-identity', 'motion-graphics', 'web-design'],
    ]);

    expect($clean['services'])->toBe(['brand-identity', 'motion-graphics', 'web-design']);
});

/**
 * The failure the one-line fix would have introduced. Asserted explicitly, because "the field is
 * empty" is indistinguishable from "the visitor answered nothing" once it reaches the inbox.
 */
it('does not blank the field when the value arrives as a list', function () {
    $clean = sanitizedThrough(multiSelectSanitizer(), [
        'services' => ['brand-identity'],
    ]);

    expect($clean['services'])->not->toBe('')
        ->and($clean['services'])->toBe(['brand-identity']);
});

it('sanitizes each element rather than only the outer value', function () {
    $clean = sanitizedThrough(multiSelectSanitizer(), [
        'services' => ['<script>alert(1)</script>brand', '  motion  '],
    ]);

    expect($clean['services'])->toBe(['alert(1)brand', 'motion']);
});

it('still accepts a single scalar, so one selection behaves as before', function () {
    $clean = sanitizedThrough(multiSelectSanitizer(), ['services' => 'brand-identity']);

    expect($clean['services'])->toBe('brand-identity');
});

it('drops a nested array rather than letting it through as a shape', function () {
    $clean = sanitizedThrough(multiSelectSanitizer(), [
        'services' => ['brand-identity', ['nested' => 'value']],
    ]);

    expect($clean['services'])->toBe(['brand-identity', '']);
});

/**
 * Keys are discarded. A list is what a multi-select submits; preserving submitted keys would let a
 * caller shape the stored array, and nothing downstream reads them.
 */
it('returns a list, not whatever keys the caller sent', function () {
    $clean = sanitizedThrough(multiSelectSanitizer(), [
        'services' => [3 => 'brand-identity', 9 => 'motion-graphics'],
    ]);

    expect(array_keys($clean['services']))->toBe([0, 1]);
});
