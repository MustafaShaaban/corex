<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

interface SubmissionTimelineStore
{
    /**
     * @param array<string,mixed> $summary
     * @return array<string,mixed>
     */
    public function append(int $submissionId, string $stage, string $outcome, array $summary): array;

    /** @return list<array<string,mixed>> */
    public function forSubmission(int $submissionId, bool $includeRestricted): array;
}
