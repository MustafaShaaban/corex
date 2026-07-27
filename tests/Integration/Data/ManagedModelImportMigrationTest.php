<?php

/**
 * The managed-model import and migration path, end to end (spec 074, FR-3.4–FR-3.9).
 *
 * Before this, `WritableDataSource` and `MigrationProvider` were implemented only by anonymous
 * classes inside the test suite: no shipped source could satisfy either, so the Import and
 * Migrations tabs were product surfaces nothing could ever reach. These tests exercise the real
 * classes against a real table — declaration, capability derivation, prepared writes, dry run,
 * commit, snapshot, apply, and a rollback that restores schema and rows together.
 *
 * @package Corex\Tests\Integration\Data
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Data\ManagedTableWriteAdapter;
use Corex\Config\Data\TableDataSource;
use Corex\Config\Data\WpTableDataReader;
use Corex\Config\Data\WpTableDataWriter;
use Corex\Config\Data\WritableDataSource;
use Corex\Config\Data\WritableTableDataSource;
use Corex\Config\DataModels\ManagedTableMigrationProvider;
use Corex\Config\DataModels\MigrationAwareDataSource;
use Corex\Config\DataModels\WpMigrationTableRunner;
use Corex\Data\DataSourceCapabilities;
use Corex\Database\Schema\ManagedTable;
use Corex\Database\Schema\Migrator;
use Corex\Database\Schema\Table;
use Corex\Operations\OperationResult;

const MANAGED_FIXTURE_TABLE = 'spec074_fixture';

/** A real table, created and dropped around each test, so nothing here touches shipped data. */
function managedFixtureTable(): ManagedTable
{
    return new ManagedTable(
        MANAGED_FIXTURE_TABLE,
        'Spec 074 fixture',
        [
            ['id' => 'email', 'label' => 'Email'],
            ['id' => 'status', 'label' => 'Status'],
            ['id' => 'note', 'label' => 'Note'],
        ],
        null,
        [
            ['id' => 'email', 'type' => 'email', 'required' => true, 'aliases' => ['e-mail', 'Email Address']],
            ['id' => 'note', 'type' => 'text'],
        ],
        ManagedTable::UNKNOWN_REJECT,
        [[
            'key' => 'spec074.index_email',
            'version' => '2026.07.1',
            'description' => 'Index the email column.',
            'plan' => ['ALTER TABLE {table} ADD INDEX spec074_email (email(190))'],
            'transactional' => false,
            'rollback_supported' => true,
        ]],
    );
}

function managedFixtureMigrator(): Migrator
{
    return Boot::app()->container()->make(Migrator::class);
}

beforeEach(function () {
    managedFixtureMigrator()->create(
        (new Table(MANAGED_FIXTURE_TABLE))->id()->string('email')->string('status', 20)->text('note')
    );
});

afterEach(function () {
    managedFixtureMigrator()->drop(MANAGED_FIXTURE_TABLE);
});

it('keeps a table that declares nothing read-only, and out of the write interfaces', function () {
    $migrator = managedFixtureMigrator();
    $readOnly = new TableDataSource(
        new ManagedTable(MANAGED_FIXTURE_TABLE, 'Read only', [['id' => 'email', 'label' => 'Email']]),
        new WpTableDataReader($migrator),
    );

    $capabilities = $readOnly->capabilities();

    expect($readOnly)->not->toBeInstanceOf(WritableDataSource::class)
        ->and($readOnly)->not->toBeInstanceOf(MigrationAwareDataSource::class)
        ->and($capabilities->supports(DataSourceCapabilities::IMPORT_DRY_RUN))->toBeFalse()
        ->and($capabilities->supports(DataSourceCapabilities::MIGRATIONS))->toBeFalse()
        ->and($capabilities->supports(DataSourceCapabilities::ROLLBACK))->toBeFalse()
        // Reading and exporting are always fine; it is writing that has to be asked for.
        ->and($capabilities->supports(DataSourceCapabilities::READ))->toBeTrue()
        ->and($capabilities->supports(DataSourceCapabilities::EXPORT_CSV))->toBeTrue();
});

it('derives every write capability from the declaration', function () {
    $source = managedFixtureSource();
    $capabilities = $source->capabilities();

    expect($source)->toBeInstanceOf(WritableDataSource::class)
        ->and($source)->toBeInstanceOf(MigrationAwareDataSource::class)
        ->and($capabilities->supports(DataSourceCapabilities::CREATE))->toBeTrue()
        ->and($capabilities->supports(DataSourceCapabilities::IMPORT_DRY_RUN))->toBeTrue()
        ->and($capabilities->supports(DataSourceCapabilities::IMPORT_COMMIT))->toBeTrue()
        ->and($capabilities->supports(DataSourceCapabilities::MIGRATIONS))->toBeTrue()
        ->and($capabilities->supports(DataSourceCapabilities::ROLLBACK))->toBeTrue();
});

