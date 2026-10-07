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

    /**
     * Takes one step of an export's job now, without waiting for the scheduler, and says where the
     * job stands.
     *
     * @return array{state:string,processed:int,total:int,error:string}
     */
    public function advance(int $jobId): array;
}
