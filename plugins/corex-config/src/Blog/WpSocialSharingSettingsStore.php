<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Blog;

defined('ABSPATH') || exit;

/**
 * WordPress option-backed social sharing settings.
 */
final class WpSocialSharingSettingsStore implements SocialSharingSettingsStore
{
    public const OPTION = 'corex_blog_social_sharing';

    public function current(): SocialSharingSettings
    {
        $stored = get_option(self::OPTION, []);
        if (! is_array($stored)) {
            return new SocialSharingSettings();
        }

        $platforms = $stored['enabled_platforms'] ?? null;

        return new SocialSharingSettings(
            enabledPlatforms: is_array($platforms) ? array_values(array_map('sanitize_key', $platforms)) : ['x', 'facebook', 'linkedin', 'copy_link'],
            logClicks: (bool) ($stored['log_clicks'] ?? true),
        );
    }
}
