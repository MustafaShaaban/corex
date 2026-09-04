<?php

/**
 * @package Corex\Tests\Unit\Database
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Database\Schema\NetworkMigrationResult;
use Corex\Database\Schema\SchemaComponent;
use Corex\Database\Schema\SchemaRegistry;
use Corex\Database\Schema\SiteMigrationOutcome;
use Corex\Database\Schema\SiteMigrationResult;
use Corex\Database\Schema\Table;
use Corex\Database\Schema\WpOptionSchemaVersionStore;

it('keeps an explicit schema option name and ordered table declarations', function () {
    $component = new SchemaComponent(
        'product-foundation',
        '3',
        [new Table('activity'), new Table('jobs')],
        'corex_product_foundation_schema_version',
    );

    expect($component->id)->toBe('product-foundation')
        ->and($component->version)->toBe('3')
        ->and($component->optionName)->toBe('corex_product_foundation_schema_version')
        ->and(array_map(static fn (Table $table): string => $table->name, $component->tables))
        ->toBe(['activity', 'jobs']);
});

it('registers components by id with the last declaration winning', function () {
    $registry = new SchemaRegistry();
    $registry->register(new SchemaComponent('one', '1', [new Table('first')], 'one_version'));
    $registry->register(new SchemaComponent('two', '1', [new Table('second')], 'two_version'));
    $replacement = new SchemaComponent('one', '2', [new Table('replacement')], 'one_version');
    $registry->register($replacement);

    expect($registry->all())->toBe([$replacement, $registry->get('two')])
        ->and($registry->get('one'))->toBe($replacement)
        ->and($registry->get('missing'))->toBeNull()
        ->and($registry->tableNames())->toBe(['replacement', 'second']);
});

it('stores schema versions in non-autoloaded site options', function () {
    Functions\expect('get_option')
        ->once()
        ->with('corex_schema_version', '')
        ->andReturn(3);
    Functions\expect('update_option')
        ->once()
        ->with('corex_schema_version', '4', false);
    $store = new WpOptionSchemaVersionStore();

    expect($store->get('corex_schema_version'))->toBe('3');
    $store->put('corex_schema_version', '4');
});

it('summarizes migrated failed and skipped site outcomes', function () {
    $migrated = new SiteMigrationResult(1, SiteMigrationOutcome::Migrated, ['foundation'], ['activity']);
    $current = new SiteMigrationResult(2, SiteMigrationOutcome::AlreadyCurrent);
    $failed = new SiteMigrationResult(3, SiteMigrationOutcome::Failed, error: 'broken');
    $skipped = new SiteMigrationResult(4, SiteMigrationOutcome::Skipped);
    $network = new NetworkMigrationResult([$migrated, $current, $failed, $skipped], 4, false);

    expect($migrated->succeeded())->toBeTrue()
        ->and($current->succeeded())->toBeTrue()
        ->and($failed->succeeded())->toBeFalse()
        ->and($network->migrated())->toBe(1)
        ->and($network->failed())->toBe(1)
        ->and($network->skipped())->toBe(1)
        ->and($network->failures())->toBe([$failed])
        ->and($network->nextOffset)->toBe(4)
        ->and($network->complete)->toBeFalse();
});
