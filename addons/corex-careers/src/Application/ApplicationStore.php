<?php

/**
 * @package Corex\Careers
 */

declare(strict_types=1);

namespace Corex\Careers\Application;

defined('ABSPATH') || exit;

/**
 * Persistence for applications. Keeps the application service headless-testable; the
 * WordPress-backed implementation uses the custom-table repository.
 */
interface ApplicationStore
{
    /**
     * @param array<string,mixed> $data
     *
     * @return int the new application id
     */
    public function create(array $data): int;

    public function setStatus(int $id, string $status): void;
}
