<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

/**
 * Functional Blog Pro navigation metadata.
 */
final class BlogProModel
{
    public const ANALYTICS = 'analytics';
    public const EDITORIAL = 'editorial';
    public const COMMENTS = 'comments';
    public const AUTHORS = 'authors';
    public const SHARING = 'sharing';

    /**
     * @return array<string,string>
     */
    public function tabs(): array
    {
        return [
            self::ANALYTICS => __('Analytics', 'corex'),
            self::EDITORIAL => __('Editorial workflow', 'corex'),
            self::COMMENTS => __('Comments', 'corex'),
            self::AUTHORS => __('Authors', 'corex'),
            self::SHARING => __('Sharing', 'corex'),
        ];
    }

    public function activeTab(string $tab): string
    {
        return array_key_exists($tab, $this->tabs()) ? $tab : self::ANALYTICS;
    }
}
