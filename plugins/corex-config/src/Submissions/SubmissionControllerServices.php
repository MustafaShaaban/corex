<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

/**
 * Explicit Inbox application services consumed by the thin REST boundary.
 */
final readonly class SubmissionControllerServices
{
    public function __construct(
        public SubmissionQueryService $queries,
        public SubmissionWorkflowService $workflow,
        public SubmissionBulkService $bulk,
        public SubmissionExportService $exports,
        public SubmissionEmailService $email,
        public SubmissionAccessPolicy $access,
    ) {
    }
}
