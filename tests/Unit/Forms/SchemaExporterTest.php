<?php

/**
 * Unit tests for the schema exporter: the resolved PHP schema → the JSON-able shape
 * the shared client validator consumes (one source of truth, front + back).
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Corex\Forms\Schema\SchemaExporter;
use Corex\Forms\Schema\SchemaResolver;
use Corex\Forms\Validation\RuleRegistry;

function exportFields(array $fields): array
{
    $schema = (new SchemaResolver(new RuleRegistry()))->resolve($fields);

    return (new SchemaExporter())->toArray($schema);
}

it('exports each field with the same name, type, required flag, and rules', function () {
    $exported = exportFields([
        'email' => ['type' => 'email', 'label' => 'Email', 'rules' => ['required', 'email', 'max:120']],
        'note'  => ['type' => 'textarea', 'label' => 'Note', 'rules' => ['max:500']],
    ]);

    expect($exported)->toHaveCount(2);

    expect($exported[0])->toMatchArray([
        'name'     => 'email',
        'type'     => 'email',
        'label'    => 'Email',
        'required' => true,
    ]);
    expect($exported[0]['rules'])->toBe([
        ['rule' => 'required', 'params' => []],
        ['rule' => 'email', 'params' => []],
        ['rule' => 'max', 'params' => ['120']],
    ]);

    expect($exported[1]['required'])->toBeFalse();
});

it('produces a JSON-serializable list (so it round-trips to the client)', function () {
    $exported = exportFields([
        'name' => ['rules' => ['required', 'min:2']],
    ]);

    $json = json_encode($exported);
    expect($json)->toBeString();

    $decoded = json_decode((string) $json, true);
    expect($decoded[0]['name'])->toBe('name');
    expect($decoded[0]['rules'][1])->toBe(['rule' => 'min', 'params' => ['2']]);
});

it('returns an empty list for a form with no fields', function () {
    expect(exportFields([]))->toBe([]);
});