it('marks only the declared fields writable, and carries their aliases', function () {
    $fields = [];
    foreach (managedFixtureSource()->fields() as $field) {
        $fields[$field->key] = $field;
    }

    expect($fields['email']->readOnly)->toBeFalse()
        ->and($fields['email']->required)->toBeTrue()
        ->and($fields['email']->importAliases)->toBe(['e-mail', 'email_address'])
        ->and($fields['note']->readOnly)->toBeFalse()
        // `status` was not declared, so nothing may write it — the whole point of opting in.
        ->and($fields['status']->readOnly)->toBeTrue();
});

it('writes only declared columns, whatever the payload contains', function () {
    $adapter = managedFixtureAdapter();

    $created = $adapter->create([
        'email' => 'ada@example.com',
        'note' => 'imported',
        // Undeclared: must be ignored rather than written.
        'status' => 'confirmed',
    ]);

    expect($created->state)->toBe(OperationResult::STATE_COMPLETED)
        ->and($created->affectedIds)->toHaveCount(1);

    $row = managedFixtureRow((int) $created->affectedIds[0]);

    expect($row['email'])->toBe('ada@example.com')
        ->and($row['note'])->toBe('imported')
        ->and($row['status'])->toBe('');
});

it('updates and deletes through the same bounded adapter', function () {
    $adapter = managedFixtureAdapter();
    $id = (int) $adapter->create(['email' => 'grace@example.com'])->affectedIds[0];

    $updated = $adapter->update([$id], ['note' => 'checked', 'status' => 'ignored']);
    $row = managedFixtureRow($id);

    expect($updated->state)->toBe(OperationResult::STATE_COMPLETED)
        ->and($row['note'])->toBe('checked')
        ->and($row['status'])->toBe('');

    expect($adapter->delete([$id])->state)->toBe(OperationResult::STATE_COMPLETED)
        ->and(managedFixtureRow($id))->toBeNull();
});

it('applies the table insert defaults so an imported row is usable', function () {
    $table = new ManagedTable(
        MANAGED_FIXTURE_TABLE,
        'With defaults',
        [['id' => 'email', 'label' => 'Email'], ['id' => 'status', 'label' => 'Status']],
        null,
        [['id' => 'email', 'type' => 'email', 'required' => true]],
        ManagedTable::UNKNOWN_REJECT,
        [],
        ['status' => 'pending'],
    );
    $adapter = new ManagedTableWriteAdapter($table, new WpTableDataWriter(managedFixtureMigrator()));

    // The payload tries to choose its own status; the default has to win, or the "you cannot
    // import a pre-confirmed list" rule would be bypassed by simply naming the column.
    $id = (int) $adapter->create(['email' => 'ada@example.com', 'status' => 'confirmed'])->affectedIds[0];

    expect(managedFixtureRow($id)['status'])->toBe('pending')
        ->and($table->insertDefaults())->toBe(['status' => 'pending']);
});

it('ignores an insert default for a column that is writable or does not exist', function () {
    $table = new ManagedTable(
        MANAGED_FIXTURE_TABLE,
        'Odd defaults',
        [['id' => 'email', 'label' => 'Email']],
        null,
        [['id' => 'email', 'type' => 'email']],
        ManagedTable::UNKNOWN_REJECT,
        [],
        ['email' => 'forced@example.com', 'nope' => 'x'],
    );

    expect($table->insertDefaults())->toBe([]);
});

it('refuses a write that names no declared column rather than writing an empty row', function () {
    expect(managedFixtureAdapter()->create(['status' => 'confirmed'])->state)
        ->toBe(OperationResult::STATE_FAILED);
});

it('applies a declared migration and can roll it back to the snapshot', function () {
    $provider = new ManagedTableMigrationProvider(
        managedFixtureTable(),
        new WpMigrationTableRunner(managedFixtureMigrator()),
    );
    $definition = $provider->definitions()[0];
    $adapter = managedFixtureAdapter();
    $id = (int) $adapter->create(['email' => 'before@example.com'])->affectedIds[0];

    $snapshot = $provider->snapshot($definition);
    expect($snapshot)->not->toBe('');

    $applied = $provider->execute($definition, $snapshot, false);
    expect($applied->state)->toBe(OperationResult::STATE_COMPLETED)
        ->and(managedFixtureIndexes())->toContain('spec074_email');

    // Written after the migration: the snapshot predates it, so a rollback discards it — which is
    // exactly what the Migrations screen tells the operator before they confirm.
    $adapter->create(['email' => 'after@example.com']);

    $rolledBack = $provider->execute($definition, $snapshot, true);

    expect($rolledBack->state)->toBe(OperationResult::STATE_COMPLETED)
        // Schema *and* rows are back: the index is gone and so is the later row.
        ->and(managedFixtureIndexes())->not->toContain('spec074_email')
        ->and(managedFixtureRow($id))->not->toBeNull()
        ->and(managedFixtureCount())->toBe(1);
});

