<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

/**
 * Persistence boundary for editable Email Studio assets.
 */
interface EmailStudioStore
{
    /** @param array<string,mixed> $payload */
    public function create(string $type, string $slug, string $name, int $parentId, array $payload): int;

    /** @param array<string,mixed> $payload */
    public function update(int $id, string $name, array $payload): bool;

    /** @return array{id:int,type:string,slug:string,name:string,parentId:int,payload:array<string,mixed>}|null */
    public function find(int $id): ?array;

    /** @return array{id:int,type:string,slug:string,name:string,parentId:int,payload:array<string,mixed>}|null */
    public function findBySlug(string $type, string $slug): ?array;

    /** @return list<array{id:int,type:string,slug:string,name:string,parentId:int,payload:array<string,mixed>}> */
    public function all(string $type, ?int $parentId = null): array;
}
