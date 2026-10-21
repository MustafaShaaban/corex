<?php

/**
 * Unit tests for the access audit log (spec 067; spec 112, User Story 2). Real denied-attempt
 * recording over an in-memory option and object cache (no real WordPress).
 *
 * One person's refusals of one section are one entry per hour, counted, with the first and last
 * time; one person cannot push other people's entries out; with a persistent object cache a repeat
 * within the hour writes nothing to the database. 30-day retention, newest first.
 *
 * @package Corex\Tests\Unit\Access
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Access\AccessAuditLog;

const AUDIT_T0 = 1_760_000_000;

beforeEach(function () {
    Functions\when('__')->returnArg();

    $GLOBALS['corex_test_options'] = [];
    $GLOBALS['corex_test_cache']   = [];
    $GLOBALS['corex_test_writes']  = 0;
    $GLOBALS['corex_test_ext_cache'] = false;

    Functions\when('get_option')->alias(static fn (string $key, $default = false) => $GLOBALS['corex_test_options'][$key] ?? $default);
    Functions\when('update_option')->alias(static function (string $key, $value): bool {
        $GLOBALS['corex_test_options'][$key] = $value;
        $GLOBALS['corex_test_writes']++;

        return true;
    });
    Functions\when('sanitize_key')->alias(static fn (string $key): string => strtolower((string) preg_replace('/[^a-z0-9_\-]/', '', strtolower($key))));
    Functions\when('wp_using_ext_object_cache')->alias(static fn (): bool => $GLOBALS['corex_test_ext_cache']);
    Functions\when('wp_cache_get')->alias(static fn (string $key, string $group = '') => $GLOBALS['corex_test_cache'][$group . ':' . $key] ?? false);
    Functions\when('wp_cache_set')->alias(static function (string $key, $value, string $group = ''): bool {
        $GLOBALS['corex_test_cache'][$group . ':' . $key] = $value;

        return true;
    });
    Functions\when('wp_cache_delete')->alias(static function (string $key, string $group = ''): bool {
        unset($GLOBALS['corex_test_cache'][$group . ':' . $key]);

        return true;
    });

    $this->log = new AccessAuditLog();
    $this->refuse = function (int $user, string $section, int $at, int $times = 1): void {
        for ($i = 0; $i < $times; $i++) {
            $this->log->record('denied', $section, $user, $at);
        }
    };
});

it('starts empty', function () {
    expect($this->log->entries(AUDIT_T0))->toBe([]);
});

it('records a refusal once, with the person, the section, and its time as first and last', function () {
    ($this->refuse)(7, 'corex-email-studio', AUDIT_T0);

    expect($this->log->entries(AUDIT_T0))->toBe([[
        'user' => 7, 'kind' => 'denied', 'section' => 'corex-email-studio',
        'first' => AUDIT_T0, 'last' => AUDIT_T0, 'count' => 1,
    ]]);
});

it('counts one person refused for one section within an hour as one entry', function () {
    for ($minute = 0; $minute < 50; $minute++) {
        ($this->refuse)(7, 'corex-forms', AUDIT_T0 + $minute * 60);
    }

    $entries = $this->log->entries(AUDIT_T0 + 3000);

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['count'])->toBe(50)
        ->and($entries[0]['first'])->toBe(AUDIT_T0)
        ->and($entries[0]['last'])->toBe(AUDIT_T0 + 49 * 60);
});

it('starts a new entry an hour after the first refusal of the window, not at a clock hour', function () {
    ($this->refuse)(7, 'corex-forms', AUDIT_T0);
    ($this->refuse)(7, 'corex-forms', AUDIT_T0 + 3599);
    ($this->refuse)(7, 'corex-forms', AUDIT_T0 + 3600);

    expect(array_column($this->log->entries(AUDIT_T0 + 3600), 'count'))->toBe([1, 2]);
});

it('keeps a window across midnight as one entry', function () {
    $beforeMidnight = (int) (new DateTimeImmutable('2026-10-20 23:50:00', new DateTimeZone('UTC')))->format('U');

    ($this->refuse)(7, 'corex-forms', $beforeMidnight);
    ($this->refuse)(7, 'corex-forms', $beforeMidnight + 20 * 60);

    expect($this->log->entries($beforeMidnight + 20 * 60))->toHaveCount(1);
});

it('counts different people, sections and kinds apart', function () {
    ($this->refuse)(7, 'corex-forms', AUDIT_T0);
    ($this->refuse)(8, 'corex-forms', AUDIT_T0);
    ($this->refuse)(7, 'corex-email-studio', AUDIT_T0);
    $this->log->record('data', 'corex-forms', 7, AUDIT_T0);

    expect($this->log->entries(AUDIT_T0))->toHaveCount(4);
});

it('keeps every other person listed when one person is refused a thousand times', function () {
    ($this->refuse)(1, 'corex-forms', AUDIT_T0);
    ($this->refuse)(2, 'corex-access', AUDIT_T0);
    for ($i = 0; $i < 1000; $i++) {
        // A thousand refusals across a thousand sections: the worst case for the cap.
        ($this->refuse)(9, 'section-' . $i, AUDIT_T0 + $i);
    }

    $users = array_column($this->log->entries(AUDIT_T0 + 1000), 'user');

    expect($users)->toContain(1, 2)
        ->and(count(array_filter($users, static fn (int $user): bool => $user === 9)))->toBe(AccessAuditLog::MAX_PER_PERSON);
});

it('writes each repeated refusal into that person\'s one entry when there is no persistent object cache', function () {
    ($this->refuse)(7, 'corex-forms', AUDIT_T0, 10);

    expect($GLOBALS['corex_test_writes'])->toBe(10)
        ->and($this->log->entries(AUDIT_T0))->toHaveCount(1);
});

it('writes once for a window when the host has a persistent object cache, and still shows the count', function () {
    $GLOBALS['corex_test_ext_cache'] = true;

    ($this->refuse)(7, 'corex-forms', AUDIT_T0, 10);
    $entries = $this->log->entries(AUDIT_T0);

    expect($GLOBALS['corex_test_writes'])->toBe(1)
        ->and($entries)->toHaveCount(1)
        ->and($entries[0]['count'])->toBe(10);
});

it('stores the cached count when the window closes, then starts the next', function () {
    $GLOBALS['corex_test_ext_cache'] = true;

    ($this->refuse)(7, 'corex-forms', AUDIT_T0, 10);
    ($this->refuse)(7, 'corex-forms', AUDIT_T0 + 3600);
    $GLOBALS['corex_test_cache'] = [];

    expect(array_column($this->log->entries(AUDIT_T0 + 3600), 'count'))->toBe([1, 10]);
});

it('stores the cached counts of every entry when asked to settle them', function () {
    $GLOBALS['corex_test_ext_cache'] = true;
    ($this->refuse)(7, 'corex-forms', AUDIT_T0, 5);

    $this->log->settle(AUDIT_T0);
    $GLOBALS['corex_test_cache'] = [];

    expect($this->log->entries(AUDIT_T0)[0]['count'])->toBe(5);
});

it('prunes entries whose last refusal is older than 30 days', function () {
    ($this->refuse)(1, 'old', AUDIT_T0 - 31 * 86400);
    ($this->refuse)(2, 'fresh', AUDIT_T0 - 60);

    expect(array_column($this->log->entries(AUDIT_T0), 'section'))->toBe(['fresh']);
});

it('returns entries newest first, by their last refusal', function () {
    ($this->refuse)(1, 'corex-access', AUDIT_T0);
    ($this->refuse)(2, 'corex-settings', AUDIT_T0 + 10);
    ($this->refuse)(1, 'corex-access', AUDIT_T0 + 20);

    expect(array_column($this->log->entries(AUDIT_T0 + 20), 'section'))->toBe(['corex-access', 'corex-settings']);
});

it('reads an entry stored in the old shape as one refusal', function () {
    $GLOBALS['corex_test_options']['corex_access_audit_log'] = [
        'not-an-array',
        ['time' => AUDIT_T0, 'user' => 3, 'kind' => 'denied', 'section' => 'valid'],
        ['no_kind' => true],
    ];

    expect($this->log->entries(AUDIT_T0))->toBe([[
        'user' => 3, 'kind' => 'denied', 'section' => 'valid',
        'first' => AUDIT_T0, 'last' => AUDIT_T0, 'count' => 1,
    ]]);
});

it('folds refusals stored one by one before spec 112 into counted entries', function () {
    $GLOBALS['corex_test_options']['corex_access_audit_log'] = [
        ['time' => AUDIT_T0, 'user' => 3, 'kind' => 'denied', 'section' => 'corex-forms'],
        ['time' => AUDIT_T0 + 60, 'user' => 3, 'kind' => 'denied', 'section' => 'corex-forms'],
        ['time' => AUDIT_T0 + 4000, 'user' => 3, 'kind' => 'denied', 'section' => 'corex-forms'],
    ];

    expect(array_map(
        static fn (array $entry): array => [$entry['count'], $entry['first'], $entry['last']],
        $this->log->entries(AUDIT_T0 + 4000),
    ))->toBe([[1, AUDIT_T0 + 4000, AUDIT_T0 + 4000], [2, AUDIT_T0, AUDIT_T0 + 60]]);
});

it('records a screen refusal under the screen\'s page address, whichever key the refusal used', function () {
    Functions\when('get_current_user_id')->justReturn(7);

    $this->log->recordDenied('forms');
    $this->log->recordDenied('corex-forms');

    $entries = $this->log->entries();

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['section'])->toBe('corex-forms')
        ->and($entries[0]['count'])->toBe(2);
});
