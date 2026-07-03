<?php

/**
 * Unit tests for capability-aware data-source registration without breaking legacy sources.
 *
 * @package Corex\Tests\Unit\Data
 */

declare(strict_types=1);

use Corex\Access\CorexAbility;
use Corex\Config\Data\CapabilityAwareDataSource;
use Corex\Config\Data\DataRegistry;
use Corex\Config\Data\DataSource;
use Corex\Config\Data\FieldAwareDataSource;
use Corex\Data\DataField;
use Corex\Data\DataSourceCapabilities;

function legacyDataSource(): DataSource
{
    return new class implements DataSource {
        public function key(): string
        {
            return 'legacy';
        }

        public function label(): string
        {
            return 'Legacy';
        }

        public function columns(): array
        {
            return [['id' => 'name', 'label' => 'Name']];
        }

        public function rows(int $page, int $perPage): array
        {
            return [];
        }

        public function total(): int
        {
            return 0;
        }

        public function delete(int $id): bool
        {
            return false;
        }
    };
}

function capableDataSource(): DataSource
{
    return new class implements DataSource, CapabilityAwareDataSource, FieldAwareDataSource {
        public function key(): string
        {
            return 'products';
        }

        public function label(): string
        {
            return 'Products';
        }

        public function columns(): array
        {
            return [['id' => 'sku', 'label' => 'SKU']];
        }

        public function rows(int $page, int $perPage): array
        {
            return [];
        }

        public function total(): int
        {
            return 0;
        }

        public function delete(int $id): bool
        {
            return true;
        }

        public function capabilities(): DataSourceCapabilities
        {
            return new DataSourceCapabilities(
                sourceKey: 'products',
                read: true,
                query: true,
                schema: true,
                detail: true,
                create: true,
                update: true,
                delete: true,
                bulkUpdate: false,
                bulkDelete: false,
                importDryRun: false,
                importCommit: false,
                exportCsv: true,
                exportXlsx: false,
                migrations: false,
                rollback: false,
                maxPageSize: 50,
                permissionMap: ['read' => CorexAbility::MANAGE_DATA],
            );
        }

        public function fields(): array
        {
            return [new DataField(
                key: 'sku',
                label: 'SKU',
                type: DataField::TYPE_TEXT,
                required: true,
                nullable: false,
                readOnly: false,
                filterOperators: ['equals'],
                sortable: true,
                personalDataClass: DataField::PERSONAL_NONE,
                validation: [],
                importAliases: [],
            )];
        }
    };
}

it('uses explicit capabilities and typed fields when a source declares them', function () {
    $registry = new DataRegistry();
    $registry->register(capableDataSource());

    expect($registry->capabilities('products')?->create)->toBeTrue()
        ->and($registry->fields('products')[0])->toBeInstanceOf(DataField::class)
        ->and($registry->describe()[0]['capabilities']['max_page_size'])->toBe(50);
});
it('keeps a legacy source readable with conservative inferred capabilities', function () {
    $registry = new DataRegistry();
    $registry->register(legacyDataSource());

    $capabilities = $registry->capabilities('legacy');

    expect($capabilities?->read)->toBeTrue()
        ->and($capabilities?->delete)->toBeFalse()
        ->and($registry->fields('legacy')[0]->readOnly)->toBeTrue();
});
