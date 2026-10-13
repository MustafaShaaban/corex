<?php

/**
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

use Corex\Email\Studio\EmailStudioStore;

/**
 * Email Studio's store with nothing in it and nowhere to keep anything: a site on which CoreX
 * Mail has only just been switched on, for a test that must not empty a real one to see it.
 */
final class InMemoryEmailStudioStore implements EmailStudioStore
{
    /** @var array<int,array{id:int,type:string,slug:string,name:string,parentId:int,payload:array<string,mixed>}> */
    private array $records = [];

    public function create(string $type, string $slug, string $name, int $parentId, array $payload): int
    {
        $id = count($this->records) + 1;
        $this->records[$id] = [
            'id' => $id,
            'type' => $type,
            'slug' => $slug,
            'name' => $name,
            'parentId' => $parentId,
            'payload' => $payload,
        ];

        return $id;
    }

    public function update(int $id, string $name, array $payload): bool
    {
        if (! isset($this->records[$id])) {
            return false;
        }

        $this->records[$id]['name'] = $name;
        $this->records[$id]['payload'] = $payload;

        return true;
    }

    public function find(int $id): ?array
    {
        return $this->records[$id] ?? null;
    }

    public function findBySlug(string $type, string $slug): ?array
    {
        foreach ($this->records as $record) {
            if ($record['type'] === $type && $record['slug'] === $slug) {
                return $record;
            }
        }

        return null;
    }

    public function all(string $type, ?int $parentId = null): array
    {
        return array_values(array_filter(
            $this->records,
            static fn (array $record): bool => $record['type'] === $type
                && ($parentId === null || $record['parentId'] === $parentId),
        ));
    }
}
