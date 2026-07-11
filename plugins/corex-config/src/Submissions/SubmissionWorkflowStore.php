<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

interface SubmissionWorkflowStore
{
    /** @return array<string,mixed>|null */
    public function findWorkflow(int $id): ?array;

    /**
     * @param array<string,mixed> $changes
     * @return array<string,mixed>
     */
    public function updateWorkflow(int $id, array $changes, string $expectedUpdatedAt): array;

    /** @return array<string,mixed> */
    public function addWorkflowNote(int $id, int $authorId, string $body, string $visibility): array;
}
