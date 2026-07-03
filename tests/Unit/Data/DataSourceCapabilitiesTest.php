<?php

/**
 * Unit tests for granular data-source capability and field contracts (spec 068: FR-059–FR-064).
 *
 * @package Corex\Tests\Unit\Data
 */

declare(strict_types=1);

use Corex\Data\DataField;
use Corex\Data\DataSourceCapabilities;

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
