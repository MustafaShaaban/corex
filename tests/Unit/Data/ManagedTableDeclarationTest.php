<?php

/**
 * A managed table declares what may be done to it (spec 074, FR-3.4).
 *
 * Import and migrations were product tabs no registered model could satisfy, because there was no
 * way for a model to opt in. These tests pin the opt-in: read-only unless the table says otherwise,
 * writes confined to explicitly declared fields, and capability flags derived from the declaration
 * rather than asserted separately — so a source cannot claim something it did not declare.
 *
 * @package Corex\Tests\Unit\Data
 */

declare(strict_types=1);

use Corex\Data\DataField;
use Corex\Database\Schema\ManagedTable;

function subscriberColumns(): array
{
    return [
        ['id' => 'email', 'label' => 'Email'],
        ['id' => 'name', 'label' => 'Name'],
        ['id' => 'topics', 'label' => 'Topics'],
        ['id' => 'consent', 'label' => 'Consent'],
        ['id' => 'created_at', 'label' => 'Created'],
    ];
}

function subscriberMigration(bool $rollback = true): array
{
    return [
        'key' => 'subscribers.add_locale',
        'version' => '2026.07.1',
        'description' => 'Add a locale column so subscribers can be segmented by language.',
        'plan' => ['ALTER TABLE subscribers ADD COLUMN locale VARCHAR(12) NOT NULL DEFAULT \'\''],
        'transactional' => false,
        'rollback_supported' => $rollback,
    ];
}

it('is read-only unless the table declares otherwise', function () {
    $table = new ManagedTable('invoices', 'Invoices', subscriberColumns());

    expect($table->isWritable())->toBeFalse()
        ->and($table->supportsImport())->toBeFalse()
        ->and($table->supportsMigrations())->toBeFalse()
        ->and($table->supportsRollback())->toBeFalse()
        ->and($table->writableFieldIds())->toBe([]);
});

it('becomes writable and importable when it declares writable fields', function () {
    $table = new ManagedTable(
        'subscribers',
        'Subscribers',
        subscriberColumns(),
        null,
        [
            ['id' => 'email', 'type' => 'email', 'required' => true],
            ['id' => 'name', 'type' => 'text'],
        ],
    );

    expect($table->isWritable())->toBeTrue()
        ->and($table->supportsImport())->toBeTrue()
        ->and($table->writableFieldIds())->toBe(['email', 'name']);
});

it('carries the import aliases and validation the source declares', function () {
    $table = new ManagedTable(
        'subscribers',
        'Subscribers',
        subscriberColumns(),
        null,
        [[
            'id' => 'email',
            'type' => 'email',
            'required' => true,
            'aliases' => ['e-mail', 'Email Address'],
            'validation' => ['max_length' => 190],
        ]],
    );

    $field = $table->writableField('email');

    expect($field['type'])->toBe('email')
        ->and($field['required'])->toBeTrue()
        // Aliases are normalised so a CSV header matches case- and separator-insensitively.
        ->and($field['aliases'])->toBe(['e-mail', 'email_address'])
        ->and($field['validation'])->toBe(['max_length' => 190]);
});

it('refuses to declare a writable field that is not one of its columns', function () {
    expect(fn () => new ManagedTable(
        'subscribers',
        'Subscribers',
        subscriberColumns(),
        null,
        [['id' => 'not_a_column', 'type' => 'text']],
    ))->toThrow(InvalidArgumentException::class);
});

it('rejects an unknown-column policy it does not implement', function () {
    expect(fn () => new ManagedTable(
        'subscribers',
        'Subscribers',
        subscriberColumns(),
        null,
        [['id' => 'email', 'type' => 'email']],
        'improvise',
    ))->toThrow(InvalidArgumentException::class);
});

it('rejects unknown CSV columns by default', function () {
    $table = new ManagedTable('subscribers', 'Subscribers', subscriberColumns(), null, [['id' => 'email', 'type' => 'email']]);

    // The safe default: a column nobody declared is a mistake worth stopping on, not a value to
    // guess at. Ignoring has to be asked for.
    expect($table->unknownColumns)->toBe(ManagedTable::UNKNOWN_REJECT);
});

