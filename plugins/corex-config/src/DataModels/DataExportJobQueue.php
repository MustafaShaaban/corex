<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;
interface DataExportJobQueue
{
    public function enqueue(DataExportRun $run): int;

    /**
     * Takes one step of an export's job now, in this request.
     *
     * @return array{state:string,processed:int,total:int,error:string}
     */
    public function advance(int $jobId): array;
}
