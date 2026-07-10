<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

/**
 * Service aggregate for the Blog Pro REST boundary.
 */
final readonly class BlogProServices
{
    public function __construct(
        public BlogAnalyticsService $analytics,
        public EditorialWorkflowService $editorial,
        public CommentModerationService $comments,
        public AuthorAnalyticsService $authors,
        public SocialSharingService $sharing,
    ) {
    }
}