it('refuses to apply a migration it could not snapshot', function () {
    $provider = new ManagedTableMigrationProvider(
        managedFixtureTable(),
        new WpMigrationTableRunner(managedFixtureMigrator()),
    );

    $result = $provider->execute($provider->definitions()[0], '', false);

    expect($result->state)->toBe(OperationResult::STATE_BLOCKED)
        ->and($result->message)->toContain('not applied');
});

it('refuses a rollback the definition never promised', function () {
    $oneWay = new ManagedTable(
        MANAGED_FIXTURE_TABLE,
        'One way',
        [['id' => 'email', 'label' => 'Email']],
        null,
        [],
        ManagedTable::UNKNOWN_REJECT,
        [[
            'key' => 'spec074.one_way',
            'version' => '1',
            'description' => 'A migration with no way back.',
            'plan' => ['SELECT 1 FROM {table} LIMIT 1'],
            'transactional' => false,
            'rollback_supported' => false,
        ]],
    );
    $provider = new ManagedTableMigrationProvider($oneWay, new WpMigrationTableRunner(managedFixtureMigrator()));

    expect($oneWay->supportsRollback())->toBeFalse()
        ->and($provider->execute($provider->definitions()[0], 'anything', true)->state)
        ->toBe(OperationResult::STATE_BLOCKED);
});

it('registers the newsletter subscribers table as the shipped importable model', function () {
    // The reference path this spec promises. Newsletter is an optional add-on, so this asserts the
    // declaration itself rather than requiring the add-on to be active in the test run.
    $table = (new Corex\Newsletter\Subscriber\SubscriberTable())->managed();

    expect($table->isWritable())->toBeTrue()
        ->and($table->supportsImport())->toBeTrue()
        ->and($table->supportsMigrations())->toBeTrue()
        ->and($table->supportsRollback())->toBeTrue()
        // Confirmation state belongs to the double opt-in flow; an import must not be able to
        // mark a list as already consented.
        ->and($table->writableFieldIds())->not->toContain('status');
});

it('keeps every audit and system table read-only', function () {
    $readOnly = [
        (new Corex\Config\Activity\ActivityTable())->managed(),
        (new Corex\Config\Jobs\JobTable())->managed(),
        (new Corex\Config\Notifications\NotificationTable())->managed(),
        (new Corex\Config\Blog\ReadingEventTable())->managed(),
    ];

    foreach ($readOnly as $table) {
        expect($table->isWritable())->toBeFalse()
            ->and($table->supportsImport())->toBeFalse()
            ->and($table->supportsMigrations())->toBeFalse();
    }
});

// --- fixtures -------------------------------------------------------------------------------

function managedFixtureSource(): WritableTableDataSource
{
    $migrator = managedFixtureMigrator();

    return new WritableTableDataSource(
        managedFixtureTable(),
        new WpTableDataReader($migrator),
        new WpTableDataWriter($migrator),
        new WpMigrationTableRunner($migrator),
    );
}

function managedFixtureAdapter(): ManagedTableWriteAdapter
{
    return new ManagedTableWriteAdapter(
        managedFixtureTable(),
        new WpTableDataWriter(managedFixtureMigrator()),
    );
}

/** @return array<string,scalar>|null */
function managedFixtureRow(int $id): ?array
{
    return (new WpTableDataReader(managedFixtureMigrator()))
        ->find(MANAGED_FIXTURE_TABLE, ['email', 'status', 'note'], $id);
}

function managedFixtureCount(): int
{
    return (new WpTableDataReader(managedFixtureMigrator()))->total(MANAGED_FIXTURE_TABLE);
}

/** @return list<string> */
function managedFixtureIndexes(): array
{
    global $wpdb;

    $table = managedFixtureMigrator()->fullName(MANAGED_FIXTURE_TABLE);
    $rows = $wpdb->get_results("SHOW INDEX FROM `{$table}`", ARRAY_A);

    return array_values(array_unique(array_map(
        static fn (array $row): string => (string) ($row['Key_name'] ?? ''),
        is_array($rows) ? $rows : [],
    )));
}
