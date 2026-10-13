<?php

/**
 * Integration test: the schema version store against WordPress's own options (DECISIONS #360).
 *
 * Reading several versions in one statement, and reading again what another request has
 * written, are both things WordPress's option cache does or does not do. A substitute for
 * `get_option()` would only show what the substitute was told to answer.
 *
 * @package Corex\Tests\Integration\Schema
 */

declare(strict_types=1);

use Corex\Database\Schema\WpOptionSchemaVersionStore;

const STORE_TEST_OPTIONS = [
    'corex_test_store_a_schema_version',
    'corex_test_store_b_schema_version',
    'corex_test_store_c_schema_version',
];

/**
 * Write a version as a process that is not this one would leave it: in the database, with
 * nothing told to this process's memory of the option.
 */
function versionWrittenByAnotherRequest(string $optionName, string $version): void
{
    global $wpdb;

    $wpdb->query($wpdb->prepare(
        "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')
         ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)",
        $optionName,
        $version,
    ));
}

/**
 * @param Closure(): mixed $read
 *
 * @return array{0: mixed, 1: list<string>} What was read, and the statements that name a test option.
 */
function statementsOfARead(Closure $read): array
{
    $statements = [];
    $listen = static function (string $sql) use (&$statements): string {
        if (str_contains($sql, 'corex_test_store_')) {
            $statements[] = $sql;
        }

        return $sql;
    };
    add_filter('query', $listen);

    try {
        return [$read(), $statements];
    } finally {
        remove_filter('query', $listen);
    }
}

beforeEach(function () {
    $this->store = new WpOptionSchemaVersionStore();
    $this->store->forget(STORE_TEST_OPTIONS);
});

afterEach(function () {
    array_map(delete_option(...), STORE_TEST_OPTIONS);
});

it('reads several versions in one statement, and an empty string where none is stored', function () {
    [$a, $b, $missing] = STORE_TEST_OPTIONS;
    versionWrittenByAnotherRequest($a, '3');
    versionWrittenByAnotherRequest($b, '1');

    [$versions, $statements] = statementsOfARead(fn (): array => $this->store->getMany(STORE_TEST_OPTIONS));

    expect($versions)->toBe([$a => '3', $b => '1', $missing => ''])
        ->and($statements)->toHaveCount(1);
});

it('stores a version with autoload off, so it is not loaded on a request that does not ask for it', function () {
    global $wpdb;

    [$a] = STORE_TEST_OPTIONS;
    $this->store->put($a, '4');

    $autoload = $wpdb->get_var($wpdb->prepare("SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $a));

    expect($autoload)->toBe('off')
        ->and($this->store->get($a))->toBe('4');
});

it('answers from what the request read until it is told to forget, then reads what is stored', function (string $versionReadFirst) {
    [$a] = STORE_TEST_OPTIONS;

    if ($versionReadFirst !== '') {
        versionWrittenByAnotherRequest($a, $versionReadFirst);
    }

    $first = $this->store->getMany([$a]);
    versionWrittenByAnotherRequest($a, '2');
    $remembered = $this->store->getMany([$a]);
    $this->store->forget([$a]);
    $afterForgetting = $this->store->getMany([$a]);

    expect($first)->toBe([$a => $versionReadFirst])
        ->and($remembered)->toBe([$a => $versionReadFirst])
        ->and($afterForgetting)->toBe([$a => '2']);
})->with([
    'a version that was stored' => '1',
    'a version that was not stored yet' => '',
]);
