<?php

/**
 * Unit tests for the settings write workflow (spec 068: T197). No WordPress writes.
 * Contract: invalid email/URL/select are rejected (never saved), valid values are sanitized, and
 * an empty write-only secret is preserved rather than cleared.
 *
 * @package Corex\Tests\Unit\Settings
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Settings\SettingsSanitizer;

beforeEach(function () {
    Functions\when('sanitize_email')->returnArg();
    Functions\when('sanitize_text_field')->alias(static fn (string $v): string => trim(strip_tags($v)));
    Functions\when('esc_url_raw')->alias(static fn (string $v): string => str_starts_with($v, 'http') ? $v : '');
    Functions\when('is_email')->alias(static fn (string $v) => str_contains($v, '@') && str_contains($v, '.') ? $v : false);
});

it('rejects an invalid email, URL, and unknown select option (never saved)', function () {
    $sanitizer = new SettingsSanitizer();

    expect($sanitizer->sanitize('not-an-email', 'email'))->toBeNull()
        ->and($sanitizer->sanitize('javascript:alert(1)', 'url'))->toBeNull()
        ->and($sanitizer->sanitize('ftp://x', 'media'))->toBeNull()
        ->and($sanitizer->sanitize('bogus', 'select', ['wp-mail' => 'wp_mail']))->toBeNull();
});

// Spec 106, FR-015: a colour is written into an email's style, so only one form of it is taken.
it('takes a colour only as six-digit hex, and keeps it in one case', function (string $written, ?string $saved) {
    expect((new SettingsSanitizer())->sanitize($written, 'hex-colour'))->toBe($saved);
})->with([
    ['#0B1F3B', '#0b1f3b'],
    [' #ad8643 ', '#ad8643'],
    ['#abc', null],
    ['0B1F3B', null],
    ['red', null],
    ['rgb(1, 2, 3)', null],
    ['#ad8643;background:url(x)', null],
    ['', ''],
]);

it('accepts and sanitizes valid values', function () {
    $sanitizer = new SettingsSanitizer();

    expect($sanitizer->sanitize('owner@example.com', 'email'))->toBe('owner@example.com')
        ->and($sanitizer->sanitize('https://example.com/x', 'url'))->toBe('https://example.com/x')
        ->and($sanitizer->sanitize('wp-mail', 'select', ['wp-mail' => 'wp_mail']))->toBe('wp-mail')
        ->and($sanitizer->sanitize('  Acme <b>Co</b>  ', 'text'))->toBe('Acme Co');
});

it('treats an empty value as an empty string, and preserves only empty secrets', function () {
    $sanitizer = new SettingsSanitizer();

    expect($sanitizer->sanitize('', 'text'))->toBe('')
        // An empty write-only secret must be preserved (not cleared by re-saving the form).
        ->and($sanitizer->shouldPreserve('', 'password'))->toBeTrue()
        // An empty non-secret is a real cleared value and is saved.
        ->and($sanitizer->shouldPreserve('', 'text'))->toBeFalse()
        // A non-empty secret is a real change and is saved.
        ->and($sanitizer->shouldPreserve('new-secret', 'password'))->toBeFalse();
});
