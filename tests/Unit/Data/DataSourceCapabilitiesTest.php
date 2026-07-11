<?php

/**
 * Unit tests for granular data-source capability and field contracts (spec 068: FR-059–FR-064).
 *
 * @package Corex\Tests\Unit\Data
 */

declare(strict_types=1);

use Corex\Data\DataField;
use Corex\Data\DataSourceCapabilities;
use Corex\Config\Data\CapabilityAwareDataSource;
use Corex\Config\Data\DataAccessPolicy;
use Corex\Config\Data\DataRegistry;
use Corex\Config\Data\DataSource;
use Corex\Config\Data\DataSourceService;
use Corex\Config\Data\FieldAwareDataSource;
use Corex\Config\Data\WritableDataSource;
use Corex\Data\DataWriteAdapter;
use Corex\Operations\OperationResult;

it('serializes granular read write import export and migration capabilities', function () {
    $capabilities = new DataSourceCapabilities(
        sourceKey: 'submissions',
        read: true,
        query: true,
        schema: true,
        detail: true,
        create: false,
        update: true,
        delete: true,
        bulkUpdate: true,
        bulkDelete: false,
        importDryRun: true,
        importCommit: false,
        exportCsv: true,
        exportXlsx: false,
        migrations: false,
        rollback: false,
        maxPageSize: 100,
        permissionMap: ['read' => 'corex_manage_data', 'delete' => 'corex_manage_submissions'],
    );

    expect($capabilities->supports(DataSourceCapabilities::DELETE))->toBeTrue()
        ->and($capabilities->supports(DataSourceCapabilities::CREATE))->toBeFalse()
        ->and($capabilities->toArray())->toMatchArray([
            'source_key'     => 'submissions',
            'bulk_update'    => true,
            'import_dry_run' => true,
            'export_csv'     => true,
            'max_page_size'  => 100,
        ]);
});
it('rejects malformed source keys page sizes and permission maps', function () {
    $base = [
        'sourceKey'     => 'example',
        'read'          => true,
        'query'         => false,
        'schema'        => false,
        'detail'        => false,
        'create'        => false,
        'update'        => false,
        'delete'        => false,
        'bulkUpdate'    => false,
        'bulkDelete'    => false,
        'importDryRun'  => false,
        'importCommit'  => false,
        'exportCsv'     => false,
        'exportXlsx'    => false,
        'migrations'    => false,
        'rollback'      => false,
        'maxPageSize'   => 20,
        'permissionMap' => ['read' => 'corex_manage_data'],
    ];

    expect(fn () => new DataSourceCapabilities(...[...$base, 'sourceKey' => 'Bad Key']))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => new DataSourceCapabilities(...[...$base, 'maxPageSize' => 0]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => new DataSourceCapabilities(...[...$base, 'permissionMap' => ['read' => 'manage_options']]))
        ->toThrow(InvalidArgumentException::class);
});

it('describes typed field behavior including privacy validation and import aliases', function () {
    $field = new DataField(
        key: 'email',
        label: 'Email address',
        type: DataField::TYPE_EMAIL,
        required: true,
        nullable: false,
        readOnly: false,
        filterOperators: ['equals', 'contains'],
        sortable: true,
        personalDataClass: DataField::PERSONAL_CONTACT,
        validation: ['max_length' => 254],
        importAliases: ['email_address', 'e-mail'],
    );

    expect($field->toArray())->toMatchArray([
        'key'                 => 'email',
        'type'                => 'email',
        'personal_data_class' => 'contact',
        'import_aliases'      => ['email_address', 'e-mail'],
    ]);
});

it('rejects contradictory field nullability and unknown operators', function () {
    expect(fn () => new DataField(
        key: 'email',
        label: 'Email',
        type: DataField::TYPE_EMAIL,
        required: true,
        nullable: true,
        readOnly: false,
        filterOperators: ['equals'],
        sortable: true,
        personalDataClass: DataField::PERSONAL_CONTACT,
        validation: [],
        importAliases: [],
    ))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new DataField(
            key: 'email',
            label: 'Email',
            type: DataField::TYPE_EMAIL,
            required: false,
            nullable: true,
            readOnly: false,
            filterOperators: ['teleport'],
            sortable: true,
            personalDataClass: DataField::PERSONAL_CONTACT,
            validation: [],
            importAliases: [],
    ))->toThrow(InvalidArgumentException::class);
});

