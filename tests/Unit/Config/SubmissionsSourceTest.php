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
use Corex\Config\Data\SubmissionsReader;
use Corex\Config\Data\SubmissionsSource;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

function stubReader(array $records, int $total = 0): SubmissionsReader
{
    return new class($records, $total) implements SubmissionsReader {
        public function __construct(private array $records, private int $totalCount)
        {
        }

        public function page(int $page, int $perPage): array
        {
            return $this->records;
        }

        public function total(): int
        {
            return $this->totalCount;
        }

        public function trash(int $id): bool
        {
            return $id > 0;
        }

        public function query(DataQuery $query): array
        {
            return $this->records;
        }

        public function count(DataQuery $query): int
        {
            return $this->totalCount;
        }

        public function find(int $id): ?array
        {
            foreach ($this->records as $record) {
                if ($record['id'] === $id) {
                    return $record;
                }
            }

            return null;
        }
    };
}

it('exposes date/form/summary columns and the submissions key', function () {
    $source = new SubmissionsSource(stubReader([]));

    expect($source->key())->toBe('submissions')
        ->and(array_column($source->columns(), 'id'))->toBe(['date', 'form', 'summary']);
});

it('shapes a submission into id/date/form/summary', function () {
    $source = new SubmissionsSource(stubReader([
        ['id' => 42, 'date' => '2026-06-12 10:00', 'form' => 'contact', 'fields' => ['name' => 'Sam', 'email' => 'sam@example.com']],
    ], 1));

    $rows = $source->rows(1, 20);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['id'])->toBe(42)
        ->and($rows[0]['form'])->toBe('contact')
        ->and($rows[0]['summary'])->toBe('name: Sam · email: sam@example.com')
        ->and($source->total())->toBe(1);
});

it('returns an empty list when there are no submissions', function () {
    expect((new SubmissionsSource(stubReader([])))->rows(1, 20))->toBe([]);
});

it('deletes by trashing the underlying record', function () {
    expect((new SubmissionsSource(stubReader([])))->delete(42))->toBeTrue();
});

it('answers a query with the same id/date/form/summary shaping', function () {
    $source = new SubmissionsSource(stubReader([
        ['id' => 7, 'date' => '2026-06-13', 'form' => 'contact', 'fields' => ['name' => 'Sam']],
    ], 1));

    $rows = $source->query(DataQuery::from(['search' => 'sam']));

    expect($rows[0]['summary'])->toBe('name: Sam')
        ->and($source->count(DataQuery::from([])))->toBe(1);
});

it('renders a single record as readable label -> value fields', function () {
    $source = new SubmissionsSource(stubReader([
        ['id' => 7, 'date' => '2026-06-13', 'form' => 'contact', 'fields' => ['full_name' => 'Sam Doe', 'email' => 's@x.com']],
    ], 1));

    $record = $source->record(7);

    expect($record['id'])->toBe(7)
        ->and($record['fields'][0])->toBe(['label' => 'Full Name', 'value' => 'Sam Doe'])
        ->and($record['fields'][1]['label'])->toBe('Email');
});

it('returns null for an unknown record', function () {
    expect((new SubmissionsSource(stubReader([])))->record(999))->toBeNull();
});
