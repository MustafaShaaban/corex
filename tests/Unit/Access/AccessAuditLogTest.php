<?php

/**
 * Unit tests for the access audit log (spec 067). Real denied-attempt recording over an in-memory
 * option backing (no real WordPress). Contract: bounded, 30-day retention, newest first, no
 * invented entries.
 *
 * @package Corex\Tests\Unit\Access
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Access\AccessAuditLog;

beforeEach(function () {
    Functions\when('__')->returnArg();

    $GLOBALS['corex_test_options'] = [];
    Functions\when('get_option')->alias(static fn (string $key, $default = false) => $GLOBALS['corex_test_options'][$key] ?? $default);
    Functions\when('update_option')->alias(static function (string $key, $value): bool {
        $GLOBALS['corex_test_options'][$key] = $value;

        return true;
    });
    Functions\when('sanitize_key')->alias(static fn (string $key): string => strtolower((string) preg_replace('/[^a-z0-9_\-]/', '', strtolower($key))));

    $this->log = new AccessAuditLog();
});

it('starts empty', function () {
    expect($this->log->entries())->toBe([]);
});

it('records a denied attempt with kind, section, and user', function () {
    $this->log->record('denied', 'corex-email-studio', 7);

    $entries = $this->log->entries();

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['kind'])->toBe('denied')
        ->and($entries[0]['section'])->toBe('corex-email-studio')
        ->and($entries[0]['user'])->toBe(7)
        ->and($entries[0]['time'])->toBeGreaterThan(0);
});

it('returns entries newest first', function () {
    $this->log->record('denied', 'access', 1);
    $this->log->record('denied', 'settings', 2);

    $entries = $this->log->entries();

    expect($entries[0]['section'])->toBe('settings')
        ->and($entries[1]['section'])->toBe('access');
});

it('prunes entries older than the 30-day window', function () {
    $GLOBALS['corex_test_options']['corex_access_audit_log'] = [
        ['time' => time() - (31 * 86400), 'user' => 1, 'kind' => 'denied', 'section' => 'old'],
        ['time' => time() - 60, 'user' => 2, 'kind' => 'denied', 'section' => 'fresh'],
    ];

    $entries = $this->log->entries();

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['section'])->toBe('fresh');
});

it('caps the log at 100 entries, dropping the oldest', function () {
    for ($i = 0; $i < 105; $i++) {
        $this->log->record('denied', 'section-' . $i, 1);
    }

    $entries = $this->log->entries();

    expect($entries)->toHaveCount(100)
        ->and($entries[0]['section'])->toBe('section-104')  // newest kept
        ->and(end($entries)['section'])->toBe('section-5'); // oldest five dropped
});

it('ignores malformed stored entries instead of failing', function () {
    $GLOBALS['corex_test_options']['corex_access_audit_log'] = [
        'not-an-array',
        ['time' => time(), 'user' => 3, 'kind' => 'denied', 'section' => 'valid'],
        ['no_kind' => true],
    ];

    $entries = $this->log->entries();

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['section'])->toBe('valid');
});