<?php

/**
 * A Data source's records as a table a person reads (spec 103, US10).
 *
 * The Data export wrote every cell as `(string) $value`: a number as text, a date as it was
 * stored, and a list as the word "Array". A source declares what each field is, so the table is
 * built from that.
 *
 * @package Corex\Tests\Unit\DataModels
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\DataModels\DataExportTable;
use Corex\Config\Export\ExportCell;
use Corex\Data\DataField;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

function dataField(string $key, string $label, string $type): DataField
{
    return new DataField($key, $label, $type, false, true, true, [], true, DataField::PERSONAL_NONE, [], []);
}

/**
 * The one row of a sheet built from one record.
 *
 * @param list<DataField> $fields
 * @param list<string>    $columns
 *
 * @return list<ExportCell>
 */
function exportedRow(array $fields, array $columns, array $record): array
{
    $table = new DataExportTable(static fn (): DateTimeZone => new DateTimeZone('Africa/Cairo'));
    $rows = [...$table->sheet('Orders', $fields, $columns, [$record])->rows];

    return $rows[0];
}

it('heads each column with the field’s label, in the order the columns were asked for', function () {
    $table = new DataExportTable(static fn (): DateTimeZone => new DateTimeZone('UTC'));
    $sheet = $table->sheet('Orders', [
        dataField('id', 'ID', DataField::TYPE_ID),
        dataField('customer', 'Customer', DataField::TYPE_TEXT),
        dataField('total', 'Total', DataField::TYPE_DECIMAL),
    ], ['total', 'customer'], []);

    expect($sheet->name)->toBe('Orders')
        ->and($sheet->headings)->toBe(['Total', 'Customer']);
});

it('writes a number as a number and keeps a number that is not one as text', function (string $type, mixed $stored, string $kind, mixed $value) {
    $cell = exportedRow([dataField('amount', 'Amount', $type)], ['amount'], ['amount' => $stored])[0];

    expect($cell->type)->toBe($kind)
        ->and($cell->value)->toBe($value);
})->with([
    'an id' => [DataField::TYPE_ID, '41', ExportCell::NUMBER, 41],
    'an integer' => [DataField::TYPE_INTEGER, 12, ExportCell::NUMBER, 12],
    'a decimal' => [DataField::TYPE_DECIMAL, '19.50', ExportCell::NUMBER, 19.5],
    'a decimal that was never filled in' => [DataField::TYPE_DECIMAL, '', ExportCell::TEXT, ''],
    'an integer field holding words' => [DataField::TYPE_INTEGER, 'n/a', ExportCell::TEXT, 'n/a'],
    // Digits, and not a number: a leading zero is part of it.
    'a phone number' => [DataField::TYPE_TEL, '01016999700', ExportCell::TEXT, '01016999700'],
]);

it('writes a date and time as one, in the site’s time when the stored value says where it is from', function () {
    $fields = [dataField('placed', 'Placed', DataField::TYPE_DATETIME)];
    $withOffset = exportedRow($fields, ['placed'], ['placed' => '2026-10-07T09:30:00+00:00'])[0];
    $asStored = exportedRow($fields, ['placed'], ['placed' => '2026-10-07 09:30:00'])[0];

    expect($withOffset->type)->toBe(ExportCell::DATETIME)
        ->and($withOffset->asText())->toBe('2026-10-07 12:30')
        // No offset was stored, so none is invented: it reads as it was stored.
        ->and($asStored->asText())->toBe('2026-10-07 09:30');
});

it('keeps a date and time it cannot read, and a day without a time, as they were stored', function (string $type, string $stored) {
    $cell = exportedRow([dataField('when', 'When', $type)], ['when'], ['when' => $stored])[0];

    expect($cell->type)->toBe(ExportCell::TEXT)
        ->and($cell->value)->toBe($stored);
})->with([
    'not a date' => [DataField::TYPE_DATETIME, 'next week'],
    'never set' => [DataField::TYPE_DATETIME, '0000-00-00 00:00:00'],
    'a day' => [DataField::TYPE_DATE, '2026-10-07'],
]);

it('writes yes or no for a switch', function (mixed $stored, string $words) {
    $cell = exportedRow([dataField('paid', 'Paid', DataField::TYPE_BOOLEAN)], ['paid'], ['paid' => $stored])[0];

    expect($cell->value)->toBe($words);
})->with([[true, 'Yes'], ['1', 'Yes'], [0, 'No'], ['', 'No'], [null, 'No']]);

it('writes a list as its items and anything deeper as what it holds, never as the word Array', function (mixed $stored, string $words) {
    $cell = exportedRow([dataField('tags', 'Tags', DataField::TYPE_JSON)], ['tags'], ['tags' => $stored])[0];

    expect($cell->value)->toBe($words);
})->with([
    'a list' => [['red', 'blue'], 'red, blue'],
    'a record' => [['city' => 'القاهرة', 'zip' => 11511], '{"city":"القاهرة","zip":11511}'],
    'nothing' => [null, ''],
]);

it('leaves out a field that was not asked for, and writes an empty cell for one the record lacks', function () {
    $row = exportedRow([
        dataField('name', 'Name', DataField::TYPE_TEXT),
        dataField('email', 'Email', DataField::TYPE_EMAIL),
        dataField('note', 'Note', DataField::TYPE_TEXTAREA),
    ], ['name', 'note'], ['name' => 'Salma', 'email' => 'salma@example.com']);

    expect(array_map(static fn (ExportCell $cell): string => $cell->asText(), $row))->toBe(['Salma', '']);
});
