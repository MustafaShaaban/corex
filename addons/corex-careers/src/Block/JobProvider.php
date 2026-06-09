<?php

/**
 * @package Corex\Careers
 */

declare(strict_types=1);

namespace Corex\Careers\Block;

defined('ABSPATH') || exit;

/**
 * Supplies open jobs as plain rows. Keeps the jobs block renderer headless-testable;
 * the WordPress-backed implementation queries the job CPT.
 */
interface JobProvider
{
    /**
     * @return list<array{title:string,url:string,department:string,location:string,type:string}>
     */
    public function openJobs(int $count): array;
}
