<?php

/**
 * What a repeated refusal costs the database, and what the audit tab then says (spec 112, User
 * Story 2, FR-009 to FR-012, SC-003). On a real WordPress: the writes counted are the queries
 * WordPress sends for the log's option.
 *
 * The install's own log is kept aside and put back afterwards.
 *
 * @package Corex\Tests\Integration\Access
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Access\AccessAuditLog;
use Corex\Config\Access\AccessScreen;

const AUDIT_WRITES_OPTION = 'corex_access_audit_log';

beforeEach(function () {
    $this->saved = get_option(AUDIT_WRITES_OPTION, null);
    update_option(AUDIT_WRITES_OPTION, [], false);

    $this->log    = Boot::app()->container()->make(AccessAuditLog::class);
    $this->writes = 0;
    $this->count  = function (string $query): string {
        if (preg_match('/^\s*(UPDATE|INSERT)\b/i', $query) === 1 && str_contains($query, AUDIT_WRITES_OPTION)) {
            $this->writes++;
        }

        return $query;
    };
    $this->wasUsingCache = wp_using_ext_object_cache();
});

afterEach(function () {
    remove_filter('query', $this->count);
    wp_using_ext_object_cache($this->wasUsingCache);
    wp_cache_flush();

    $this->saved === null
        ? delete_option(AUDIT_WRITES_OPTION)
        : update_option(AUDIT_WRITES_OPTION, $this->saved, false);
});

it('updates one entry per refusal and adds none, without a persistent object cache', function () {
    wp_using_ext_object_cache(false);
    add_filter('query', $this->count);

    for ($i = 0; $i < 10; $i++) {
        $this->log->record('denied', 'corex-forms', 990001, time());
    }

    remove_filter('query', $this->count);
    expect($this->writes)->toBe(10)
        ->and($this->log->entries())->toHaveCount(1)
        ->and($this->log->entries()[0]['count'])->toBe(10);
});

it('writes once for the hour with a persistent object cache, and the count still reads true', function () {
    wp_using_ext_object_cache(true);
    add_filter('query', $this->count);

    for ($i = 0; $i < 10; $i++) {
        $this->log->record('denied', 'corex-forms', 990001, time());
    }

    remove_filter('query', $this->count);
    expect($this->writes)->toBe(1)
        ->and($this->log->entries()[0]['count'])->toBe(10);
});

it('keeps every other person listed after one person is refused a thousand times in an hour', function () {
    $this->log->record('denied', 'corex-access', 990002, time());
    $this->log->record('denied', 'corex-releases', 990003, time());

    for ($i = 0; $i < 1000; $i++) {
        $this->log->record('denied', 'corex-forms', 990001, time());
    }

    $entries = $this->log->entries();
    $mine    = array_values(array_filter($entries, static fn (array $entry): bool => $entry['user'] === 990001));

    expect(array_column($entries, 'user'))->toContain(990002, 990003)
        ->and($mine)->toHaveCount(1)
        ->and($mine[0]['count'])->toBe(1000);
});

it('shows on the audit tab how many times, from the first time to the last', function () {
    $first = time() - 600;
    $this->log->record('denied', 'corex-forms', 990001, $first);
    $this->log->record('denied', 'corex-forms', 990001, $first + 300);
    $this->log->record('denied', 'corex-forms', 990001, $first + 500);

    // The tab holds every access event since spec 117; the person filter keeps it to this one's.
    $_GET['person'] = '990001';
    $screen = Boot::app()->container()->make(AccessScreen::class);
    try {
        $html = (string) (new ReflectionMethod($screen, 'auditTab'))->invoke($screen);
    } finally {
        unset($_GET['person']);
    }

    expect($html)->toContain('3 times, from <time')
        ->and(substr_count($html, 'class="corex-access__event"'))->toBe(1)
        ->and($html)->toContain('Forms &amp; Flows');
});
