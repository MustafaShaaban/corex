<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

interface SubmissionBulkPreviewStore
{
    /**
     * @param list<array{id:int,updated_at:string}> $records
     * @param array<string,mixed> $parameters
     */
    public function issue(int $actorId, string $action, array $records, array $parameters): SubmissionBulkPreview;

    public function consume(string $token, int $actorId): ?SubmissionBulkPreview;
}
