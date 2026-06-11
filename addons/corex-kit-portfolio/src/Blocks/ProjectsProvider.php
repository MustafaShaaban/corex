<?php

/**
 * @package Corex\Portfolio
 */

declare(strict_types=1);

namespace Corex\Portfolio\Blocks;

defined('ABSPATH') || exit;

/**
 * Supplies recent projects as plain {title, url, thumbnail} rows. The interface keeps
 * the projects-grid renderer headless-testable; the WordPress-backed implementation
 * queries the `corex_project` CPT.
 */
interface ProjectsProvider
{
    /**
     * @return list<array{title:string,url:string,thumbnail:string}>
     */
    public function recent(int $count): array;
}
