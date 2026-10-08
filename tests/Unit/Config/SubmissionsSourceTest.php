<?php

/**
 * Unit tests for the submissions DataSource shaping (spec 030 US1: FR-002). Pure — the
 * WP_Query/meta access is in the injected reader, stubbed here.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Data\DataQuery;
use Corex\Config\Data\SubmissionsSource;
use Corex\Data\DataField;
use Corex\Tests\Support\InMemorySubmissionsReader;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

it('exposes date/form/summary columns and the submissions key', function () {
    $source = new SubmissionsSource(new InMemorySubmissionsReader([]));

    expect($source->key())->toBe('submissions')
        ->and(array_column($source->columns(), 'id'))->toBe(['date', 'form', 'summary']);
});

it('shapes a submission into id/date/form/summary', function () {
    $source = new SubmissionsSource(new InMemorySubmissionsReader([
        ['id' => 42, 'date' => '2026-06-12 10:00', 'form' => 'contact', 'fields' => ['name' => 'Sam', 'email' => 'sam@example.com']],
    ]));

    $rows = $source->rows(1, 20);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['id'])->toBe(42)
        ->and($rows[0]['form'])->toBe('contact')
        ->and($rows[0]['summary'])->toBe('name: Sam · email: sam@example.com')
        ->and($source->total())->toBe(1);
});

it('returns an empty list when there are no submissions', function () {
    expect((new SubmissionsSource(new InMemorySubmissionsReader([])))->rows(1, 20))->toBe([]);
});

it('deletes by trashing the underlying record', function () {
    expect((new SubmissionsSource(new InMemorySubmissionsReader([])))->delete(42))->toBeTrue();
});

it('answers a query with the same id/date/form/summary shaping', function () {
    $source = new SubmissionsSource(new InMemorySubmissionsReader([
        ['id' => 7, 'date' => '2026-06-13', 'form' => 'contact', 'fields' => ['name' => 'Sam']],
    ]));

    $rows = $source->query(DataQuery::from(['search' => 'sam']));

    expect($rows[0]['summary'])->toBe('name: Sam')
        ->and($source->count(DataQuery::from([])))->toBe(1);
});

it('renders a single record as readable label -> value fields', function () {
    $source = new SubmissionsSource(new InMemorySubmissionsReader([
        ['id' => 7, 'date' => '2026-06-13', 'form' => 'contact', 'fields' => ['full_name' => 'Sam Doe', 'email' => 's@x.com']],
    ]));

    $record = $source->record(7);

    expect($record['id'])->toBe(7)
        ->and($record['fields'][0])->toBe(['label' => 'Full Name', 'value' => 'Sam Doe'])
        ->and($record['fields'][1]['label'])->toBe('Email');
});

it('returns null for an unknown record', function () {
    expect((new SubmissionsSource(new InMemorySubmissionsReader([])))->record(999))->toBeNull();
});

it('derives a real field schema with meaningful types from captured submissions', function () {
    $source = new SubmissionsSource(new InMemorySubmissionsReader([
        ['id' => 1, 'date' => '2026-06-20', 'form' => 'contact', 'fields' => [
            'name' => 'Sam', 'email' => 's@x.com', 'message' => 'Hi', 'phone' => '123',
        ]],
    ]));

    $schema = $source->schema();

    // The three fixed fields first, then derived payload fields with inferred types.
    expect(array_column($schema, 'type'))->toBe(['id', 'datetime', 'form', 'text', 'email', 'textarea', 'tel'])
        ->and($schema[3])->toBe(['name' => 'Name', 'type' => 'text'])
        ->and($schema[4]['type'])->toBe('email')
        ->and($schema[5]['type'])->toBe('textarea');
});

it('returns only the fixed fields when there are no submissions', function () {
    $schema = (new SubmissionsSource(new InMemorySubmissionsReader([])))->schema();

    expect(array_column($schema, 'type'))->toBe(['id', 'datetime', 'form']);
});

it('builds a zero-filled 14-day trend, oldest first, from real timestamps', function () {
    $today = gmdate('Y-m-d');
    $source = new SubmissionsSource(new InMemorySubmissionsReader([
        ['id' => 1, 'date' => $today . ' 09:00', 'form' => 'contact', 'fields' => []],
        ['id' => 2, 'date' => $today . ' 10:00', 'form' => 'contact', 'fields' => []],
    ]));

    $trend = $source->trend(14);

    expect($trend)->toHaveCount(14)
        ->and($trend[13]['date'])->toBe($today)
        ->and($trend[13]['count'])->toBe(2)
        ->and($trend[0]['count'])->toBe(0)
        // strictly ascending dates
        ->and($trend[0]['date'] < $trend[13]['date'])->toBeTrue();
});

/**
 * What the source hands an export (spec 103, US10).
 *
 * The export offers a column for every field the source declares, one per answer among them, and
 * reads each cell from the row by the field's key. The rows it was given were the Records table's:
 * a date, a form and a summary. Every answer's column came out empty, in every format.
 */
