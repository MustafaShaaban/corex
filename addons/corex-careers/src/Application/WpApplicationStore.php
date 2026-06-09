<?php

/**
 * @package Corex\Careers
 */

declare(strict_types=1);

namespace Corex\Careers\Application;

defined('ABSPATH') || exit;

/**
 * Bridges the application store contract to the custom-table repository.
 */
final class WpApplicationStore implements ApplicationStore
{
    public function __construct(private readonly ApplicationRepository $repository)
    {
    }

    public function create(array $data): int
    {
        return $this->repository->insert($data + ['created_at' => current_time('mysql')]);
    }

    public function setStatus(int $id, string $status): void
    {
        $this->repository->update($id, ['status' => $status, 'updated_at' => current_time('mysql')]);
    }
}
