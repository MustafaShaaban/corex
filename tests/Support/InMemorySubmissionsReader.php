<?php

/**
 * Stored submissions held in memory, for the tests of what is built from them.
 *
 * `SubmissionsSource` shapes what a reader hands it, and the Data export writes what the source
 * shapes. Both are tested without WordPress, so both need a reader that is not `WP_Query`: this
 * one answers from the records it was given, in the order it was given them.
 *
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

use Corex\Config\Data\DataQuery;
use Corex\Config\Data\SubmissionsReader;

final class InMemorySubmissionsReader implements SubmissionsReader
{
    /**
     * @param list<array{id:int,date:string,form:string,fields:array<string,mixed>}> $records
     * @param int|null $total What the reader reports as its total; the number of records when null.
     */
    public function __construct(private readonly array $records, private readonly ?int $total = null)
    {
    }

    public function page(int $page, int $perPage): array
    {
        return array_slice($this->records, ($page - 1) * $perPage, $perPage);
    }

    public function total(): int
    {
        return $this->total ?? count($this->records);
    }

    public function trash(int $id): bool
    {
        return $id > 0;
    }

    public function query(DataQuery $query): array
    {
        return array_slice($this->matching($query), ($query->page - 1) * $query->perPage, $query->perPage);
    }

    public function count(DataQuery $query): int
    {
        return $this->total ?? count($this->matching($query));
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

    public function fieldKeys(int $sample): array
    {
        $keys = [];
        foreach (array_slice($this->records, 0, $sample) as $record) {
            foreach (array_keys($record['fields']) as $key) {
                $keys[$key] = true;
            }
        }

        return array_map('strval', array_keys($keys));
    }

    public function dailyCounts(int $days): array
    {
        $counts = [];
        foreach ($this->records as $record) {
            $day = substr($record['date'], 0, 10);
            $counts[$day] = ($counts[$day] ?? 0) + 1;
        }

        return $counts;
    }

    /** @return list<array{id:int,date:string,form:string,fields:array<string,mixed>}> */
    private function matching(DataQuery $query): array
    {
        $form = $query->filters['form'] ?? '';

        return array_values(array_filter(
            $this->records,
            static fn (array $record): bool => $form === '' || $record['form'] === $form,
        ));
    }
}
