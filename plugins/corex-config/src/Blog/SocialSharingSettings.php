<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

/**
 * Blog social sharing configuration.
 */
final readonly class SocialSharingSettings
{
    /**
     * @param list<string> $enabledPlatforms
     */
    public function __construct(
        public array $enabledPlatforms = ['x', 'facebook', 'linkedin', 'copy_link'],
        public bool $logClicks = true,
    ) {
    }
}
