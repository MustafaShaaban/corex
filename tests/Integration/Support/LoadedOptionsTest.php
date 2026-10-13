<?php

/**
 * Integration tests: an option CoreX reads on every request is kept among the options WordPress
 * loads with the site's, so reading it is no query.
 *
 * Three of CoreX's own stores saved their option with autoload off and read it on every request:
 * one query each, whether the row was there or not, since WordPress remembers a missing option
 * for one request only. `LoadedOptions` stores such an option loaded, and on an admin or a cron
 * request turns over a row an older release stored and writes a row that says nothing is stored
 * where there is none. Reading stays `get_option()`: a row that is not loaded is still read, and
 * costs the query it always did.
 *
 * @package Corex\Tests\Integration\Support
 */

declare(strict_types=1);

use Corex\Support\Options\LoadedOptions;
use Corex\Tests\Support\LoadedOptionsRun;
use Corex\Tests\Support\OptionRow;

const LOADED_OPTIONS_TEST_OPTION = 'corex_test_read_on_every_request';

beforeEach(function () {
    delete_option(LOADED_OPTIONS_TEST_OPTION);

    $this->loaded = new LoadedOptions();
    $this->loaded->register(LOADED_OPTIONS_TEST_OPTION, []);
});

afterEach(function () {
    delete_option(LOADED_OPTIONS_TEST_OPTION);
});

it('turns over a row an older release stored unloaded, and keeps what it holds', function () {
    OptionRow::storeUnloaded(LOADED_OPTIONS_TEST_OPTION, ['custom_slug' => 'team-door']);

    LoadedOptionsRun::onACronRequest($this->loaded);

    expect(OptionRow::autoload(LOADED_OPTIONS_TEST_OPTION))->toBe('on')
        ->and(wp_load_alloptions())->toHaveKey(LOADED_OPTIONS_TEST_OPTION)
        ->and(get_option(LOADED_OPTIONS_TEST_OPTION))->toBe(['custom_slug' => 'team-door']);
});

it('writes a row that says nothing is stored where there is none', function () {
    // The request has already asked for it, as CoreX's boot does, and WordPress remembers the miss.
    expect(get_option(LOADED_OPTIONS_TEST_OPTION, 'not stored'))->toBe('not stored');

    LoadedOptionsRun::onACronRequest($this->loaded);

    expect(OptionRow::autoload(LOADED_OPTIONS_TEST_OPTION))->toBe('on')
        ->and(get_option(LOADED_OPTIONS_TEST_OPTION, 'not stored'))->toBe([]);
});

it('writes nothing on a front-end request', function () {
    OptionRow::storeUnloaded(LOADED_OPTIONS_TEST_OPTION, ['custom_slug' => 'team-door']);

    $this->loaded->ensureLoaded();

    expect(is_admin())->toBeFalse()
        ->and(wp_doing_cron())->toBeFalse()
        ->and(OptionRow::autoload(LOADED_OPTIONS_TEST_OPTION))->toBe('off')
        // And the row is read as it always was.
        ->and(get_option(LOADED_OPTIONS_TEST_OPTION))->toBe(['custom_slug' => 'team-door']);
});

it('asks the database nothing once its options are loaded', function () {
    global $wpdb;

    LoadedOptionsRun::onACronRequest($this->loaded);
    // What every request does before any plugin is loaded.
    wp_load_alloptions();
    $before = $wpdb->num_queries;

    LoadedOptionsRun::onACronRequest($this->loaded);
    $value = get_option(LOADED_OPTIONS_TEST_OPTION);

    expect($wpdb->num_queries - $before)->toBe(0)
        ->and($value)->toBe([]);
});

it('keeps a value another request saved between its look and its write', function () {
    global $wpdb;

    // This request believes the option is missing, as any request that booted before the save does.
    get_option(LOADED_OPTIONS_TEST_OPTION);

    // The other request's save lands just before this one's insert reaches the database.
    $saveFromAnotherRequest = static function (string $sql) use ($wpdb, &$saveFromAnotherRequest): string {
        if (stripos(ltrim($sql), 'INSERT') !== 0 || ! str_contains($sql, LOADED_OPTIONS_TEST_OPTION)) {
            return $sql;
        }

        remove_filter('query', $saveFromAnotherRequest);
        $wpdb->insert($wpdb->options, [
            'option_name' => LOADED_OPTIONS_TEST_OPTION,
            'option_value' => maybe_serialize(['custom_slug' => 'saved-meanwhile']),
            'autoload' => 'on',
        ]);

        return $sql;
    };
    add_filter('query', $saveFromAnotherRequest);

    try {
        LoadedOptionsRun::onACronRequest($this->loaded);
    } finally {
        remove_filter('query', $saveFromAnotherRequest);
    }

    expect(get_option(LOADED_OPTIONS_TEST_OPTION))->toBe(['custom_slug' => 'saved-meanwhile']);
});

it('stores a value loaded, also when it is the value an unloaded row already held', function (mixed $held) {
    if ($held !== null) {
        OptionRow::storeUnloaded(LOADED_OPTIONS_TEST_OPTION, $held);
    }

    LoadedOptions::store(LOADED_OPTIONS_TEST_OPTION, ['custom_slug' => 'team-door']);

    expect(OptionRow::autoload(LOADED_OPTIONS_TEST_OPTION))->toBe('on')
        ->and(wp_load_alloptions())->toHaveKey(LOADED_OPTIONS_TEST_OPTION)
        ->and(get_option(LOADED_OPTIONS_TEST_OPTION))->toBe(['custom_slug' => 'team-door']);
})->with([
    'no row' => [null],
    'an unloaded row with another value' => [['custom_slug' => 'old-door']],
    'an unloaded row with the same value' => [['custom_slug' => 'team-door']],
]);
