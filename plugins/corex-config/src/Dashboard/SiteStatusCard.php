<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Dashboard;

defined('ABSPATH') || exit;

/**
 * Pure view model for the Corex dashboard "Site status" card (spec 042): which kits are applied, the live
 * contact-submission count and where to find them, and the current front-page status. The corex-config boundary
 * supplies the live inputs (provisioner, submissions reader, front-page option); this only decides the shape —
 * including the actionable empty state — so it is WordPress-free and unit-testable.
 */
final class SiteStatusCard
{
    public const FRONT_COREX_PAGE = 'corex_page';
    public const FRONT_BLANK      = 'blank';
    public const FRONT_BLOG_INDEX = 'blog_index';

    /**
     * @param list<string> $appliedKits
     *
     * @return array{
     *   appliedKits:list<string>,
     *   submissionCount:int,
     *   submissionsUrl:string,
     *   frontPage:string,
     *   isEmptyState:bool
     * }
     */
    public function model(array $appliedKits, int $submissionCount, string $submissionsUrl, string $frontPage): array
    {
        return [
            'appliedKits'     => $appliedKits,
            'submissionCount' => $submissionCount,
            'submissionsUrl'  => $submissionsUrl,
            'frontPage'       => $frontPage,
            'isEmptyState'    => $appliedKits === [] && $submissionCount === 0,
        ];
    }
}
