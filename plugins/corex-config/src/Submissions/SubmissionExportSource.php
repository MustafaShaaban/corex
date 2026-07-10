<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

interface SubmissionExportSource extends SubmissionInboxReader
{
    /** @param list<int> $submissionIds */
    public function markExported(array $submissionIds, string $exportedAt): void;
}
