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