it('supports migrations only when it declares them', function () {
    $without = new ManagedTable('subscribers', 'Subscribers', subscriberColumns());
    $with = new ManagedTable('subscribers', 'Subscribers', subscriberColumns(), null, [], ManagedTable::UNKNOWN_REJECT, [subscriberMigration()]);

    expect($without->supportsMigrations())->toBeFalse()
        ->and($with->supportsMigrations())->toBeTrue()
        ->and($with->migrations)->toHaveCount(1);
});

it('claims rollback only when a declaration actually provides one', function () {
    $reversible = new ManagedTable('t', 'T', subscriberColumns(), null, [], ManagedTable::UNKNOWN_REJECT, [subscriberMigration(true)]);
    $oneWay = new ManagedTable('t', 'T', subscriberColumns(), null, [], ManagedTable::UNKNOWN_REJECT, [subscriberMigration(false)]);

    expect($reversible->supportsRollback())->toBeTrue()
        // A one-way migration must not advertise a rollback that would do nothing.
        ->and($oneWay->supportsRollback())->toBeFalse()
        ->and($oneWay->supportsMigrations())->toBeTrue();
});

it('rejects a migration declaration that is missing its plan', function () {
    expect(fn () => new ManagedTable(
        't',
        'T',
        subscriberColumns(),
        null,
        [],
        ManagedTable::UNKNOWN_REJECT,
        [['key' => 'broken', 'version' => '1', 'description' => 'No plan at all', 'plan' => []]],
    ))->toThrow(InvalidArgumentException::class);
});

it('keeps its existing constructor working for every read-only caller', function () {
    // Eight first-party tables already construct this with four arguments; adding the declaration
    // must not touch any of them.
    $table = new ManagedTable('activity_events', 'Activity events', [['id' => 'kind', 'label' => 'Kind']], 'corex');

    // `displayLabel()` is translation-aware and belongs to ManagedTableTest, which stubs i18n;
    // what matters here is that the fourth argument still lands on the text domain and the table
    // still comes out read-only.
    expect($table->columnIds())->toBe(['kind'])
        ->and($table->label)->toBe('Activity events')
        ->and($table->isWritable())->toBeFalse()
        ->and($table->unknownColumns)->toBe(ManagedTable::UNKNOWN_REJECT);
});

// Spec 109, slice 1 (FR-016, FR-017): a table says which of its columns hold personal data.
// Every column of every managed table was given the class "none" by the source that read it,
// so a subscriber's email address was exported with no confirmation and no right asked.

it('counts every column as undeclared until the table says what it holds', function () {
    $table = new ManagedTable('invoices', 'Invoices', subscriberColumns());

    expect($table->personalDataClass('email'))->toBe(DataField::PERSONAL_UNDECLARED)
        ->and($table->personalDataClass('created_at'))->toBe(DataField::PERSONAL_UNDECLARED);
});

it('answers the declared class for a column, and undeclared for one it left out', function () {
    $table = new ManagedTable(
        'subscribers',
        'Subscribers',
        subscriberColumns(),
        personalData: [
            'email' => DataField::PERSONAL_CONTACT,
            'name' => DataField::PERSONAL_IDENTITY,
            'consent' => DataField::PERSONAL_NONE,
        ],
    );

    expect($table->personalDataClass('email'))->toBe(DataField::PERSONAL_CONTACT)
        ->and($table->personalDataClass('name'))->toBe(DataField::PERSONAL_IDENTITY)
        ->and($table->personalDataClass('consent'))->toBe(DataField::PERSONAL_NONE)
        ->and($table->personalDataClass('topics'))->toBe(DataField::PERSONAL_UNDECLARED);
});

it('refuses a class a table cannot declare', function (string $class) {
    new ManagedTable('subscribers', 'Subscribers', subscriberColumns(), personalData: ['email' => $class]);
})->throws(InvalidArgumentException::class, 'personal-data class')->with([
    'one nothing knows' => ['private'],
    // "Undeclared" is what a column is when nothing was said. Saying it would be a way to look
    // as if the question had been answered.
    'the one that means nothing was said' => [DataField::PERSONAL_UNDECLARED],
]);

it('refuses a personal-data class for a column the table does not have', function () {
    new ManagedTable('subscribers', 'Subscribers', subscriberColumns(), personalData: ['phone' => DataField::PERSONAL_CONTACT]);
})->throws(InvalidArgumentException::class, 'not one of its columns');
