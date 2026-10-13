<?php

/**
 * Integration test: a setting's override is an option WordPress loads with the site's options,
 * and a setting that has no override is answered without asking the database (DECISIONS #361).
 *
 * The config engine asked WordPress for a `corex_`-named option of every key it was read for.
 * WordPress remembers that an option is not stored for one request only, unless the site has a
 * persistent object cache, so each key with no override was a query on every request: nine of
 * the eleven CoreX's boot ran on a front-end request of a fresh site.
 *
 * `query.max` stands for any setting: it has a shipped default (500) and no screen writes it.
 *
 * @package Corex\Tests\Integration\Foundation
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Support\Config\ConfigInterface;

const COREX_TEST_OVERRIDE_OPTION = 'corex_query_max';

/**
 * Read a setting and count the queries the read made.
 *
 * WordPress's own memory of what is not stored is emptied first, so that an option another test
 * already asked for cannot be why no query is made.
 *
 * @return array{value: mixed, queries: int}
 */
function settingReadCountingQueries(string $key, mixed $default = null): array
{
    global $wpdb;

    wp_cache_delete('notoptions', 'options');
    $before = $wpdb->num_queries;
    $value = Boot::app()->container()->make(ConfigInterface::class)->get($key, $default);

    return ['value' => $value, 'queries' => $wpdb->num_queries - $before];
}

beforeEach(function () {
    $this->config = Boot::app()->container()->make(ConfigInterface::class);
    // An install somebody works on may hold this override: it is put back afterwards.
    $this->overrideOfThisInstall = get_option(COREX_TEST_OVERRIDE_OPTION, null);
    delete_option(COREX_TEST_OVERRIDE_OPTION);
});

afterEach(function () {
    delete_option(COREX_TEST_OVERRIDE_OPTION);

    if ($this->overrideOfThisInstall !== null) {
        update_option(COREX_TEST_OVERRIDE_OPTION, $this->overrideOfThisInstall);
    }
});

it('reads an override stored as an option', function () {
    update_option(COREX_TEST_OVERRIDE_OPTION, '25');

    expect($this->config->get('query.max'))->toBe('25');
});

it('falls back to the shipped default when the override is gone', function () {
    update_option(COREX_TEST_OVERRIDE_OPTION, '25');
    delete_option(COREX_TEST_OVERRIDE_OPTION);

    expect($this->config->get('query.max'))->toBe(500);
});

it('returns the fallback of whoever asked for a setting nothing holds', function () {
    expect($this->config->get('query.never_defined', 'fallback'))->toBe('fallback')
        ->and($this->config->has('query.never_defined'))->toBeFalse();
});

it('asks the database nothing for a setting that has no override', function () {
    $shipped = settingReadCountingQueries('query.max');
    $undefined = settingReadCountingQueries('query.never_defined', 'fallback');

    expect($shipped)->toBe(['value' => 500, 'queries' => 0])
        ->and($undefined)->toBe(['value' => 'fallback', 'queries' => 0]);
});

it('asks the database nothing for a setting whose override is stored', function () {
    update_option(COREX_TEST_OVERRIDE_OPTION, '25');

    expect(settingReadCountingQueries('query.max'))->toBe(['value' => '25', 'queries' => 0]);
});

it('reads an override a filter answers for an option that is not stored', function () {
    $answer = static fn (): string => '40';
    add_filter('pre_option_' . COREX_TEST_OVERRIDE_OPTION, $answer);

    try {
        expect($this->config->get('query.max'))->toBe('40');
    } finally {
        remove_filter('pre_option_' . COREX_TEST_OVERRIDE_OPTION, $answer);
    }
});

it('does not read an override WordPress was told not to load, and reads it once it is loaded', function () {
    // The third argument is what `wp option update corex_query_max 25 --autoload=no` passes.
    update_option(COREX_TEST_OVERRIDE_OPTION, '25', false);

    expect($this->config->get('query.max'))->toBe(500);

    wp_set_option_autoload(COREX_TEST_OVERRIDE_OPTION, true);

    expect($this->config->get('query.max'))->toBe('25');
});
