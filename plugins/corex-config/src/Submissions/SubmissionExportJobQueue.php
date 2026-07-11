<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

interface SubmissionExportJobQueue
{
    public function enqueue(SubmissionExportRun $run): int;
}