it('projects action visibility from both adapter support and actor permission', function () {
    $registry = new DataRegistry();
    $registry->register(dataCapabilitySource());
    $policy = new class() implements DataAccessPolicy {
        public function allows(int $actorId, string $ability): bool
        {
            return $actorId === 7 && $ability === 'corex_manage_data';
        }
    };

    $description = (new DataSourceService($registry, $policy))->describe(7, 'orders');

    expect($description['actions']['read'])->toMatchArray([
        'supported' => true, 'allowed' => true, 'visible' => true,
    ])->and($description['actions']['update'])->toMatchArray([
        'supported' => true, 'allowed' => true, 'visible' => true,
    ])->and($description['actions']['create'])->toMatchArray([
        'supported' => true, 'allowed' => false, 'visible' => false, 'reason' => 'permission',
    ])->and($description['actions']['delete'])->toMatchArray([
        'supported' => false, 'allowed' => false, 'visible' => false, 'reason' => 'unsupported',
    ]);
});

it('redacts source fields when the actor cannot read the source', function () {
    $registry = new DataRegistry();
    $registry->register(dataCapabilitySource());
    $policy = new class() implements DataAccessPolicy {
        public function allows(int $actorId, string $ability): bool
        {
            return false;
        }
    };

    $description = (new DataSourceService($registry, $policy))->describe(9, 'orders');

    expect($description['access'])->toBe('denied')
        ->and($description['fields'])->toBe([])
        ->and($description['actions']['read']['visible'])->toBeFalse();
});

it('hides declared write actions when the source has no real write adapter', function () {
    $registry = new DataRegistry();
    $registry->register(new class() implements DataSource, CapabilityAwareDataSource {
        public function key(): string { return 'claims'; }
        public function label(): string { return 'Claims'; }
        public function columns(): array { return []; }
        public function rows(int $page, int $perPage): array { return []; }
        public function total(): int { return 0; }
        public function delete(int $id): bool { return false; }
        public function capabilities(): DataSourceCapabilities
        {
            return new DataSourceCapabilities(
                sourceKey: 'claims', read: true, query: false, schema: false, detail: false,
                create: true, update: true, delete: true, bulkUpdate: true, bulkDelete: true,
                importDryRun: true, importCommit: true, exportCsv: true, exportXlsx: true,
                migrations: true, rollback: true, maxPageSize: 20,
                permissionMap: ['create' => 'corex_manage_data'],
            );
        }
    });
    $policy = new class() implements DataAccessPolicy {
        public function allows(int $actorId, string $ability): bool { return true; }
    };

    $actions = (new DataSourceService($registry, $policy))->describe(7, 'claims')['actions'];

    expect($actions['create'])->toMatchArray([
        'supported' => false, 'allowed' => false, 'visible' => false, 'reason' => 'no_adapter',
    ])->and($actions['bulk_delete']['visible'])->toBeFalse()
        ->and($actions['import_commit']['reason'])->toBe('no_adapter')
        ->and($actions['export_xlsx']['reason'])->toBe('no_adapter')
        ->and($actions['migrations']['reason'])->toBe('no_adapter')
        ->and($actions['rollback']['reason'])->toBe('no_adapter');
});

function dataCapabilitySource(): DataSource
{
    return new class() implements DataSource, CapabilityAwareDataSource, FieldAwareDataSource, WritableDataSource {
        public function key(): string { return 'orders'; }
        public function label(): string { return 'Orders'; }
        public function columns(): array { return [['id' => 'number', 'label' => 'Number']]; }
        public function rows(int $page, int $perPage): array { return []; }
        public function total(): int { return 0; }
        public function delete(int $id): bool { return false; }
        public function writeAdapter(): DataWriteAdapter
        {
            return new class() implements DataWriteAdapter {
                public function create(array $values): OperationResult { throw new RuntimeException('Not called.'); }
                public function update(array $recordIds, array $values): OperationResult { throw new RuntimeException('Not called.'); }
                public function delete(array $recordIds): OperationResult { throw new RuntimeException('Not called.'); }
            };
        }

        public function capabilities(): DataSourceCapabilities
        {
            return new DataSourceCapabilities(
                sourceKey: 'orders', read: true, query: true, schema: true, detail: true,
                create: true, update: true, delete: false, bulkUpdate: false, bulkDelete: false,
                importDryRun: false, importCommit: false, exportCsv: true, exportXlsx: false,
                migrations: false, rollback: false, maxPageSize: 25,
                permissionMap: [
                    'read' => 'corex_manage_data', 'query' => 'corex_manage_data',
                    'schema' => 'corex_manage_data', 'detail' => 'corex_manage_data',
                    'create' => 'corex_manage_data_models', 'update' => 'corex_manage_data',
                ],
            );
        }

        public function fields(): array
        {
            return [new DataField(
                key: 'number', label: 'Number', type: DataField::TYPE_TEXT, required: true,
                nullable: false, readOnly: false, filterOperators: ['equals', 'contains'], sortable: true,
                personalDataClass: DataField::PERSONAL_NONE, validation: [], importAliases: [],
            )];
        }
    };
}
