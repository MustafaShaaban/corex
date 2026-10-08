<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Config\Export\ExportWriters;

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
        public SubmissionExportHistory $exportHistory,
        public ExportWriters $exportFormats,
        public SubmissionEmailService $email,
        public SubmissionAccessPolicy $access,
    ) {
    }
}
