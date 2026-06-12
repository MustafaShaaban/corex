<?php

/**
 * Unit tests for the managed-table value + registry (spec 038: FR-001).
 *
 * @package Corex\Tests\Unit\Data
 */

declare(strict_types=1);

use Corex\Database\Schema\ManagedTable;
use Corex\Database\Schema\ManagedTables;

it('exposes its name, label and columns', function () {
    $table = new ManagedTable('invoices', 'Invoices', [
        ['id' => 'number', 'label' => 'Number'],
        ['id' => 'total', 'label' => 'Total'],
    ]);

    expect($table->name)->toBe('invoices')
        ->and($table->label)->toBe('Invoices')
        ->and($table->columns)->toHaveCount(2)
        ->and($table->columnIds())->toBe(['number', 'total']);
});

it('registers and lists managed tables, keyed by name (last wins)', function () {
    $registry = new ManagedTables();
    $registry->register(new ManagedTable('invoices', 'Invoices', []));
    $registry->register(new ManagedTable('clients', 'Clients', []));
    $registry->register(new ManagedTable('invoices', 'Invoices (v2)', []));

    expect($registry->all())->toHaveCount(2)
        ->and($registry->all()[0]->label)->toBe('Invoices (v2)')
        ->and($registry->all()[1]->name)->toBe('clients');
});
