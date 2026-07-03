<?php

/**
 * @package Corex\Portfolio
 */

declare(strict_types=1);

namespace Corex\Portfolio\Blocks;

defined('ABSPATH') || exit;

/**
 * Supplies a project's structured meta (client, role, year, external URL) as plain strings. The
 * interface keeps {@see ProjectMetaRenderer} headless-testable; the WordPress-backed implementation
 * reads the registered `corex_project_*` post meta. Unset fields come back as empty strings so the
 * renderer can omit them — an honest empty, never a fabricated value.
 */
interface ProjectMetaProvider
{
    /**
     * @return array{client:string,role:string,year:string,url:string}
     */
    public function metaFor(int $postId): array;
}
