<?php

/**
 * Unit tests for the CSV import dry-run validator (spec 065). No WordPress.
 * Contract: match the model's columns, surface unknown/missing columns, and reject bad rows with a
 * reason — while writing nothing (this class has no persistence to exercise).
 *
 * @package Corex\Tests\Unit\DataModels
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\DataModels\DataImportValidator;

beforeEach(function () {
    Functions\when('__')->returnArg();
    $this->validator = new DataImportValidator();
});

it('accepts rows whose width matches the header and reports matched columns', function () {
    $result = $this->validator->validate(
        ['name', 'email'],
        ['name', 'email'],
        [['Ada', 'ada@example.com'], ['Grace', 'grace@example.com']],
    );

    expect($result['accepted'])->toBe(2)
        ->and($result['totalRows'])->toBe(2)
        ->and($result['rejected'])->toBe([])
        ->and($result['matched'])->toBe(['name', 'email']);
});

it('surfaces unknown and missing columns without rejecting the import', function () {
    $result = $this->validator->validate(
        ['name', 'email'],
        ['name', 'phone'],
        [['Ada', '555-0100']],
    );

    expect($result['unknown'])->toBe(['phone'])
        ->and($result['missing'])->toBe(['email'])
        ->and($result['matched'])->toBe(['name']);
});

it('rejects a row whose column count differs from the header, citing the line', function () {
    $result = $this->validator->validate(
        ['name', 'email'],
        ['name', 'email'],
        [['Ada', 'ada@example.com'], ['OnlyOneCell']],
    );

    expect($result['accepted'])->toBe(1)
        ->and($result['rejected'])->toHaveCount(1)
        ->and($result['rejected'][0]['line'])->toBe(3) // header=1, first row=2, bad row=3
        ->and($result['rejected'][0]['reason'])->toContain('Column count');
});

it('rejects an all-empty row as empty', function () {
    $result = $this->validator->validate(['name'], ['name'], [['   ']]);

    expect($result['accepted'])->toBe(0)
        ->and($result['rejected'][0]['reason'])->toContain('empty');
});

it('caps the rejected-row report to protect memory', function () {
    $rows = array_fill(0, 100, ['a', 'b', 'c']); // width 3 vs header width 2 -> all rejected

    $result = $this->validator->validate(['name', 'email'], ['name', 'email'], $rows);

    expect($result['totalRows'])->toBe(100)
        ->and($result['accepted'])->toBe(0)
        ->and(count($result['rejected']))->toBe(DataImportValidator::MAX_REPORTED);
});
