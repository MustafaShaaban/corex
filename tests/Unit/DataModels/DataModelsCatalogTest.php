<?php

/**
 * Unit tests for the pure Data Models catalog view model (spec 063, Phase 3). No WordPress.
 * Contract: present the REAL registered models + schema + counts; never fabricate a model or count.
 *
 * @package Corex\Tests\Unit\DataModels
 */

declare(strict_types=1);

use Corex\Config\DataModels\DataModelsCatalog;

/**
 * @return array{key:string,label:string,columns:list<array{id:string,label:string}>,total:int}
 */
function modelFixture(string $key, array $columns, int $total): array
{
    return ['key' => $key, 'label' => ucfirst($key), 'columns' => $columns, 'total' => $total];
}

it('catalogs each registered model with its schema and record count', function () {
    $catalog = (new DataModelsCatalog())->catalog([
        modelFixture('submissions', [['id' => 'date', 'label' => 'Date'], ['id' => 'form', 'label' => 'Form']], 12),
        modelFixture('locations', [['id' => 'name', 'label' => 'Name']], 3),
    ]);

    expect($catalog['count'])->toBe(2)
        ->and($catalog['totalRecords'])->toBe(15)
        ->and($catalog['isEmpty'])->toBeFalse()
        ->and($catalog['models'][0]['columnCount'])->toBe(2)
        ->and($catalog['models'][1]['columnCount'])->toBe(1);
});

it('reports an honest empty catalog when no models are registered', function () {
    $catalog = (new DataModelsCatalog())->catalog([]);

    expect($catalog['count'])->toBe(0)
        ->and($catalog['totalRecords'])->toBe(0)
        ->and($catalog['isEmpty'])->toBeTrue()
        ->and($catalog['models'])->toBe([]);
});

it('never reports a negative record count', function () {
    $catalog = (new DataModelsCatalog())->catalog([modelFixture('x', [], -5)]);

    expect($catalog['models'][0]['total'])->toBe(0)
        ->and($catalog['totalRecords'])->toBe(0);
});

it('preserves the real column schema verbatim', function () {
    $columns = [['id' => 'email', 'label' => 'Email address']];
    $catalog = (new DataModelsCatalog())->catalog([modelFixture('subscribers', $columns, 1)]);

    expect($catalog['models'][0]['columns'])->toBe($columns);
});
