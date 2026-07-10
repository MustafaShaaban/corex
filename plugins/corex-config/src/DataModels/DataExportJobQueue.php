<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;
interface DataExportJobQueue
{
    public function enqueue(DataExportRun $run): int;
}