function answeredSubmissions(): SubmissionsSource
{
    Functions\when('sanitize_key')->alias(static fn (string $key): string => strtolower($key));
    Functions\when('wp_json_encode')->alias('json_encode');

    return new SubmissionsSource(new InMemorySubmissionsReader([
        ['id' => 7, 'date' => '2026-10-08 13:28:00', 'form' => 'contact', 'fields' => [
            'email' => 'sam@example.com', 'name' => 'Sam', 'message' => 'Hello', 'topics' => ['web', 'brand'],
        ]],
        ['id' => 8, 'date' => '2026-10-08 14:00:00', 'form' => 'callback', 'fields' => ['name' => 'Mona', 'phone' => '01016999700']],
    ]));
}

/** @return list<string> */
function declaredKeys(SubmissionsSource $source): array
{
    return array_map(static fn (DataField $field): string => $field->key, $source->fields());
}

it('hands an export a value under every field it declares, and under nothing else', function () {
    $source = answeredSubmissions();

    $rows = $source->exportRows(DataQuery::from([]));

    expect(declaredKeys($source))->toBe(['date', 'form', 'summary', 'email', 'name', 'message', 'topics', 'phone'])
        ->and(array_keys($rows[0]))->toBe(declaredKeys($source))
        ->and(array_keys($rows[1]))->toBe(declaredKeys($source))
        ->and($rows[0])->toBe([
            'date' => '2026-10-08 13:28:00',
            'form' => 'contact',
            'summary' => 'email: sam@example.com · name: Sam · message: Hello · topics: ["web","brand"]',
            'email' => 'sam@example.com',
            'name' => 'Sam',
            'message' => 'Hello',
            // As it was stored: what a list reads as is the table's to say, as for any source.
            'topics' => ['web', 'brand'],
            // Asked on another form. An empty cell, and still a cell.
            'phone' => '',
        ])
        ->and($rows[1]['phone'])->toBe('01016999700')
        ->and($rows[1]['email'])->toBe('');
});

it('hands an export the same row for a record it is asked for by id', function () {
    $source = answeredSubmissions();
    $all = $source->exportRows(DataQuery::from([]));

    // In the order asked for; a record that is gone is left out, not written as an empty row.
    expect($source->exportRowsOf([8, 999, 7]))->toBe([$all[1], $all[0]])
        ->and($source->exportRowsOf([]))->toBe([]);
});

it('keeps the Records table and the detail view as they were', function () {
    $source = answeredSubmissions();

    expect(array_keys($source->query(DataQuery::from([]))[0]))->toBe(['id', 'date', 'form', 'summary'])
        ->and(array_keys($source->rows(1, 20)[0]))->toBe(['id', 'date', 'form', 'summary'])
        ->and(array_keys((array) $source->record(7)))->toBe(['id', 'date', 'form', 'fields']);
});

it('declares an answer once, however its key was written, and reads it under that one field', function () {
    Functions\when('sanitize_key')->alias(static fn (string $key): string => strtolower($key));
    $source = new SubmissionsSource(new InMemorySubmissionsReader([
        ['id' => 1, 'date' => '2026-10-08 09:00:00', 'form' => 'contact', 'fields' => ['Email' => 'a@example.com']],
        ['id' => 2, 'date' => '2026-10-08 10:00:00', 'form' => 'contact', 'fields' => ['email' => 'b@example.com']],
        // An answer keyed like one of the source's own fields has no column: the summary holds it.
        ['id' => 3, 'date' => '2026-10-08 11:00:00', 'form' => 'booking', 'fields' => ['date' => 'next week']],
    ]));

    $rows = $source->exportRows(DataQuery::from([]));

    expect(declaredKeys($source))->toBe(['date', 'form', 'summary', 'email'])
        ->and(array_column($rows, 'email'))->toBe(['a@example.com', 'b@example.com', ''])
        ->and($rows[2]['date'])->toBe('2026-10-08 11:00:00')
        ->and($rows[2]['summary'])->toBe('date: next week');
});

it('marks an answer as the personal data its kind is, for the export to ask about', function () {
    $classes = [];
    foreach (answeredSubmissions()->fields() as $field) {
        $classes[$field->key] = $field->personalDataClass;
    }

    expect($classes)->toBe([
        'date' => DataField::PERSONAL_NONE,
        'form' => DataField::PERSONAL_NONE,
        'summary' => DataField::PERSONAL_CONTENT,
        'email' => DataField::PERSONAL_CONTACT,
        'name' => DataField::PERSONAL_NONE,
        'message' => DataField::PERSONAL_CONTENT,
        'topics' => DataField::PERSONAL_NONE,
        'phone' => DataField::PERSONAL_CONTACT,
    ]);
});
