<?php

/**
 * @package Corex\Portfolio
 */

declare(strict_types=1);

namespace Corex\Portfolio\Blocks;

defined('ABSPATH') || exit;

/**
 * Reads a project's structured meta from the registered `corex_project_*` post meta. The only place
 * that touches WordPress for {@see ProjectMetaRenderer}, so the renderer stays unit-testable.
 */
final class WpProjectMetaProvider implements ProjectMetaProvider
{
    public const CLIENT = 'corex_project_client';
    public const ROLE   = 'corex_project_role';
    public const YEAR   = 'corex_project_year';
    public const URL    = 'corex_project_url';

    /**
     * @return array{client:string,role:string,year:string,url:string}
     */
    public function metaFor(int $postId): array
    {
        return [
            'client' => trim((string) get_post_meta($postId, self::CLIENT, true)),
            'role'   => trim((string) get_post_meta($postId, self::ROLE, true)),
            'year'   => trim((string) get_post_meta($postId, self::YEAR, true)),
            'url'    => trim((string) get_post_meta($postId, self::URL, true)),
        ];
    }
}
